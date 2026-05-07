<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Automatically expire pending deposit/withdrawal transactions that have been
 * sitting unresolved for longer than the configured TTL.
 *
 * Usage:   php artisan transactions:cleanup-stale
 * Schedule: hourly (see routes/console.php)
 */
class CleanupStaleTransactions extends Command
{
    protected $signature = 'transactions:cleanup-stale
        {--hours=24 : Hours after which a pending transaction is considered stale}
        {--dry-run : Show what would be cleaned up without making changes}';

    protected $description = 'Expire pending transactions that have been unresolved beyond the TTL';

    public function handle(): int
    {
        $hours  = (int) $this->option('hours');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subHours($hours);

        $query = Transaction::where('status', 'pending')
            ->where('created_at', '<', $cutoff);

        $count = $query->count();

        if ($count === 0) {
            $this->info('No stale pending transactions found.');
            return self::SUCCESS;
        }

        $this->info("Found {$count} stale pending transaction(s) older than {$hours} hours.");

        if ($dryRun) {
            $query->get(['id', 'user_id', 'type', 'amount', 'method', 'created_at'])
                ->each(fn ($tx) => $this->line(
                    "  TX #{$tx->id} — {$tx->type} \${$tx->amount} ({$tx->method}) — {$tx->created_at}"
                ));
            $this->warn('Dry run — no changes made.');
            return self::SUCCESS;
        }

        $updated = $query->update([
            'status' => 'expired',
            'notes'  => "Auto-expired after {$hours}h by cleanup command",
        ]);

        Log::info("CleanupStaleTransactions: expired {$updated} pending transaction(s) older than {$hours}h.");
        $this->info("Expired {$updated} transaction(s).");

        return self::SUCCESS;
    }
}
