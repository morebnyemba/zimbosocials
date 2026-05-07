<?php
// app/Http/Controllers/WalletController.php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ManualPaymentDetail;
use App\Models\Transaction;
use App\Models\ContractApplication;
use App\Models\ContractProofSubmission;
use App\Models\User;
use App\Services\DepositService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function __construct(
        private readonly DepositService $depositService,
    ) {}

    public function index(): Response
    {
        /** @var User $user */
        $user = Auth::user();
        $userId = (int) $user->getAuthIdentifier();

        $transactions = Transaction::where('user_id', $userId)
            ->latest()
            ->paginate(20);

        // Single aggregate query instead of 4 separate SUM queries
        $totalsRow = Transaction::where('user_id', $userId)
            ->selectRaw("
                SUM(CASE WHEN type = 'deposit' AND status = 'completed' THEN amount ELSE 0 END)          AS deposited,
                SUM(CASE WHEN type = 'contract_earning' AND status = 'completed' THEN amount ELSE 0 END) AS contract_earnings,
                ABS(SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END))                           AS withdrawn,
                ABS(SUM(CASE WHEN type = 'order_charge' THEN amount ELSE 0 END))                         AS spent
            ")
            ->first();

        $totals = [
            'deposited'         => (float) ($totalsRow->deposited         ?? 0),
            'contract_earnings' => (float) ($totalsRow->contract_earnings ?? 0),
            'withdrawn'         => (float) ($totalsRow->withdrawn         ?? 0),
            'spent'             => (float) ($totalsRow->spent             ?? 0),
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
        $role = (string) (Auth::user()?->getAttribute('role') ?? '');
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

        AuditLog::dispatchLog(
            action: 'transaction.deposit_created_pending',
            userId: $userId,
            modelType: Transaction::class,
            modelId: (int) $transaction->getKey(),
            newValues: [
                'status' => 'pending',
                'method' => (string) $transaction->getAttribute('method'),
                'amount' => (float) $transaction->amount,
            ],
        );

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
            'proof_file' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
                'dimensions:min_width=1,min_height=1,max_width=8000,max_height=8000',
            ],
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
            $storedPath = $file->store(
                'proofs/' . $userId,
                'public'
            );

            $oldProofUrl = $transaction->proof_url;

            $transaction->update([
                'proof_url' => '/storage/' . $storedPath,
                'notes' => 'Proof of payment submitted. Awaiting admin approval.',
            ]);

            AuditLog::dispatchLog(
                action: 'transaction.deposit_proof_submitted',
                userId: $userId,
                modelType: Transaction::class,
                modelId: (int) $transaction->getKey(),
                oldValues: [
                    'proof_url' => $oldProofUrl,
                ],
                newValues: [
                    'proof_url' => (string) $transaction->getAttribute('proof_url'),
                ],
            );

            return back()->with('success', 'Proof of payment submitted successfully. An admin will verify and credit your account.');
        }

        return back()->with('error', 'Failed to upload proof file. Please try again.');
    }

    /**
     * Request a withdrawal. Wrapped in a DB transaction with pessimistic locking
     * to prevent race conditions from concurrent requests.
     */
    public function withdraw(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $role = (string) $user->getAttribute('role');

        if (!in_array($role, ['marketer', 'reseller'], true)) {
            return back()->with('error', 'Only marketer accounts can request withdrawals.');
        }

        $data = $request->validate([
            'amount'    => ['required', 'numeric', 'min:1'],
            'method'    => ['required', 'string', 'max:60'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $amount = (float) $data['amount'];
        $userId = (int) $user->getAuthIdentifier();

        // Require approved proof for every approved contract application
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

        // Atomic: lock user row → re-check balance → decrement → create transaction
        try {
            DB::transaction(function () use ($userId, $amount, $data): void {
                $lockedUser = User::lockForUpdate()->findOrFail($userId);
                $balanceBefore = (float) $lockedUser->balance;

                if ($amount > $balanceBefore) {
                    throw new \RuntimeException('Insufficient balance for this withdrawal request.');
                }

                $lockedUser->decrement('balance', $amount);

                Transaction::create([
                    'user_id'        => $userId,
                    'type'           => 'withdrawal',
                    'amount'         => -$amount,
                    'balance_before' => $balanceBefore,
                    'balance_after'  => $balanceBefore - $amount,
                    'method'         => $data['method'],
                    'reference'      => $data['reference'] ?? null,
                    'status'         => 'pending',
                    'notes'          => 'Marketer withdrawal request',
                ]);
            });

            return back()->with('success', 'Withdrawal request submitted. Funds have been reserved.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Webhook called by payment gateway after successful payment.
     * Uses DepositService for atomic crediting with proper locking.
     */
    public function handleWebhook(Request $request): \Illuminate\Http\JsonResponse
    {
        // TODO: Verify webhook signature for your payment provider
        $reference = $request->input('reference');

        $transaction = Transaction::where('reference', $reference)
            ->where('status', 'pending')
            ->first();

        if (! $transaction) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        $this->depositService->credit($transaction, 'external_webhook');

        return response()->json(['success' => true]);
    }
}
