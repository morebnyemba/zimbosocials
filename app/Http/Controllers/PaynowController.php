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
    public function __construct(
        private readonly Application $app,
        private readonly DepositService $depositService,
        private readonly \App\Services\Paynow\PaynowMobileService $mobile,
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

        // Paynow SDK v1.0.5 constructor is (id, key, returnUrl, resultUrl):
        //   returnUrl (3rd) = browser redirect back after payment  -> paynow.return (GET)
        //   resultUrl (4th) = server-to-server status update (IPN)  -> paynow.update / webhook (POST)
        return new Paynow(
            config('services.paynow.integration_id'),
            config('services.paynow.integration_key'),
            route('paynow.return'),
            route('paynow.update')
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

            $this->depositService->reject($transaction, 'paynow_init_failed', 'Failed to initiate Paynow transaction.');

            return response()->json(['success' => false, 'message' => 'Failed to initiate Paynow transaction.'], 400);

        } catch (\Throwable $e) {
            Log::error('Paynow Init Error', $this->exceptionContext($e, ['method' => 'paynow', 'transaction_id' => $transaction->id]));
            $this->depositService->reject($transaction, 'paynow_init_exception', 'Payment gateway error: '.$this->describeException($e));

            return response()->json(['success' => false, 'message' => 'Payment gateway error: '.$this->describeException($e)], 500);
        }
    }

    /**
     * Initiate an express checkout for a specific mobile money provider.
     * Route: POST /paynow/mobile/{provider}  (ecocash|onemoney|innbucks|omari)
     */
    public function initMobile(Request $request, string $provider)
    {
        if (! PaynowMobileService::isProvider($provider)) {
            abort(404);
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:1',
            'phone' => 'required|string',
        ]);

        // Single shared implementation (also used by the WhatsApp assistant).
        $res = $this->mobile->initiate($request->user(), $provider, (string) $data['phone'], (float) $data['amount']);

        if (empty($res['ok'])) {
            $status = match ($res['error'] ?? '') {
                'invalid_phone', 'wrong_network', 'invalid_amount', 'unknown_provider', 'paynow_unconfigured' => 422,
                'init_failed' => 400,
                default => 500,
            };

            return response()->json(['success' => false, 'message' => $res['message'] ?? 'Payment failed.'], $status);
        }

        return match ($res['flow']) {
            'innbucks_authcode' => response()->json([
                'success' => true,
                'flow' => 'innbucks_authcode',
                'authorization_code' => $res['authorization_code'],
                'authorization_expires' => $res['authorization_expires'] ?? '',
                'qr_url' => $res['qr_url'],
                'deep_link' => $res['deep_link'],
                'instructions' => $res['instructions'],
                'transaction_id' => $res['transaction_id'],
                'provider' => $provider,
            ]),
            'omari_otp' => response()->json([
                'success' => true,
                'flow' => 'omari_otp',
                'otp_reference' => $res['otp_reference'],
                'transaction_id' => $res['transaction_id'],
                'provider' => $provider,
                'message' => $res['message'],
            ]),
            default => response()->json([
                'success' => true,
                'flow' => 'ussd_pin',
                'message' => $res['message'],
                'instructions' => $res['instructions'] ?? null,
                'transaction_id' => $res['transaction_id'],
                'provider' => $provider,
            ]),
        };
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

        // Shared implementation (also used by the WhatsApp assistant).
        $res = $this->mobile->submitOtp($transaction, (string) $request->input('otp'));

        return response()->json(['success' => $res['ok'], 'message' => $res['message']], $res['ok'] ? 200 : 422);
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

                if ($remoteStatus && in_array($remoteStatus->status(), ['cancelled', 'failed'], true)) {
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

            if ($remoteStatus && in_array($remoteStatus->status(), ['cancelled', 'failed'], true)) {
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
