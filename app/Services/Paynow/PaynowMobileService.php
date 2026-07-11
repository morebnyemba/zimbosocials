<?php

namespace App\Services\Paynow;

use App\Models\AuditLog;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DepositService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Paynow\Payments\Paynow;
use Paynow\Util\Hash;

/**
 * Shared Paynow mobile-money (Express Checkout) initiation, used by both the
 * web PaynowController and the WhatsApp deposit flow so there is ONE tested
 * path. Creates a pending deposit, pushes the payment request to the provider,
 * and returns a normalized result. Crediting happens later via the Paynow
 * webhook (paynow.update) when the customer approves.
 */
class PaynowMobileService
{
    /** method key => Paynow method string + metadata (normalised 10-digit prefixes). */
    public const PROVIDERS = [
        'ecocash' => ['label' => 'EcoCash', 'method' => 'ecocash', 'prefixes' => ['077', '078']],
        'onemoney' => ['label' => 'OneMoney', 'method' => 'onemoney', 'prefixes' => ['071']],
        'innbucks' => ['label' => 'InnBucks', 'method' => 'innbucks', 'prefixes' => []],
        'omari' => ['label' => "O'mari", 'method' => 'omari', 'prefixes' => ['077', '078']],
    ];

    public function __construct(
        private readonly Application $app,
        private readonly DepositService $depositService,
    ) {}

    public static function isProvider(string $provider): bool
    {
        return array_key_exists($provider, self::PROVIDERS);
    }

    /**
     * Initiate an express-checkout mobile payment.
     *
     * @return array{ok:bool, flow?:string, message?:string, instructions?:?string,
     *   transaction?:Transaction, transaction_id?:int, authorization_code?:string,
     *   deep_link?:string, qr_url?:string, otp_reference?:string, error?:string}
     */
    public function initiate(User $user, string $provider, string $rawPhone, float $amount): array
    {
        if (! self::isProvider($provider)) {
            return ['ok' => false, 'error' => 'unknown_provider', 'message' => 'That payment method is not available.'];
        }
        $config = self::PROVIDERS[$provider];

        if ($amount < 1) {
            return ['ok' => false, 'error' => 'invalid_amount', 'message' => 'Enter an amount of at least 1.'];
        }

        $phone = $this->normalizeZwPhone($rawPhone);
        if ($phone === null) {
            return ['ok' => false, 'error' => 'invalid_phone', 'message' => 'Please enter a valid Zimbabwean mobile number (e.g. 0771234567).'];
        }

        if (! empty($config['prefixes']) && ! $this->matchesPrefix($phone, $config['prefixes'])) {
            $expected = implode(' or ', $config['prefixes']);

            return ['ok' => false, 'error' => 'wrong_network', 'message' => "{$config['label']} numbers must start with {$expected}."];
        }

        if (empty(config('services.paynow.integration_id'))) {
            return ['ok' => false, 'error' => 'paynow_unconfigured', 'message' => 'Mobile payments are not available right now.'];
        }

        $transaction = $this->createPendingTransaction($user, $amount, $provider);

        try {
            $paynow = $this->getPaynow();
            $payment = $paynow->createPayment((string) $transaction->id, $this->authEmail($user));
            $payment->add('Account Deposit', $amount);

            $response = $paynow->sendMobile($payment, $phone, $config['method']);

            if (! $response->success()) {
                $this->depositService->reject($transaction, 'paynow_mobile_init_failed', 'Mobile init failed');

                return ['ok' => false, 'error' => 'init_failed', 'message' => 'Could not send the payment request. Please try again.'];
            }

            $data = $response->data();
            $transaction->update(['reference' => $response->pollUrl()]);

            if ($provider === 'innbucks') {
                $authCode = $data['authorizationcode'] ?? '';
                $transaction->update(['gateway_meta' => [
                    'authorizationcode' => $authCode,
                    'authorizationexpires' => $data['authorizationexpires'] ?? '',
                ]]);

                return [
                    'ok' => true, 'flow' => 'innbucks_authcode', 'transaction' => $transaction, 'transaction_id' => (int) $transaction->id,
                    'authorization_code' => $authCode,
                    'deep_link' => 'com.innbucks.customer://purchase?paymentToken='.$authCode,
                    'qr_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='.urlencode($authCode),
                    'instructions' => $response->instructions(),
                ];
            }

            if ($provider === 'omari') {
                $transaction->update(['gateway_meta' => [
                    'otpreference' => $data['otpreference'] ?? '',
                    'remoteotpurl' => $data['remoteotpurl'] ?? '',
                ]]);

                return [
                    'ok' => true, 'flow' => 'omari_otp', 'transaction' => $transaction, 'transaction_id' => (int) $transaction->id,
                    'otp_reference' => $data['otpreference'] ?? '',
                    'message' => "An OTP was sent to {$phone}. Enter it to complete the payment.",
                ];
            }

            // EcoCash / OneMoney — USSD PIN push.
            return [
                'ok' => true, 'flow' => 'ussd_pin', 'transaction' => $transaction, 'transaction_id' => (int) $transaction->id,
                'message' => "Check your phone and enter your {$config['label']} PIN to approve the payment.",
                'instructions' => $response->instructions(),
            ];
        } catch (\Throwable $e) {
            Log::error('PaynowMobileService init error', ['provider' => $provider, 'transaction_id' => $transaction->id, 'message' => $e->getMessage()]);
            $this->depositService->reject($transaction, 'paynow_mobile_exception', 'Exception: '.$e->getMessage());

            return ['ok' => false, 'error' => 'exception', 'message' => 'Payment gateway error. Please try again later.'];
        }
    }

    /**
     * Submit the OTP for an O'mari express-checkout transaction (raw Paynow call;
     * callers own the transaction/ownership/status checks).
     *
     * @return array{ok:bool, message:string}
     */
    public function submitOtp(Transaction $transaction, string $otp): array
    {
        $meta = (array) ($transaction->gateway_meta ?? []);
        $remoteOtpUrl = $meta['remoteotpurl'] ?? null;
        if (empty($remoteOtpUrl)) {
            return ['ok' => false, 'message' => 'OTP submission is not available for this transaction.'];
        }

        $integrationId = (string) config('services.paynow.integration_id');
        // The SDK lowercases the key before hashing for mobile/express requests.
        $integrationKey = strtolower((string) config('services.paynow.integration_key'));
        $otp = trim($otp);

        $hash = Hash::make([
            'id' => $integrationId,
            'otp' => $otp,
            'status' => 'Message',
        ], $integrationKey);

        try {
            $res = Http::asForm()->post($remoteOtpUrl, [
                'id' => $integrationId, 'otp' => $otp, 'status' => 'Message', 'hash' => $hash,
            ]);
            parse_str($res->body(), $params);

            if (isset($params['status']) && strtolower($params['status']) === 'error') {
                return ['ok' => false, 'message' => $params['error'] ?? 'Invalid OTP'];
            }

            return ['ok' => true, 'message' => 'OTP accepted. Processing your payment…'];
        } catch (\Throwable $e) {
            Log::error("PaynowMobileService O'mari OTP error", ['transaction_id' => $transaction->id, 'message' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Could not submit OTP. Please try again.'];
        }
    }

    public function normalizeZwPhone(string $raw): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', $raw);
        if (strlen($digits) === 12 && str_starts_with($digits, '263')) {
            $digits = '0'.substr($digits, 3);
        }
        if (strlen($digits) === 9) {
            $digits = '0'.$digits;
        }

        return (strlen($digits) === 10 && str_starts_with($digits, '0')) ? $digits : null;
    }

    private function matchesPrefix(string $phone, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($phone, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function authEmail(User $user): string
    {
        return (string) (config('services.paynow.test_email') ?: $user->email);
    }

    private function getPaynow(): Paynow
    {
        if ($this->app->bound(Paynow::class)) {
            return $this->app->make(Paynow::class);
        }

        return new Paynow(
            config('services.paynow.integration_id'),
            config('services.paynow.integration_key'),
            route('paynow.return'),
            route('paynow.update'),
        );
    }

    private function createPendingTransaction(User $user, float $amount, string $method): Transaction
    {
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => 'deposit',
            'amount' => $amount,
            'balance_before' => (float) $user->balance,
            'balance_after' => (float) $user->balance + $amount,
            'method' => $method,
            'status' => 'pending',
            'notes' => 'Initiated via Paynow ('.strtoupper($method).')',
        ]);

        AuditLog::dispatchLog(
            action: 'transaction.paynow_created_pending',
            userId: (int) $user->getAuthIdentifier(),
            modelType: Transaction::class,
            modelId: (int) $transaction->getKey(),
            newValues: ['status' => 'pending', 'method' => $method, 'amount' => $amount],
        );

        return $transaction;
    }
}
