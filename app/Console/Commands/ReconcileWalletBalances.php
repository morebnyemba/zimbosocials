<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Verifies that every user's wallet balance matches the sum of their applied
 * ledger transactions. The balance column is mutable and touched by many code
 * paths — this is the safety net that surfaces any money bug as a concrete,
 * per-user drift instead of letting it accumulate silently.
 *
 * Applied = transactions that actually moved the balance:
 *   - any type with status 'completed'
 *   - withdrawals with status 'pending' (balance is debited at request time;
 *     a rejected withdrawal is re-credited and its row flipped to 'rejected')
 *
 * Read-only. Never mutates balances.
 *
 * Usage:   php artisan wallet:reconcile [--tolerance=0.01]
 * Schedule: daily (see routes/console.php)
 */
class ReconcileWalletBalances extends Command
{
    protected $signature = 'wallet:reconcile
        {--tolerance=0.01 : Maximum absolute drift (USD) treated as rounding noise}';

    protected $description = 'Verify user balances against the transaction ledger and flag drift';

    public function handle(): int
    {
        $tolerance = (float) $this->option('tolerance');

        $expected = DB::table('transactions')
            ->selectRaw("user_id, SUM(amount) AS expected_balance")
            ->where(function ($q) {
                $q->where('status', 'completed')
                    ->orWhere(fn ($q2) => $q2->where('type', 'withdrawal')->where('status', 'pending'));
            })
            ->groupBy('user_id')
            ->pluck('expected_balance', 'user_id');

        $drifted = [];

        User::query()
            ->select(['id', 'name', 'email', 'balance'])
            ->orderBy('id')
            ->chunk(500, function ($users) use ($expected, $tolerance, &$drifted): void {
                foreach ($users as $user) {
                    $ledger = (float) ($expected[$user->id] ?? 0);
                    $actual = (float) $user->balance;
                    $drift = round($actual - $ledger, 4);

                    if (abs($drift) > $tolerance) {
                        $drifted[] = [
                            'user_id' => $user->id,
                            'email' => $user->email,
                            'balance' => $actual,
                            'ledger' => $ledger,
                            'drift' => $drift,
                        ];
                    }
                }
            });

        if (empty($drifted)) {
            $this->info('All balances reconcile with the ledger.');

            return self::SUCCESS;
        }

        $this->error(count($drifted).' user(s) have balance drift beyond ±$'.$tolerance.':');
        $this->table(['User ID', 'Email', 'Balance', 'Ledger', 'Drift'], array_map(
            fn ($row) => [$row['user_id'], $row['email'], number_format($row['balance'], 4), number_format($row['ledger'], 4), number_format($row['drift'], 4)],
            $drifted
        ));

        Log::error('Wallet reconciliation found drift', ['drifted' => $drifted]);

        // Alert admins once per day at most (the command may be re-run manually).
        if (Cache::add('admin:wallet_drift_notified', true, now()->addDay())) {
            NotificationService::notifyAdmins(
                'admin_wallet_drift',
                'Wallet Reconciliation Alert',
                count($drifted).' user balance(s) no longer match the transaction ledger. Check the logs (wallet:reconcile) and investigate before it compounds.',
                ['count' => count($drifted)]
            );
        }

        return self::FAILURE;
    }
}
