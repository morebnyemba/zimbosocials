<?php

namespace App\Services;

use App\Models\BusinessContract;
use App\Models\ContractApplication;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Shared settlement logic for closing a business contract: refunds the
 * escrow for unused slots (budget + platform fee) back to the business and
 * ignores still-pending applications. Used by the user-facing close action
 * and the scheduled deadline auto-close, so both settle identically.
 */
class ContractSettlementService
{
    public const PLATFORM_FEE_RATE = 0.10;

    /**
     * Close a contract and refund unused-slot escrow to the business.
     * Must NOT be called inside an outer transaction that already holds
     * conflicting row locks.
     *
     * @return float The amount refunded (0.0 when every slot was consumed).
     */
    public function closeAndRefundUnusedSlots(BusinessContract $contract, string $refundNote): float
    {
        $refunded = 0.0;

        DB::transaction(function () use ($contract, $refundNote, &$refunded): void {
            $lockedContract = BusinessContract::lockForUpdate()->findOrFail($contract->getKey());

            if ($lockedContract->status === BusinessContract::STATUS_CLOSED) {
                return; // already settled
            }

            $lockedUser = User::lockForUpdate()->findOrFail((int) $lockedContract->user_id);

            $totalSlots = (int) $lockedContract->slots;
            $consumedSlots = ContractApplication::where('business_contract_id', $lockedContract->getKey())
                ->whereIn('status', ContractApplication::slotConsumingStatuses())
                ->count();

            $unusedSlots = $totalSlots - $consumedSlots;

            if ($unusedSlots > 0) {
                $unitBudget = (float) $lockedContract->budget;
                $refundPerSlot = $unitBudget + ($unitBudget * self::PLATFORM_FEE_RATE);
                $totalRefund = round($unusedSlots * $refundPerSlot, 2);

                $balanceBefore = (float) $lockedUser->balance;
                $lockedUser->increment('balance', $totalRefund);

                Transaction::create([
                    'user_id' => (int) $lockedUser->getKey(),
                    'type' => 'refund',
                    'amount' => $totalRefund,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceBefore + $totalRefund,
                    'status' => 'completed',
                    'notes' => $refundNote.' ('.$unusedSlots.' unused slot(s) on contract #'.$lockedContract->getKey().')',
                ]);

                $refunded = $totalRefund;
            }

            $lockedContract->update(['status' => BusinessContract::STATUS_CLOSED]);

            ContractApplication::where('business_contract_id', $lockedContract->getKey())
                ->where('status', ContractApplication::STATUS_PENDING)
                ->update([
                    'status' => ContractApplication::STATUS_IGNORED,
                    'decided_by' => (int) $lockedUser->getKey(),
                    'reviewed_at' => now(),
                ]);
        });

        return $refunded;
    }
}
