<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DepositService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Paynow\Payments\Paynow;
use Paynow\Util\Hash;

class PaynowController extends Controller
{
    /**
     * Supported mobile money providers: method key => Paynow method string + metadata.
     * Phone prefix lists are the leading digits of a normalised 10-digit Zimbabwean number.
     */
    private const MOBILE_PROVIDERS = [
        'ecocash' => ['label' => 'EcoCash',  'method' => 'ecocash',  'prefixes' => ['077', '078']],
        'onemoney' => ['label' => 'OneMoney', 'method' => 'onemoney', 'prefixes' => ['071']],
        'innbucks' => ['label' => 'InnBucks', 'method' => 'innbucks', 'prefixes' => []],     // any number can use InnBucks wallet
        'omari' => ['label' => "O'mari",   'method' => 'omari',    'prefixes' => ['077', '078']],
    ];

    public function __construct(
        private readonly Application $app,
        private readonly DepositService $depositService,
    ) {}

    /**
     * Human-readable description of an exception. The Paynow SDK throws several
     * empty-message exceptions (HashMismatchException, InvalidIntegrationException),
     * so fall back to the class short-name when the message is blank.
     */
    private function describeException(\Throwable $e): string
    {
        $message = trim($e->getMessage());

        return $message !== '' ? $message : class_basename($e);
    }

    /**
     * Structured log context capturing the full exception detail (class, message,
     * code, location) plus any extra fields — so blank-message gateway errors are
     * still identifiable in the log.
     */
    private function exceptionContext(\Throwable $e, array $extra = []): array
    {
        return array_merge([
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'at' => $e->getFile().':'.$e->getLine(),
        ], $extra);
    }

    /**
     * The authemail sent to Paynow. In test mode Paynow only accepts the
     * merchant's own registered email, so PAYNOW_TEST_EMAIL lets us test the
     * full flow without editing customer accounts. Blank in production (live
     * mode) → the real customer email is used.
     */
    private function authEmail(User $user): string
    {
        return (string) (config('services.paynow.test_email') ?: $user->email);
    }

    private function getPaynow(): Paynow
    {
        // Resolve through the container so tests can override with a mock
        if ($this->app->bound(Paynow::class)) {
            return $this->app->make(Paynow::class);
        }

        // Paynow constructor signature is (id, key, resultUrl, returnUrl) — result first.
        // resultUrl = server-to-server status update (our webhook); returnUrl = browser redirect back.
        return new Paynow(
            config('services.paynow.integration_id'),
            config('services.paynow.integration_key'),
            route('paynow.update'),
            route('paynow.return')
        );
    }

    public function init(Request $request)
    {
        // This endpoint only handles the Paynow web/card flow; mobile money has
        // its own route. Default the method so the front-end need only send amount.
        $method = $request->input('method', 'paynow');

        $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        // Reject mobile methods — they have a dedicated route
        if ($method !== 'paynow') {
            return response()->json([
                'success' => false,
                'message' => 'Use the provider-specific endpoint for mobile money payments.',
            ], 422);
        }

        $user = $request->user();
        $amount = (float) $request->amount;

        $transaction = $this->createPendingTransaction($user, $amount, 'paynow');

        $paynow = $this->getPaynow();
        $payment = $paynow->createPayment((string) $transaction->id, $this->authEmail($user));
        $payment->add('Account Deposit', $amount);

        try {
            $response = $paynow->send($payment);

            if ($response->success()) {
                $transaction->update(['reference' => $response->pollUrl()]);

                return response()->json([
                    'success' => true,
                    'redirect_url' => $response->redirectUrl(),
                    'transaction_id' => $transaction->getKey(),
                ]);
            }

            return response()->json(['success' => false, 'message' => 'Failed to initiate Paynow transaction.'], 400);

        } catch (\Throwable $e) {
            Log::error('Paynow Init Error', $this->exceptionContext($e, ['method' => 'paynow', 'transaction_id' => $transaction->id]));

            return response()->json(['success' => false, 'message' => 'Payment gateway error: '.$this->describeException($e)], 500);
        }
    }

    /**
     * Initiate an express checkout for a specific mobile money provider.
     * Route: POST /paynow/mobile/{provider}  (ecocash|onemoney|innbucks|omari)
     */
    public function initMobile(Request $request, string $provider)
    {
        if (! array_key_exists($provider, self::MOBILE_PROVIDERS)) {
            abort(404);
        }

        $config = self::MOBILE_PROVIDERS[$provider];

        $request->validate([
            'amount' => 'required|numeric|min:1',
            'phone' => 'required|string',
        ]);

        // Normalize to a local 10-digit Zimbabwe number (0XXXXXXXXX)
        $phone = $this->normalizeZwPhone((string) $request->input('phone'));

        if ($phone === null) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a valid Zimbabwean phone number.',
            ], 422);
        }

        // Validate that the number belongs to this provider's network (skip if no prefix restriction)
        if (! empty($config['prefixes'])) {
            $matchesProvider = false;
            foreach ($config['prefixes'] as $prefix) {
                if (str_starts_with($phone, $prefix)) {
                    $matchesProvider = true;
                    break;
                }
            }

            if (! $matchesProvider) {
                $expected = implode(' or ', $config['prefixes']);

                return response()->json([
                    'success' => false,
                    'message' => "{$config['label']} numbers must start with {$expected}.",
                ], 422);
            }
        }

        $user = $request->user();
        $amount = (float) $request->input('amount');

        $transaction = $this->createPendingTransaction($user, $amount, $provider);

        $paynow = $this->getPaynow();
        $payment = $paynow->createPayment((string) $transaction->id, $this->authEmail($user));
        $payment->add('Account Deposit', $amount);

        try {
            $response = $paynow->sendMobile($payment, $phone, $config['method']);

            if ($response->success()) {
                $data = $response->data();

                // Store poll URL; add provider-specific fields to gateway_meta
                $updateFields = ['reference' => $response->pollUrl()];

                if ($provider === 'innbucks') {
                    $authCode = $data['authorizationcode'] ?? '';
                    $authExpires = $data['authorizationexpires'] ?? '';
                    $updateFields['gateway_meta'] = [
                        'authorizationcode' => $authCode,
                        'authorizationexpires' => $authExpires,
                    ];
                    $transaction->update($updateFields);

                    $qrUrl = 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl='.urlencode($authCode);
                    $deepLink = 'com.innbucks.customer://purchase?paymentToken='.$authCode;

                    return response()->json([
                        'success' => true,
                        'flow' => 'innbucks_authcode',
                        'authorization_code' => $authCode,
                        'authorization_expires' => $authExpires,
                        'qr_url' => $qrUrl,
                        'deep_link' => $deepLink,
                        'instructions' => $response->instructions(),
                        'transaction_id' => $transaction->getKey(),
                        'provider' => $provider,
                    ]);
                }

                if ($provider === 'omari') {
                    $otpReference = $data['otpreference'] ?? '';
                    $remoteOtpUrl = $data['remoteotpurl'] ?? '';
                    $updateFields['gateway_meta'] = [
                        'otpreference' => $otpReference,
                        'remoteotpurl' => $remoteOtpUrl,
                    ];
                    $transaction->update($updateFields);

                    return response()->json([
                        'success' => true,
                        'flow' => 'omari_otp',
                        'otp_reference' => $otpReference,
                        'transaction_id' => $transaction->getKey(),
                        'provider' => $provider,
                        'message' => "An OTP has been sent to {$phone}. Please enter it below to complete the payment.",
                    ]);
                }

                // EcoCash / OneMoney / TeleCash — standard USSD PIN push
                $transaction->update($updateFields);

                return response()->json([
                    'success' => true,
                    'flow' => 'ussd_pin',
                    'message' => "Check your phone and enter your {$config['label']} PIN to complete the payment.",
                    'instructions' => $response->instructions(),
                    'transaction_id' => $transaction->getKey(),
                    'provider' => $provider,
                ]);
            }

            $transaction->update(['status' => 'rejected', 'notes' => 'Mobile init failed']);

            return response()->json(['success' => false, 'message' => 'Could not send the payment request. Please try again.'], 400);

        } catch (\Throwable $e) {
            Log::error("Paynow {$config['label']} Init Error", $this->exceptionContext($e, ['provider' => $provider, 'transaction_id' => $transaction->id]));
            $transaction->update(['status' => 'rejected', 'notes' => 'Exception: '.$this->describeException($e)]);

            return response()->json(['success' => false, 'message' => 'Payment gateway error. Please try again later.'], 500);
        }
    }

    /**
     * Normalise an input string to a 10-digit local Zimbabwe number (0XXXXXXXXX).
     * Accepts: 0771234567 / +263771234567 / 263771234567 / 771234567
     * Returns null if the result is not exactly 10 digits.
     */
    private function normalizeZwPhone(string $raw): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', $raw);

        // International with country code: 263XXXXXXXXX (12 digits)
        if (strlen($digits) === 12 && str_starts_with($digits, '263')) {
            $digits = '0'.substr($digits, 3);
        }

        // Without leading zero: XXXXXXXXX (9 digits)
        if (strlen($digits) === 9) {
            $digits = '0'.$digits;
        }

        return (strlen($digits) === 10 && str_starts_with($digits, '0')) ? $digits : null;
    }

    /**
     * Submit the OTP received by the customer for an O'mari express checkout transaction.
     * Route: POST /paynow/omari/otp/{transaction}
     */
    public function submitOmariOtp(Request $request, Transaction $transaction): JsonResponse
    {
        if ($transaction->user_id !== (int) $request->user()->getAuthIdentifier()) {
            abort(403);
        }

        if ($transaction->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'This transaction has already been processed.',
            ], 422);
        }

        $request->validate([
            'otp' => 'required|string|min:4|max:8',
        ]);

        $meta = (array) ($transaction->gateway_meta ?? []);
        $remoteOtpUrl = $meta['remoteotpurl'] ?? null;

        if (empty($remoteOtpUrl)) {
            return response()->json([
                'success' => false,
                'message' => 'OTP submission is not available for this transaction.',
            ], 422);
        }

        $integrationId = (string) config('services.paynow.integration_id');
        $integrationKey = (string) config('services.paynow.integration_key');
        $otp = (string) $request->input('otp');

        // Hash per Paynow spec: SHA-512( id + otp + "Message" + integrationKey ) → uppercase
        $hash = Hash::make([
            'id' => $integrationId,
            'otp' => $otp,
            'status' => 'Message',
        ], $integrationKey);

        try {
            $httpResponse = Http::asForm()->post($remoteOtpUrl, [
                'id' => $integrationId,
                'otp' => $otp,
                'status' => 'Message',
                'hash' => $hash,
            ]);

            parse_str($httpResponse->body(), $params);

            if (isset($params['status']) && strtolower($params['status']) === 'error') {
                $error = $params['error'] ?? 'Invalid OTP';

                return response()->json(['success' => false, 'message' => $error], 422);
            }

            // OTP accepted — Paynow confirms payment via webhook or poll
            return response()->json([
                'success' => true,
                'message' => 'OTP accepted. Processing your payment…',
            ]);

        } catch (\Throwable $e) {
            Log::error("Paynow O'mari OTP error", $this->exceptionContext($e, ['transaction_id' => $transaction->id]));

            return response()->json(['success' => false, 'message' => 'Could not submit OTP. Please try again.'], 500);
        }
    }

    /**
     * Create a pending deposit transaction and write an audit log entry.
     */
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

        // Audit asynchronously — not critical to the request path
        AuditLog::dispatchLog(
            action: 'transaction.paynow_created_pending',
            userId: (int) $user->getAuthIdentifier(),
            modelType: Transaction::class,
            modelId: (int) $transaction->getKey(),
            newValues: ['status' => 'pending', 'method' => $method, 'amount' => $amount],
        );

        return $transaction;
    }

    public function returnUrl(Request $request)
    {
        // Paynow sends: ?reference={our_tx_id}&paynowreference=...&status=Paid|Cancelled|Failed&hash=...
        $txId = $request->query('reference');
        $pnStatus = (string) $request->query('status', '');

        if (! $txId) {
            return redirect()->route('wallet.index')
                ->with('info', 'Payment return received. Your balance will update once confirmed.');
        }

        $transaction = Transaction::find($txId);

        if (! $transaction || $transaction->user_id !== (int) $request->user()?->getAuthIdentifier()) {
            return redirect()->route('wallet.index')
                ->with('warning', 'Could not locate the transaction. Contact support if your balance is not updated.');
        }

        // Already resolved — no need to poll
        if ($transaction->status === 'completed') {
            return redirect()->route('wallet.index')
                ->with('success', 'Your deposit of $'.number_format((float) $transaction->amount, 2).' has been confirmed!');
        }

        if ($transaction->status === 'rejected') {
            return redirect()->route('wallet.index')
                ->with('error', 'Your payment was not successful. No funds were deducted.');
        }

        // Still pending — do a final poll against Paynow before giving up
        $pollUrl = $transaction->reference;
        if (! empty($pollUrl) && str_starts_with($pollUrl, 'http')) {
            try {
                $paynow = $this->getPaynow();
                $remoteStatus = $paynow->pollTransaction($pollUrl);

                if ($remoteStatus && $remoteStatus->paid()) {
                    $this->depositService->credit($transaction, 'return_url_poll');

                    return redirect()->route('wallet.index')
                        ->with('success', 'Your deposit of $'.number_format((float) $transaction->amount, 2).' has been confirmed!');
                }

                if ($remoteStatus && in_array($remoteStatus->status(), ['Cancelled', 'Failed'], true)) {
                    $this->depositService->reject($transaction, 'return_url');

                    return redirect()->route('wallet.index')
                        ->with('error', 'Your payment was cancelled or failed. No funds were deducted.');
                }
            } catch (\Exception $e) {
                Log::warning('Paynow return URL poll error', $this->exceptionContext($e, ['transaction_id' => $transaction->id]));
            }
        }

        // Fallback: Paynow status hint from query string (not hash-verified — informational only)
        if (strtolower($pnStatus) === 'cancelled') {
            return redirect()->route('wallet.index')
                ->with('warning', 'Payment was cancelled. You can try again from your wallet.');
        }

        return redirect()->route('wallet.index')
            ->with('info', 'Your payment is being verified. Your balance will update shortly — refresh in a moment.');
    }

    /**
     * Client-side polling endpoint for mobile money (EcoCash/OneMoney) transactions.
     * The frontend polls this after initiating an express checkout until status
     * changes from 'pending' or a timeout is reached.
     */
    public function pollStatus(Request $request, Transaction $transaction): JsonResponse
    {
        // Ensure the transaction belongs to the authenticated user
        if ($transaction->user_id !== (int) $request->user()->getAuthIdentifier()) {
            abort(403);
        }

        // If already resolved, return immediately (no need to hit Paynow)
        if ($transaction->status !== 'pending') {
            return response()->json([
                'status' => $transaction->status,
                'resolved' => true,
            ]);
        }

        // Only poll Paynow if we have a stored poll URL
        $pollUrl = $transaction->reference;
        if (empty($pollUrl) || ! str_starts_with($pollUrl, 'http')) {
            return response()->json(['status' => 'pending', 'resolved' => false]);
        }

        try {
            $paynow = $this->getPaynow();
            $remoteStatus = $paynow->pollTransaction($pollUrl);

            if ($remoteStatus && $remoteStatus->paid()) {
                // Use DepositService — fixes the missing referral reward bug
                $this->depositService->credit($transaction, 'client_poll');

                return response()->json(['status' => 'completed', 'resolved' => true]);
            }

            if ($remoteStatus && in_array($remoteStatus->status(), ['Cancelled', 'Failed'], true)) {
                $this->depositService->reject($transaction, 'client_poll');

                return response()->json(['status' => 'rejected', 'resolved' => true]);
            }
        } catch (\Throwable $e) {
            Log::warning('Paynow poll error', $this->exceptionContext($e, ['transaction_id' => $transaction->id]));
        }

        return response()->json(['status' => 'pending', 'resolved' => false]);
    }

    public function webhook(Request $request)
    {
        $paynow = $this->getPaynow();
        $status = $paynow->processStatusUpdate();

        if ($status) {
            $transactionId = $status->reference();
            $transaction = Transaction::find($transactionId);

            if (! $transaction || $transaction->status !== 'pending') {
                return response()->json(['status' => 'ignored']);
            }

            if ($status->paid()) {
                $this->depositService->credit($transaction, 'paynow_webhook');
            } elseif (in_array(strtolower((string) $status->status()), ['cancelled', 'failed'], true)) {
                $this->depositService->reject($transaction, 'paynow_webhook');
            }

            return response()->json(['status' => 'ok']);
        }

        return response()->json(['status' => 'error'], 400);
    }
}
