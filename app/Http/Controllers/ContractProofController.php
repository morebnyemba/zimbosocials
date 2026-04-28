<?php

namespace App\Http\Controllers;

use App\Models\ContractApplication;
use App\Models\ContractProofSubmission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        if ($decision === 'approved' && $proof->status === 'pending') {
            try {
                \Illuminate\Support\Facades\DB::transaction(function () use ($proof, $application, $contract, $userId): void {
                    $budget = (float) ($contract->budget ?? 0);
                    $marketer = $application->marketer;

                    // Release funds to marketer
                    if ($budget > 0) {
                        $marketerBefore = (float) $marketer->balance;
                        $marketer->increment('balance', $budget);

                        \App\Models\Transaction::create([
                            'user_id' => (int) $marketer->id,
                            'type' => 'contract_earning',
                            'amount' => $budget,
                            'balance_before' => $marketerBefore,
                            'balance_after' => $marketerBefore + $budget,
                            'status' => 'completed',
                            'notes' => 'Released escrow for contract #' . $contract->id . ' proof approval.',
                        ]);
                    }

                    $proof->update([
                        'status'      => 'approved',
                        'reviewed_by' => $userId,
                        'reviewed_at' => now(),
                    ]);

                    $application->update(['status' => 'completed']);
                });
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
