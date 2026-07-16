<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\DepositService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Paynow\Payments\Paynow;

/**
 * Actively polls recently-initiated Paynow gateway deposits and resolves them
 * (credit / reject) via DepositService — so a payment's status auto-updates
 * even when nothing else is watching it.
 *
 * Why this exists: only the WEB wallet page polls Paynow (while the tab is
 * open). A WhatsApp-initiated express payment (EcoCash/OneMoney/OMari) has no
 * poller at all — after "payment request sent" the flow ends. Express failures
 * (insufficient funds, wrong PIN, timeout) are reported by POLLING the poll
 * URL, and Paynow often sends no result webhook for them — so those deposits
 * would sit 'pending' until the 24h stale-cleanup. This command, run every
 * minute by the scheduler, closes that window to ~1-2 minutes and feeds the
 * order auto-resume the same way a webhook would.
 *
 * Scoped to the ACTIVE window (default 45 min) so it's cheap and never fights
 * the hourly stale-cleanup, which owns anything older.
 *
 * Usage:   php artisan transactions:poll-gateway [--minutes=45]
 * Schedule: every minute (see routes/console.php)
 */
class PollPendingGatewayDeposits extends Command
{
    protected $signature = 'transactions:poll-gateway
        {--minutes=45 : Only poll pending gateway deposits created within this many minutes}
        {--limit=50 : Max transactions to poll per run}';

    protected $description = 'Poll recently-started Paynow deposits and auto-update their status (credit/reject)';

    public function __construct(
        private readonly Application $app,
        private readonly DepositService $depositService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $since = now()->subMinutes((int) $this->option('minutes'));

        $pending = Transaction::where('type', 'deposit')
            ->where('status', 'pending')
            ->where('created_at', '>=', $since)
            ->where('reference', 'like', 'http%') // has a Paynow poll URL
            ->orderBy('created_at')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($pending->isEmpty()) {
            return self::SUCCESS;
        }

        $credited = 0;
        $rejected = 0;
        $stillPending = 0;

        $paynow = $this->getPaynow();

        foreach ($pending as $transaction) {
            try {
                $status = $paynow->pollTransaction((string) $transaction->reference);

                if ($status && $status->paid()) {
                    $this->depositService->credit($transaction, 'gateway_poller');
                    $credited++;

                    continue;
                }

                if ($status && in_array($status->status(), ['cancelled', 'failed'], true)) {
                    $this->depositService->reject($transaction, 'gateway_poller');
                    $rejected++;

                    continue;
                }

                $stillPending++;
            } catch (\Throwable $e) {
                Log::warning('PollPendingGatewayDeposits: poll failed', [
                    'transaction_id' => $transaction->id,
                    'message' => $e->getMessage(),
                ]);
                $stillPending++;
            }
        }

        if ($credited || $rejected) {
            $this->info("Gateway poll: credited {$credited}, rejected {$rejected}, still pending {$stillPending}.");
        }

        return self::SUCCESS;
    }

    private function getPaynow(): Paynow
    {
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
}
