<?php

namespace App\Console\Commands;

use App\Models\BusinessContract;
use App\Services\ContractSettlementService;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Closes open/filled contracts whose deadline has passed and refunds the
 * escrow for unused slots back to the business. Without this, deadline_at was
 * stored but never enforced anywhere — expired contracts stayed open forever
 * with the business's escrow locked up.
 *
 * Usage:   php artisan contracts:close-expired
 * Schedule: daily (see routes/console.php)
 */
class CloseExpiredContracts extends Command
{
    protected $signature = 'contracts:close-expired {--dry-run : List expired contracts without closing them}';

    protected $description = 'Close contracts past their deadline and refund unused-slot escrow to the business';

    public function handle(ContractSettlementService $settlement): int
    {
        $expired = BusinessContract::whereIn('status', [BusinessContract::STATUS_OPEN, BusinessContract::STATUS_FILLED])
            ->whereNotNull('deadline_at')
            ->whereDate('deadline_at', '<', now()->toDateString())
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired contracts to close.');

            return self::SUCCESS;
        }

        $closed = 0;

        foreach ($expired as $contract) {
            if ($this->option('dry-run')) {
                $this->line("  Contract #{$contract->id} \"{$contract->title}\" — deadline {$contract->deadline_at?->toDateString()}");

                continue;
            }

            try {
                $refunded = $settlement->closeAndRefundUnusedSlots($contract, 'Refund on deadline auto-close');
                $closed++;

                NotificationService::notify(
                    (int) $contract->user_id,
                    'contract_application',
                    'Contract Closed (Deadline Reached)',
                    "Your contract \"{$contract->title}\" reached its deadline and was closed."
                        .($refunded > 0 ? " \${$refunded} of unused escrow was refunded to your wallet." : ''),
                    ['contract_id' => (int) $contract->id, 'refunded' => $refunded]
                );
            } catch (\Throwable $e) {
                Log::error("CloseExpiredContracts: failed to close contract #{$contract->id}", [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
                $this->error("  Failed to close contract #{$contract->id}: {$e->getMessage()}");
            }
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no changes made.');

            return self::SUCCESS;
        }

        $this->info("Closed {$closed} expired contract(s).");

        return self::SUCCESS;
    }
}
