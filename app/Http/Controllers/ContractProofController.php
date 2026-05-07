<?php

namespace App\Http\Controllers;

use App\Models\ContractApplication;
use App\Models\ContractProofSubmission;
use App\Models\Transaction;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ContractProofController extends Controller
{
    /**
     * Marketer submits proof of work for an approved contract application.
     */
    public function store(ContractApplication $application, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $userId = (int) $user->getAuthIdentifier();
        $marketerIdOnApp = (int) $application->getAttribute('marketer_id');
        $appStatus = (string) $application->getAttribute('status');

        if ($marketerIdOnApp !== $userId) {
            abort(403);
        }

        if ($appStatus !== 'approved') {
            return back()->with('error', 'You can only submit proof for approved contracts.');
        }

        $data = $request->validate([
            'proof_url' => ['required', 'url', 'max:1000'],
            'notes'     => ['nullable', 'string', 'max:1000'],
        ]);

        ContractProofSubmission::updateOrCreate(
            [
                'contract_application_id' => (int) $application->getKey(),
                'marketer_id'             => $userId,
            ],
            [
                'proof_url'   => $data['proof_url'],
                'notes'       => $data['notes'] ?? null,
                'status'      => 'pending',
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]
        );

        return back()->with('success', 'Proof submitted. Pending admin review before withdrawal is unlocked.');
    }

    /**
     * Business owner or Admin reviews (approves or rejects) a proof submission.
     *
     * Uses DB transaction + lockForUpdate as an idempotency guard:
     * re-verifying the proof status inside the lock prevents double-payouts
     * from concurrent requests (e.g. double-click).
     */
    public function review(ContractProofSubmission $proof, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $userId = (int) $user->getAuthIdentifier();

        $application = $proof->application;
        $contract = $application->contract;
        $contractOwnerId = (int) $contract->user_id;

        // Only Admin or Contract Owner can review
        if (!$user->isAdmin() && $contractOwnerId !== $userId) {
            abort(403);
        }

        $decision = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
        ])['decision'];

        if ($decision === 'approved') {
            try {
                DB::transaction(function () use ($proof, $application, $contract, $userId): void {
                    // Re-fetch with lock to prevent double-payout
                    $lockedProof = ContractProofSubmission::lockForUpdate()->findOrFail($proof->getKey());

                    if ($lockedProof->status !== 'pending') {
                        throw new \RuntimeException('This proof has already been reviewed.');
                    }

                    $budget = (float) ($contract->budget ?? 0);
                    $marketer = $application->marketer;

                    // Release funds to marketer
                    if ($budget > 0 && $marketer) {
                        $lockedMarketer = User::lockForUpdate()->findOrFail($marketer->id);
                        $marketerBefore = (float) $lockedMarketer->balance;
                        $lockedMarketer->increment('balance', $budget);

                        Transaction::create([
                            'user_id' => (int) $lockedMarketer->id,
                            'type' => 'contract_earning',
                            'amount' => $budget,
                            'balance_before' => $marketerBefore,
                            'balance_after' => $marketerBefore + $budget,
                            'status' => 'completed',
                            'notes' => 'Released escrow for contract #' . $contract->id . ' proof approval.',
                        ]);

                        // Notify marketer of payout
                        NotificationService::notify(
                            (int) $lockedMarketer->id,
                            'withdrawal_processed',
                            'Contract Payout',
                            "You've been paid \${$budget} for completing contract: {$contract->title}.",
                            ['amount' => "\${$budget}"]
                        );
                    }

                    $lockedProof->update([
                        'status'      => 'approved',
                        'reviewed_by' => $userId,
                        'reviewed_at' => now(),
                    ]);

                    $application->update(['status' => 'completed']);
                });
            } catch (\RuntimeException $e) {
                return back()->with('error', $e->getMessage());
            } catch (\Exception $e) {
                return back()->with('error', 'Failed to process payout: ' . $e->getMessage());
            }
        } else {
            $proof->update([
                'status'      => $decision,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
            ]);
        }

        $outcome = $decision === 'approved' ? 'approved — funds released to marketer' : 'rejected';
        return back()->with('success', "Proof {$outcome}.");
    }
}
