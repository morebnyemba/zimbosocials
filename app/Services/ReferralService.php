<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReferralService
{
    private function firstDepositRewardAmount(): float
    {
        return (float) Setting::get('first_deposit_reward', config('services.referral.first_deposit_reward', 1.00));
    }

    private function orderCommissionPercent(): float
    {
        return (float) Setting::get('order_commission_percent', config('services.referral.order_commission_percent', 2.00));
    }

    private function orderCommissionMinimumTotal(): float
    {
        return (float) Setting::get('order_commission_min_total', config('services.referral.order_commission_min_total', 20.00));
    }

    /**
     * Maximum number of order commissions to award per referral relationship.
     * 0 = unlimited (default, backward-compatible).
     */
    private function maxReferralCommissionOrders(): int
    {
        return (int) Setting::get('max_referral_commission_orders', 0);
    }

    /**
     * Percentage bonus credited to a *referred* user on their first deposit
     * (default 10%). Configurable via the `referred_first_deposit_bonus_percent`
     * setting.
     */
    public function referredFirstDepositBonusPercent(): float
    {
        return (float) Setting::get(
            'referred_first_deposit_bonus_percent',
            config('services.referral.referred_first_deposit_bonus_percent', 10.00)
        );
    }

    /**
     * Minimum deposit (USD) a referred user must make for first-deposit referral
     * rewards (referrer reward + welcome bonus) to apply. Prevents farming the
     * flat reward with tiny deposits. Default $5.
     */
    public function minQualifyingDeposit(): float
    {
        return (float) Setting::get(
            'referral_min_qualifying_deposit',
            config('services.referral.min_qualifying_deposit', 5.00)
        );
    }

    /**
     * Credit the referred user a percentage bonus (default 10%) on their first
     * completed deposit. Distinct from the referrer's flat reward. Idempotent
     * via the transaction reference, so retried webhooks/polls never double-pay.
     */
    public function creditReferredUserWelcomeBonus(Transaction $depositTransaction): void
    {
        if ($depositTransaction->getAttribute('type') !== 'deposit') {
            return;
        }
        if ($depositTransaction->getAttribute('status') !== 'completed') {
            return;
        }

        $referredUser = User::find($depositTransaction->getAttribute('user_id'));
        if (! $referredUser || ! $referredUser->getAttribute('referred_by')) {
            return;
        }

        $percent = $this->referredFirstDepositBonusPercent();
        if ($percent <= 0) {
            return;
        }

        // Must meet the minimum qualifying deposit (same threshold as the
        // referrer reward). One-time per user is enforced by the reference below.
        $amount = (float) $depositTransaction->getAttribute('amount');
        if ($amount < $this->minQualifyingDeposit()) {
            return;
        }

        $bonus = round(($amount * $percent) / 100, 2);
        if ($bonus <= 0) {
            return;
        }

        $reference = 'REF-WELCOME-'.$referredUser->getKey();

        DB::transaction(function () use ($referredUser, $reference, $bonus, $percent, $amount, $depositTransaction): void {
            $fresh = User::lockForUpdate()->find($referredUser->getKey());
            if (! $fresh) {
                return;
            }

            $already = Transaction::query()
                ->where('user_id', $fresh->getKey())
                ->where('type', 'bonus')
                ->where('reference', $reference)
                ->exists();

            if ($already) {
                return;
            }

            $bonusTx = $fresh->creditBalance($bonus, 'referral_welcome', $reference, 'bonus');

            AuditLog::log(
                action: 'referral.referred_welcome_bonus_awarded',
                userId: null,
                modelType: Transaction::class,
                modelId: (int) $bonusTx->getKey(),
                newValues: [
                    'referred_user_id' => (int) $fresh->getKey(),
                    'source_deposit_id' => (int) $depositTransaction->getKey(),
                    'bonus_percent' => $percent,
                    'deposit_amount' => $amount,
                    'bonus_amount' => $bonus,
                ]
            );
        });
    }

    /**
     * Days a referrer keeps earning ongoing commissions after their most recent
     * referral. 0 = never expires.
     */
    public function commissionActiveDays(): int
    {
        return (int) Setting::get('referral_commission_active_days', config('services.referral.commission_active_days', 60));
    }

    /** Days before the commission window lapses that the referrer is warned. */
    public function commissionWarnDays(): int
    {
        return (int) Setting::get('referral_commission_warn_days', config('services.referral.commission_warn_days', 7));
    }

    /**
     * When a referrer's ongoing commissions stay active until, based on their
     * most recent referral. Null if the feature is disabled or they've never
     * referred anyone.
     */
    public function commissionActiveUntil(int $referrerId): ?\Illuminate\Support\Carbon
    {
        if ($this->commissionActiveDays() <= 0) {
            return null;
        }

        $latest = User::where('referred_by', $referrerId)->max('created_at');

        return $latest ? \Illuminate\Support\Carbon::parse($latest)->addDays($this->commissionActiveDays()) : null;
    }

    /**
     * Whether a referrer is currently eligible for ongoing order commissions
     * (i.e. has referred someone within the active window).
     */
    public function isReferrerCommissionActive(int $referrerId): bool
    {
        if ($this->commissionActiveDays() <= 0) {
            return true;
        }

        $until = $this->commissionActiveUntil($referrerId);

        return $until !== null && $until->isFuture();
    }

    /**
     * Months after a referred user joins before that referral permanently stops
     * generating commissions (0 = never expires). Distinct from the reversible
     * activity pause — once past this, no activity brings the referral back.
     */
    public function referralLifetimeMonths(): int
    {
        return (int) Setting::get('referral_lifetime_months', config('services.referral.lifetime_months', 36));
    }

    /** When a specific referral permanently expires (null if disabled). */
    public function referralExpiresAt(User $referredUser): ?\Illuminate\Support\Carbon
    {
        $months = $this->referralLifetimeMonths();
        $joined = $referredUser->getAttribute('created_at');

        return ($months > 0 && $joined) ? \Illuminate\Support\Carbon::parse($joined)->addMonths($months) : null;
    }

    /** Whether a referral is still within its permanent lifetime window. */
    public function isReferralWithinLifetime(User $referredUser): bool
    {
        $expiresAt = $this->referralExpiresAt($referredUser);

        return $expiresAt === null || $expiresAt->isFuture();
    }

    /**
     * Current program rates for display on the referral page (kept in sync with
     * admin settings so the "How it works" panel never drifts from the payouts).
     */
    public function programRates(): array
    {
        return [
            'first_deposit_reward' => $this->firstDepositRewardAmount(),
            'welcome_bonus_percent' => $this->referredFirstDepositBonusPercent(),
            'order_commission_percent' => $this->orderCommissionPercent(),
            'order_commission_min_total' => $this->orderCommissionMinimumTotal(),
            'min_qualifying_deposit' => $this->minQualifyingDeposit(),
            'lifetime_months' => $this->referralLifetimeMonths(),
        ];
    }

    public function rewardReferrerOnFirstDeposit(Transaction $depositTransaction): void
    {
        if ($depositTransaction->getAttribute('type') !== 'deposit') {
            return;
        }

        if ($depositTransaction->getAttribute('status') !== 'completed') {
            return;
        }

        $referredUser = User::find($depositTransaction->getAttribute('user_id'));
        if (! $referredUser) {
            return;
        }

        $referrerId = $referredUser->getAttribute('referred_by');
        if (! $referrerId) {
            return;
        }

        // One-time per referred user (the awarded-at flag is the source of truth).
        if ($referredUser->getAttribute('referred_bonus_awarded_at')) {
            return;
        }

        // Must meet the minimum qualifying deposit. A below-minimum deposit does
        // NOT disqualify the user — a later qualifying deposit still triggers it.
        if ((float) $depositTransaction->getAttribute('amount') < $this->minQualifyingDeposit()) {
            return;
        }

        $reward = $this->firstDepositRewardAmount();
        if ($reward <= 0) {
            return;
        }

        DB::transaction(function () use ($referredUser, $referrerId, $reward, $depositTransaction): void {
            $freshReferredUser = User::lockForUpdate()->find($referredUser->getKey());

            if (! $freshReferredUser || $freshReferredUser->getAttribute('referred_bonus_awarded_at')) {
                return;
            }

            $referrer = User::find($referrerId);
            if (! $referrer) {
                return;
            }

            $bonusTx = $referrer->creditBalance(
                $reward,
                'referral',
                'REF-FIRST-DEPOSIT-'.$freshReferredUser->getKey(),
                'bonus'
            );

            $freshReferredUser->update([
                'referred_bonus_awarded_at' => now(),
            ]);

            AuditLog::log(
                action: 'referral.first_deposit_reward_awarded',
                userId: null,
                modelType: Transaction::class,
                modelId: (int) $bonusTx->getKey(),
                newValues: [
                    'referred_user_id' => (int) $freshReferredUser->getKey(),
                    'referrer_id' => (int) $referrer->getKey(),
                    'source_deposit_id' => (int) $depositTransaction->getKey(),
                    'reward_amount' => $reward,
                ]
            );
        });
    }

    /**
     * Reverse the order commission when a commissioned order is refunded.
     * Commission pays on completion, but an admin can still refund a
     * completed order (or a force sync can pull it back to cancelled) — the
     * referrer must not keep a cut of money that went back to the customer.
     * Idempotent via the clawback reference.
     */
    public function clawbackOrderCommission(Order $order): void
    {
        $reference = 'REF-ORDER-'.$order->getKey();
        $clawbackReference = 'REF-ORDER-CLAWBACK-'.$order->getKey();

        $commission = Transaction::query()
            ->where('type', 'bonus')
            ->where('reference', $reference)
            ->where('status', 'completed')
            ->first();

        if (! $commission) {
            return; // order never generated a commission
        }

        DB::transaction(function () use ($commission, $clawbackReference, $order): void {
            $referrer = User::lockForUpdate()->find($commission->user_id);
            if (! $referrer) {
                return;
            }

            $already = Transaction::query()
                ->where('user_id', $referrer->getKey())
                ->where('reference', $clawbackReference)
                ->exists();

            if ($already) {
                return;
            }

            $amount = (float) $commission->amount;
            $balanceBefore = (float) $referrer->balance;
            $referrer->decrement('balance', $amount);

            $clawbackTx = Transaction::create([
                'user_id' => (int) $referrer->getKey(),
                'order_id' => null,
                'type' => 'bonus',
                'amount' => -$amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceBefore - $amount,
                'method' => 'referral_clawback',
                'reference' => $clawbackReference,
                'status' => 'completed',
                'notes' => "Commission reversed — order #{$order->getKey()} was refunded.",
            ]);

            AuditLog::log(
                action: 'referral.order_commission_clawed_back',
                userId: null,
                modelType: Transaction::class,
                modelId: (int) $clawbackTx->getKey(),
                newValues: [
                    'referrer_id' => (int) $referrer->getKey(),
                    'source_order_id' => (int) $order->getKey(),
                    'clawback_amount' => $amount,
                ]
            );
        });
    }

    public function rewardReferrerOnReferredOrder(Order $order): void
    {
        $commissionPercent = $this->orderCommissionPercent();
        $minimumOrderTotal = $this->orderCommissionMinimumTotal();
        if ($commissionPercent <= 0) {
            return;
        }

        $referredUser = User::find($order->getAttribute('user_id'));
        if (! $referredUser) {
            return;
        }

        $referrerId = $referredUser->getAttribute('referred_by');
        if (! $referrerId) {
            return;
        }

        // Ongoing commissions pause if the referrer hasn't referred anyone new
        // within the active window (they resume once they refer again).
        if (! $this->isReferrerCommissionActive((int) $referrerId)) {
            return;
        }

        // Permanent per-referral expiry: after the lifetime window this referral
        // never generates commission again, regardless of activity.
        if (! $this->isReferralWithinLifetime($referredUser)) {
            return;
        }

        // Commission starts after first order, i.e. from second order onward.
        $ordersCount = Order::query()
            ->where('user_id', $referredUser->getKey())
            ->count();

        if ($ordersCount <= 1) {
            return;
        }

        $charge = (float) $order->getAttribute('charge');
        // Inclusive minimum: an order exactly at the threshold qualifies.
        if ($charge < $minimumOrderTotal) {
            return;
        }

        $commissionAmount = round(($charge * $commissionPercent) / 100, 4);

        if ($commissionAmount <= 0) {
            return;
        }

        // Idempotency guard in case order handling is retried.
        $reference = 'REF-ORDER-'.$order->getKey();
        $alreadyRewarded = Transaction::query()
            ->where('user_id', (int) $referrerId)
            ->where('type', 'bonus')
            ->where('reference', $reference)
            ->exists();

        if ($alreadyRewarded) {
            return;
        }

        // Cap: check how many order commissions have been awarded for this referral relationship
        $maxOrders = $this->maxReferralCommissionOrders();
        if ($maxOrders > 0) {
            // Collect all order IDs belonging to the referred user, then count matching bonus TXs
            $referredOrderIds = Order::where('user_id', $referredUser->getKey())
                ->pluck('id')
                ->map(fn ($id) => 'REF-ORDER-'.$id)
                ->all();

            $awardedCount = Transaction::query()
                ->where('user_id', (int) $referrerId)
                ->where('type', 'bonus')
                ->whereIn('reference', $referredOrderIds)
                ->count();

            if ($awardedCount >= $maxOrders) {
                return;
            }
        }

        $referrer = User::find($referrerId);
        if (! $referrer) {
            return;
        }

        DB::transaction(function () use ($referrer, $commissionAmount, $reference, $referredUser, $order, $charge, $commissionPercent, $minimumOrderTotal): void {
            $bonusTx = $referrer->creditBalance(
                $commissionAmount,
                'referral_order',
                $reference,
                'bonus'
            );

            AuditLog::log(
                action: 'referral.order_commission_awarded',
                userId: null,
                modelType: Transaction::class,
                modelId: (int) $bonusTx->getKey(),
                newValues: [
                    'referred_user_id' => (int) $referredUser->getKey(),
                    'referrer_id' => (int) $referrer->getKey(),
                    'source_order_id' => (int) $order->getKey(),
                    'order_charge' => $charge,
                    'commission_percent' => $commissionPercent,
                    'minimum_order_total' => $minimumOrderTotal,
                    'commission_amount' => $commissionAmount,
                ]
            );
        });
    }
}
