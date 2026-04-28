<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ManualPaymentDetail;
use App\Models\Transaction;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Paynow\Payments\Paynow;

class PaynowController extends Controller
{
    private function getPaynow(): Paynow
    {
        return new Paynow(
            config('services.paynow.integration_id'),
            config('services.paynow.integration_key'),
            route('paynow.return'),
            route('paynow.update') // Webhook URL
        );
    }

    public function init(Request $request)
    {
        // Allowed gateway methods come from admin-configured ManualPaymentDetail with gateway_type='paynow'
        $gatewayMethods = ManualPaymentDetail::active()
            ->where('gateway_type', 'paynow')
            ->pluck('method_key')
            ->all();

        // Fallback if nothing configured yet
        if (empty($gatewayMethods)) {
            $gatewayMethods = ['paynow', 'ecocash', 'onemoney'];
        }

        $request->validate([
            'amount' => 'required|numeric|min:1',
            'phone'  => 'nullable|string',
            'method' => ['required', \Illuminate\Validation\Rule::in($gatewayMethods)],
        ]);

        $user = $request->user();
        $amount = (float) $request->amount;
        
        // Create pending transaction
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type'    => 'deposit',
            'amount'  => $amount,
            'status'  => 'pending',
            'notes'   => 'Initiated via Paynow',
            'payment_method_id' => null, // Dynamic payment
        ]);

        AuditLog::log(
            action: 'transaction.paynow_created_pending',
            userId: (int) $user->getAuthIdentifier(),
            modelType: Transaction::class,
            modelId: (int) $transaction->getKey(),
            newValues: [
                'status' => 'pending',
                'method' => (string) $request->input('method'),
                'amount' => $amount,
            ],
        );

        $paynow = $this->getPaynow();
        $payment = $paynow->createPayment((string) $transaction->id, $user->email);
        $payment->add('Account Deposit', $amount);

        try {
            if ($request->method === 'paynow') {
                // Redirect user to Paynow website
                $response = $paynow->send($payment);
                
                if ($response->success()) {
                    $transaction->update(['reference_id' => $response->pollUrl()]);
                    return response()->json([
                        'success' => true,
                        'redirect_url' => $response->redirectUrl()
                    ]);
                }
            } else {
                // Express Checkout (Mobile Money)
                $phone = preg_replace('/[^0-9]/', '', $request->phone);
                $response = $paynow->sendMobile($payment, $phone, $request->method);
                
                if ($response->success()) {
                    $transaction->update(['reference_id' => $response->pollUrl()]);
                    return response()->json([
                        'success' => true,
                        'message' => 'Check your phone to enter your PIN.',
                        'poll_url' => $response->pollUrl()
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate Paynow transaction.'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Paynow Init Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Payment gateway error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function returnUrl(Request $request)
    {
        // User is redirected back here from Paynow website
        return redirect()->route('wallet.index')->with('info', 'Your payment is being processed. It will reflect in your balance shortly.');
    }

    public function webhook(Request $request, ReferralService $referralService)
    {
        $paynow = $this->getPaynow();
        $status = $paynow->processStatusUpdate();

        if ($status) {
            $transactionId = $status->reference();
            $transaction = Transaction::find($transactionId);

            if (!$transaction || $transaction->status !== 'pending') {
                return response()->json(['status' => 'ignored']);
            }

            if ($status->paid()) {
                $oldStatus = (string) $transaction->getAttribute('status');
                $transaction->update([
                    'status' => 'completed',
                    'notes' => 'Completed via Paynow'
                ]);

                $referralService->rewardReferrerOnFirstDeposit($transaction->fresh());

                AuditLog::log(
                    action: 'transaction.paynow_status_updated',
                    userId: null,
                    modelType: Transaction::class,
                    modelId: (int) $transaction->getKey(),
                    oldValues: ['status' => $oldStatus],
                    newValues: ['status' => 'completed'],
                );

                $user = User::find($transaction->user_id);
                if ($user) {
                    $user->increment('balance', $transaction->amount);
                    NotificationService::notify(
                        $user->id,
                        'deposit_confirmed',
                        'Deposit Confirmed',
                        "Your deposit of \${$transaction->amount} via Paynow was successful.",
                        ['amount' => "\${$transaction->amount}"]
                    );
                }
            } elseif ($status->status() === 'Cancelled' || $status->status() === 'Failed') {
                $oldStatus = (string) $transaction->getAttribute('status');
                $transaction->update([
                    'status' => 'rejected',
                    'notes' => 'Failed/Cancelled via Paynow'
                ]);

                AuditLog::log(
                    action: 'transaction.paynow_status_updated',
                    userId: null,
                    modelType: Transaction::class,
                    modelId: (int) $transaction->getKey(),
                    oldValues: ['status' => $oldStatus],
                    newValues: [
                        'status' => 'rejected',
                        'provider_status' => (string) $status->status(),
                    ],
                );
            }

            return response()->json(['status' => 'ok']);
        }

        return response()->json(['status' => 'error'], 400);
    }
}
