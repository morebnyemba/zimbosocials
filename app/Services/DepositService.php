<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Single source of truth for crediting a deposit.
 *
 * Every code path that resolves a pending deposit (webhook, return-URL poll,
 * client-side poll, admin approval) MUST call DepositService::credit() instead
 * of duplicating the lock → update → increment → notify → referral chain.
 */
class DepositService
{
    public function __construct(
        private readonly ReferralService $referralService,
    ) {}

    /**
     * Atomically credit a pending deposit transaction.
     *
     * @param  Transaction  $transaction  The pending deposit transaction.
     * @param  string  $source  Descriptive tag for the audit trail (e.g. 'webhook', 'poll', 'admin').
     * @return bool True if credited, false if already resolved or not found.
     */
    public function credit(Transaction $transaction, string $source = 'system'): bool
    {
        if ($transaction->status !== 'pending') {
            return false;
        }

        $credited = false;

        DB::transaction(function () use ($transaction, $source, &$credited): void {
            $locked = Transaction::lockForUpdate()->find($transaction->getKey());

            if (! $locked || $locked->status !== 'pending') {
                return;
            }

            $oldStatus = (string) $locked->getAttribute('status');
            $amount = (float) $locked->amount;

            $locked->update([
                'status' => 'completed',
                'notes' => "Completed via {$source}",
            ]);

            $user = User::lockForUpdate()->find($locked->user_id);
            if (! $user) {
                Log::error("DepositService: User {$locked->user_id} not found for transaction {$locked->getKey()}");

                return;
            }

            $user->increment('balance', $amount);

            // Update balance_after to reflect the actual new balance
            $locked->update([
                'balance_after' => (float) $user->balance,
            ]);

            AuditLog::log(
                action: 'transaction.deposit_credited',
                userId: null,
                modelType: Transaction::class,
                modelId: (int) $locked->getKey(),
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => 'completed', 'source' => $source],
            );

            NotificationService::notify(
                $user->id,
                'deposit_confirmed',
                'Deposit Confirmed',
                "Your deposit of \${$amount} has been confirmed.",
                ['amount' => "\${$amount}"]
            );

            // Referral rewards on first deposit:
            //  - flat reward to the referrer
            //  - percentage welcome bonus (default 10%) to the referred user
            $this->referralService->rewardReferrerOnFirstDeposit($locked->fresh());
            $this->referralService->creditReferredUserWelcomeBonus($locked->fresh());

            $credited = true;
        });

        return $credited;
    }

    /**
     * Reject a pending deposit transaction. Notifies the user — every
     * rejection path (gateway failure, admin manual reject) should tell the
     * user their deposit didn't go through, not just credited ones.
     */
    public function reject(Transaction $transaction, string $source = 'system', ?string $notes = null): bool
    {
        if ($transaction->status !== 'pending') {
            return false;
        }

        $oldStatus = (string) $transaction->getAttribute('status');

        $transaction->update([
            'status' => 'rejected',
            'notes' => $notes ?? "Rejected via {$source}",
        ]);

        AuditLog::log(
            action: 'transaction.deposit_rejected',
            userId: null,
            modelType: Transaction::class,
            modelId: (int) $transaction->getKey(),
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'rejected', 'source' => $source],
        );

        NotificationService::notify(
            (int) $transaction->user_id,
            'deposit_rejected',
            'Deposit Rejected',
            'Your deposit request has been rejected. No funds were deducted.',
            ['transaction_id' => $transaction->id]
        );

        return true;
    }
}
