<?php
// app/Http/Controllers/WalletController.php

namespace App\Http\Controllers;

use App\Models\ManualPaymentDetail;
use App\Models\Transaction;
use App\Models\ContractApplication;
use App\Models\ContractProofSubmission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function index(): Response
    {
        /** @var User $user */
        $user = Auth::user();
        $userId = (int) $user->getAuthIdentifier();

        $transactions = Transaction::where('user_id', $userId)
            ->latest()
            ->paginate(20);

        $totals = [
            'deposited' => Transaction::where('user_id', $userId)
                ->where('type', 'deposit')
                ->where('status', 'completed')
                ->sum('amount'),
            'contract_earnings' => Transaction::where('user_id', $userId)
                ->where('type', 'contract_earning')
                ->where('status', 'completed')
                ->sum('amount'),
            'withdrawn' => abs(Transaction::where('user_id', $userId)
                ->where('type', 'withdrawal')
                ->sum('amount')),
            'spent' => abs(Transaction::where('user_id', $userId)
                ->where('type', 'order_charge')
                ->sum('amount')),
        ];

        $manualPaymentDetails = ManualPaymentDetail::active()
            ->ordered()
            ->get();

        $availableMethods = $manualPaymentDetails
            ->pluck('label', 'method_key')
            ->toArray();

        // method_keys that go through the Paynow payment gateway (automatic redirect)
        $gatewayMethods = $manualPaymentDetails
            ->where('gateway_type', 'paynow')
            ->pluck('method_key')
            ->values()
            ->all();

        if (empty($availableMethods)) {
            $availableMethods = [
                'paynow'   => 'Paynow (Online / Card)',
                'ecocash'  => 'EcoCash Express',
                'onemoney' => 'OneMoney Express',
                'crypto'   => 'Crypto (Manual)',
            ];
            $gatewayMethods = ['paynow', 'ecocash', 'onemoney'];
        }

        return Inertia::render('Wallet/Index', [
            'transactions'        => $transactions,
            'totals'              => $totals,
            'manualPaymentDetails'=> $manualPaymentDetails,
            'availableMethods'    => $availableMethods,
            'gatewayMethods'      => $gatewayMethods,
            'pendingProofs'       => $this->getPendingProofCount($userId),
        ]);
    }

    private function getPendingProofCount(int $userId): int
    {
        $role = (string) (User::find($userId)?->getAttribute('role') ?? '');
        if (!in_array($role, ['marketer', 'reseller'], true)) {
            return 0;
        }

        $approvedApps = ContractApplication::where('marketer_id', $userId)
            ->where('status', 'approved')
            ->pluck('id');

        if ($approvedApps->isEmpty()) {
            return 0;
        }

        $approvedProofs = ContractProofSubmission::where('marketer_id', $userId)
            ->where('status', 'approved')
            ->pluck('contract_application_id')
            ->unique();

        return $approvedApps->diff($approvedProofs)->count();
    }

    /**
     * Manual deposit - Create pending transaction awaiting proof of payment.
     * Only for manual payment methods (not Paynow/express).
     */
    public function manualDeposit(Request $request): RedirectResponse
    {
        // Only non-gateway methods are allowed here; gateway methods go through PaynowController
        $manualOnlyMethods = ManualPaymentDetail::active()
            ->whereNull('gateway_type')
            ->pluck('method_key')
            ->unique()
            ->values()
            ->all();

        if (empty($manualOnlyMethods)) {
            $manualOnlyMethods = ['innbucks', 'crypto', 'bank'];
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:10000'],
            'method' => ['required', Rule::in($manualOnlyMethods)],
        ]);

        /** @var User $user */
        $user = Auth::user();
        $userId = (int) $user->getAuthIdentifier();

        $transaction = Transaction::create([
            'user_id'        => $userId,
            'type'           => 'deposit',
            'amount'         => $data['amount'],
            'balance_before' => $user->balance,
            'balance_after'  => $user->balance, // not credited yet — awaiting proof
            'method'         => $data['method'],
            'status'         => 'pending',
            'notes'          => 'Awaiting proof of payment submission',
        ]);

        return back()->with('info', 'Deposit request created. Please upload your proof of payment to complete the transaction.');
    }

    /**
     * Submit proof of payment for a manual deposit.
     * File is validated and stored, transaction is marked for admin review.
     */
    public function submitProof(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $userId = (int) $user->getAuthIdentifier();

        $data = $request->validate([
            'transaction_id' => ['required', 'integer', 'exists:transactions,id'],
            'proof_file' => ['required', 'file', 'image', 'max:5120'], // 5MB max, image only
        ]);

        $transaction = Transaction::where('id', $data['transaction_id'])
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->first();

        if (! $transaction) {
            return back()->with('error', 'Transaction not found or already processed.');
        }

        // Store proof file
        if ($request->hasFile('proof_file')) {
            $file = $request->file('proof_file');
            $fileName = sprintf(
                'proofs/%s/%s-%s.%s',
                $userId,
                $transaction->id,
                time(),
                $file->getClientOriginalExtension()
            );

            $filePath = $file->storeAs(
                'public',
                $fileName,
                'public'
            );

            $transaction->update([
                'proof_url' => '/storage/' . $fileName,
                'notes' => 'Proof of payment submitted. Awaiting admin approval.',
            ]);

            return back()->with('success', 'Proof of payment submitted successfully. An admin will verify and credit your account.');
        }

        return back()->with('error', 'Failed to upload proof file. Please try again.');
    }

    public function withdraw(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $role = (string) $user->getAttribute('role');

        if (!in_array($role, ['marketer', 'reseller'], true)) {
            return back()->with('error', 'Only marketer accounts can request withdrawals.');
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'method' => ['required', 'string', 'max:60'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $amount = (float) $data['amount'];
        $balanceBefore = (float) $user->balance;

        if ($amount > $balanceBefore) {
            return back()->with('error', 'Insufficient balance for this withdrawal request.');
        }

        // Require approved proof for every approved contract application that paid out earnings
        $userId = (int) $user->getAuthIdentifier();

        $approvedApps = ContractApplication::where('marketer_id', $userId)
            ->where('status', 'approved')
            ->pluck('id');

        if ($approvedApps->isNotEmpty()) {
            $approvedProofs = ContractProofSubmission::where('marketer_id', $userId)
                ->where('status', 'approved')
                ->pluck('contract_application_id')
                ->unique();

            $missingProof = $approvedApps->diff($approvedProofs);

            if ($missingProof->isNotEmpty()) {
                return back()->with('error',
                    'You must submit and have approved proof of work (post/reel/video link) for all your approved contracts before withdrawing.'
                );
            }
        }

        $user->decrement('balance', $amount);

        Transaction::create([
            'user_id' => (int) $user->getAuthIdentifier(),
            'type' => 'withdrawal',
            'amount' => -$amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceBefore - $amount,
            'method' => $data['method'],
            'reference' => $data['reference'] ?? null,
            'status' => 'pending',
            'notes' => 'Marketer withdrawal request',
        ]);

        return back()->with('success', 'Withdrawal request submitted. Funds have been reserved.');
    }

    /**
     * Webhook called by payment gateway after successful payment.
     * This should be called by your payment provider (e.g. PayPal IPN, EcoCash callback).
     */
    public function handleWebhook(Request $request): \Illuminate\Http\JsonResponse
    {
        // TODO: Verify webhook signature for your payment provider
        $reference = $request->input('reference');
        $amount    = (float) $request->input('amount');

        $transaction = Transaction::where('reference', $reference)
            ->where('status', 'pending')
            ->first();

        if (! $transaction) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        $user = $transaction->user;
        $user->increment('balance', $transaction->amount);

        $transaction->update([
            'status'       => 'completed',
            'balance_after'=> $user->fresh()->balance,
        ]);

        return response()->json(['success' => true]);
    }
}
