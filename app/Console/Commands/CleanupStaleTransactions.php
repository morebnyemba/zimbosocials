<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\DepositService;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Paynow\Payments\Paynow;

/**
 * Automatically expire pending DEPOSIT transactions that have been sitting
 * unresolved for longer than the configured TTL.
 *
 * Deliberately narrow in scope:
 *  - Withdrawals are NEVER touched. A pending withdrawal already debited the
 *    user's balance ("funds reserved"); expiring it would destroy that money.
 *    Old pending withdrawals are surfaced to admins instead.
 *  - Manual deposits with a submitted proof are skipped — they're in the
 *    admin review queue, however long that takes.
 *  - Gateway (Paynow) deposits get one final poll before expiry, so a payment
 *    whose webhook was missed is credited instead of thrown away.
 *
 * Usage:   php artisan transactions:cleanup-stale
 * Schedule: hourly (see routes/console.php)
 */
class CleanupStaleTransactions extends Command
{
    protected $signature = 'transactions:cleanup-stale
        {--hours=24 : Hours after which a pending deposit is considered stale}
        {--dry-run : Show what would be cleaned up without making changes}';

    protected $description = 'Expire pending deposits that have been unresolved beyond the TTL (final-polls Paynow first; never touches withdrawals)';

    public function __construct(
        private readonly Application $app,
        private readonly DepositService $depositService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subHours($hours);

        $stale = Transaction::where('status', 'pending')
            ->where('type', 'deposit')
            ->where('created_at', '<', $cutoff)
            ->get();

        $this->warnAboutOldWithdrawals($cutoff);

        if ($stale->isEmpty()) {
            $this->info('No stale pending deposits found.');

            return self::SUCCESS;
        }

        $this->info("Found {$stale->count()} stale pending deposit(s) older than {$hours} hours.");

        $expired = 0;
        $credited = 0;
        $rejected = 0;
        $skipped = 0;

        foreach ($stale as $transaction) {
            // Proof already submitted — it's in the admin review queue, not stale.
            if (! empty($transaction->proof_url)) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->line("  TX #{$transaction->id} — {$transaction->type} \${$transaction->amount} ({$transaction->method}) — {$transaction->created_at}");

                continue;
            }

            $pollUrl = (string) $transaction->reference;

            // Gateway deposit — final poll before giving up, so a paid
            // transaction with a missed webhook is credited, not expired.
            if (str_starts_with($pollUrl, 'http')) {
                $outcome = $this->resolveViaPaynowPoll($transaction, $pollUrl);

                if ($outcome === 'credited') {
                    $credited++;

                    continue;
                }
                if ($outcome === 'rejected') {
                    $rejected++;

                    continue;
                }
                // 'unknown' falls through to expiry below.
            }

            if ($this->depositService->expire($transaction, 'stale_cleanup', "Auto-expired after {$hours}h by cleanup command")) {
                $expired++;
            }
        }

        if ($dryRun) {
            $this->warn('Dry run — no changes made.');

            return self::SUCCESS;
        }

        Log::info("CleanupStaleTransactions: expired {$expired}, credited {$credited} (late payment), rejected {$rejected}, skipped {$skipped} (proof under review).");
        $this->info("Expired {$expired}, credited {$credited} (late payment found), rejected {$rejected}, skipped {$skipped} (proof under review).");

        return self::SUCCESS;
    }

    /**
     * Poll Paynow one last time for a stale gateway deposit.
     * Returns 'credited', 'rejected', or 'unknown' (poll failed / still pending).
     */
    private function resolveViaPaynowPoll(Transaction $transaction, string $pollUrl): string
    {
        try {
            $status = $this->getPaynow()->pollTransaction($pollUrl);

            if ($status && $status->paid()) {
                $this->depositService->credit($transaction, 'stale_cleanup_poll');

                return 'credited';
            }

            if ($status && in_array($status->status(), ['cancelled', 'failed'], true)) {
                $this->depositService->reject($transaction, 'stale_cleanup_poll');

                return 'rejected';
            }
        } catch (\Throwable $e) {
            Log::warning('CleanupStaleTransactions: Paynow poll failed', [
                'transaction_id' => $transaction->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }

        return 'unknown';
    }

    private function getPaynow(): Paynow
    {
        // Resolve through the container so tests can override with a mock
        if ($this->app->bound(Paynow::class)) {
            return $this->app->make(Paynow::class);
        }

        return new Paynow(
            config('services.paynow.integration_id'),
            config('services.paynow.integration_key'),
            route('paynow.return'),
            route('paynow.update')
        );
    }

    /**
     * Pending withdrawals hold reserved user funds and must be resolved by an
     * admin (process or reject) — nudge admins about any that have gone stale.
     */
    private function warnAboutOldWithdrawals(\Illuminate\Support\Carbon $cutoff): void
    {
        $oldWithdrawals = Transaction::where('status', 'pending')
            ->where('type', 'withdrawal')
            ->where('created_at', '<', $cutoff)
            ->count();

        if ($oldWithdrawals === 0) {
            return;
        }

        $this->warn("{$oldWithdrawals} pending withdrawal(s) are older than the TTL — leaving them for admin action (reserved funds).");

        // The command runs hourly — only nudge admins once per day.
        if (! $this->option('dry-run') && \Illuminate\Support\Facades\Cache::add('admin:stale_withdrawals_notified', true, now()->addDay())) {
            NotificationService::notifyAdmins(
                'admin_stale_withdrawals',
                'Stale Withdrawal Requests',
                "{$oldWithdrawals} withdrawal request(s) have been pending for over 24 hours. Process or reject them so the reserved funds are resolved.",
                ['count' => $oldWithdrawals]
            );
        }
    }
}
