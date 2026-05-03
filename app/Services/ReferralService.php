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

        if ($referredUser->getAttribute('referred_bonus_awarded_at')) {
            return;
        }

        $completedDepositCount = Transaction::query()
            ->where('user_id', $referredUser->getKey())
            ->where('type', 'deposit')
            ->where('status', 'completed')
            ->count();

        if ($completedDepositCount !== 1) {
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
                'REF-FIRST-DEPOSIT-' . $freshReferredUser->getKey(),
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

        // Commission starts after first order, i.e. from second order onward.
        $ordersCount = Order::query()
            ->where('user_id', $referredUser->getKey())
            ->count();

        if ($ordersCount <= 1) {
            return;
        }

        $charge = (float) $order->getAttribute('charge');
        if ($charge <= $minimumOrderTotal) {
            return;
        }

        $commissionAmount = round(($charge * $commissionPercent) / 100, 4);

        if ($commissionAmount <= 0) {
            return;
        }

        // Idempotency guard in case order handling is retried.
        $reference = 'REF-ORDER-' . $order->getKey();
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
                ->map(fn ($id) => 'REF-ORDER-' . $id)
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
    }
}
