<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ManualPaymentDetail;
use App\Models\Setting;
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

            // Instant bonus on manual-method deposits (default 5%, every
            // deposit). Stacks with the referred-user welcome bonus below, so
            // a referred user's first manual deposit effectively earns 15%.
            $this->creditManualDepositBonus($locked, $user);

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
     * Bonus percentage credited on every manual (non-gateway) deposit.
     * Configurable via the `manual_deposit_bonus_percent` setting.
     */
    public function manualDepositBonusPercent(): float
    {
        return (float) Setting::get('manual_deposit_bonus_percent', 5.00);
    }

    /**
     * Whether a deposit method is a manual (non-gateway) method. The
     * configured ManualPaymentDetail row is authoritative; when no row exists
     * for the method, Paynow gateway method keys are never manual.
     */
    public function isManualMethod(?string $method): bool
    {
        if ($method === null || $method === '') {
            return false;
        }

        $detail = ManualPaymentDetail::where('method_key', $method)->first();
        if ($detail) {
            return $detail->gateway_type === null;
        }

        return ! in_array($method, ['paynow', 'ecocash', 'onemoney', 'innbucks', 'omari'], true);
    }

    /**
     * Credit the manual-deposit bonus for a just-completed deposit. Must be
     * called inside the credit() transaction with the user row already locked.
     * Idempotent via the transaction reference, so a webhook/poll/admin race
     * can never double-pay the bonus.
     */
    private function creditManualDepositBonus(Transaction $deposit, User $user): void
    {
        if (! $this->isManualMethod($deposit->getAttribute('method'))) {
            return;
        }

        $percent = $this->manualDepositBonusPercent();
        if ($percent <= 0) {
            return;
        }

        $bonus = round(((float) $deposit->amount * $percent) / 100, 2);
        if ($bonus <= 0) {
            return;
        }

        $reference = 'DEPOSIT-BONUS-'.$deposit->getKey();

        $already = Transaction::query()
            ->where('user_id', $user->getKey())
            ->where('type', 'bonus')
            ->where('reference', $reference)
            ->exists();

        if ($already) {
            return;
        }

        $bonusTx = $user->creditBalance($bonus, 'deposit_bonus', $reference, 'bonus');

        AuditLog::log(
            action: 'transaction.manual_deposit_bonus_awarded',
            userId: null,
            modelType: Transaction::class,
            modelId: (int) $bonusTx->getKey(),
            newValues: [
                'source_deposit_id' => (int) $deposit->getKey(),
                'bonus_percent' => $percent,
                'deposit_amount' => (float) $deposit->amount,
                'bonus_amount' => $bonus,
            ],
        );

        NotificationService::notify(
            (int) $user->getKey(),
            'deposit_bonus',
            'Deposit Bonus Credited',
            "You earned a {$percent}% bonus (\${$bonus}) on your deposit.",
            ['amount' => "\${$bonus}", 'percent' => $percent]
        );
    }

    /**
     * Expire a stale pending deposit that was never paid. Same audit/notify
     * treatment as reject(), but lands on 'expired' so it is distinguishable
     * from an explicit gateway/admin rejection.
     */
    public function expire(Transaction $transaction, string $source = 'system', ?string $notes = null): bool
    {
        if ($transaction->status !== 'pending') {
            return false;
        }

        $oldStatus = (string) $transaction->getAttribute('status');

        $transaction->update([
            'status' => 'expired',
            'notes' => $notes ?? "Expired via {$source}",
        ]);

        AuditLog::log(
            action: 'transaction.deposit_expired',
            userId: null,
            modelType: Transaction::class,
            modelId: (int) $transaction->getKey(),
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'expired', 'source' => $source],
        );

        NotificationService::notify(
            (int) $transaction->user_id,
            'deposit_rejected',
            'Deposit Expired',
            'Your deposit request expired because no payment was received. No funds were deducted — you can start a new deposit any time.',
            ['transaction_id' => $transaction->id]
        );

        return true;
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
