<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\NotificationService;
use App\Services\Upstream\OrderDispatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Queue-independent safety net that forces stuck PENDING orders to a terminal
 * state, so an order never sits on 'pending' forever.
 *
 * Why this exists: the normal path is synchronous dispatch → (on failure) the
 * DispatchOrderUpstream queued retries → after 5 fails, auto-cancel+refund. All
 * of that depends on the queue worker actually running. On shared hosting the
 * worker is a cron job; if it stops, failed pushes never retry and never cancel,
 * and the order is stranded on 'pending'. This command runs on the scheduler
 * and closes that gap.
 *
 * Scope is deliberately narrow and money-safe:
 *  - Only PENDING orders that were NEVER pushed upstream (pushed_to_upstream =
 *    false) — nothing was delivered, so cancelling+refunding is always safe.
 *  - A grace period keeps it clear of the normal sync+queue path (which resolves
 *    healthy orders within ~17 min), so it never double-dispatches.
 *  - 'UNKNOWN OUTCOME' orders (connection lost mid-push — may exist upstream)
 *    are NEVER auto-cancelled; they're escalated to admins for manual checking.
 *  - PROCESSING orders (already pushed) are out of scope — they may be mid-
 *    delivery; upstream:sync-orders + orders:flag-stuck handle those.
 *
 * Usage:   php artisan orders:recover-pending [--grace=30] [--deadline=120]
 * Schedule: every five minutes (see routes/console.php)
 */
class RecoverPendingOrders extends Command
{
    protected $signature = 'orders:recover-pending
        {--grace=30 : Minutes an order may be pending before we step in (keeps clear of the normal queue retries)}
        {--deadline=120 : Minutes after which an un-pushable pending order is cancelled + refunded}
        {--dry-run : Report what would happen without changing anything}';

    protected $description = 'Force stuck pending orders to a terminal state (re-dispatch, else cancel + refund)';

    public function handle(OrderDispatchService $dispatcher): int
    {
        $grace = (int) $this->option('grace');
        $deadline = (int) $this->option('deadline');
        $dryRun = (bool) $this->option('dry-run');

        $orders = Order::where('status', 'pending')
            ->where('pushed_to_upstream', false)
            ->where('created_at', '<', now()->subMinutes($grace))
            ->orderBy('created_at')
            ->limit(100)
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No stuck pending orders.');

            return self::SUCCESS;
        }

        $redispatched = 0;
        $cancelled = 0;
        $escalated = 0;
        $waiting = 0;
        $escalateIds = [];

        foreach ($orders as $order) {
            // Connection-lost orders may already exist upstream — never auto-
            // cancel (double-charge risk). Escalate to a human instead.
            if (str_starts_with((string) $order->upstream_last_error, 'UNKNOWN OUTCOME')) {
                $escalated++;
                $escalateIds[] = $order->id;

                continue;
            }

            if ($dryRun) {
                $overDeadline = $order->created_at->lt(now()->subMinutes($deadline));
                $this->line("  #{$order->id} pending {$order->created_at->diffForHumans()} — would ".($overDeadline ? 'CANCEL + REFUND' : 're-dispatch'));

                continue;
            }

            // One more real dispatch attempt (independent of the queue).
            $result = $dispatcher->dispatch($order);

            if ($result['ok']) {
                $redispatched++;
                Log::info("RecoverPendingOrders: order #{$order->id} pushed on recovery attempt.");

                continue;
            }

            // A fresh connection-loss during recovery — leave for manual too.
            if ($result['unknown'] ?? false) {
                $escalated++;
                $escalateIds[] = $order->id;

                continue;
            }

            // Still un-pushable and past the hard deadline → make it terminal.
            if ($order->created_at->lt(now()->subMinutes($deadline))) {
                $refund = $dispatcher->cancelAndRefundUndeliverable(
                    $order->id,
                    "Auto-cancelled after {$deadline}m stuck pending (could not reach any provider). Charge refunded."
                );
                if ($refund >= 0) {
                    $cancelled++;
                    Log::warning("RecoverPendingOrders: order #{$order->id} cancelled + refunded \${$refund} (last error: {$result['message']}).");
                }

                continue;
            }

            // Not yet at the deadline — will retry next run.
            $waiting++;
        }

        if ($escalateIds !== []) {
            $this->escalate($escalateIds);
        }

        $this->info("Recovery: re-dispatched {$redispatched}, cancelled+refunded {$cancelled}, awaiting deadline {$waiting}, escalated {$escalated}.");

        return self::SUCCESS;
    }

    /**
     * Alert admins about pending orders that can't be auto-resolved (connection
     * lost mid-push — may or may not exist upstream). Once per order per day.
     */
    private function escalate(array $orderIds): void
    {
        $fresh = array_values(array_filter(
            $orderIds,
            fn (int $id) => Cache::add("order:unknown_escalated:{$id}", true, now()->addDay())
        ));

        if ($fresh === []) {
            return;
        }

        $list = implode(', ', array_map(fn ($id) => "#{$id}", $fresh));
        NotificationService::notifyAdmins(
            'admin_order_needs_verification',
            count($fresh).' order(s) need manual verification',
            "These orders lost the connection mid-submission and may or may not exist at the provider — check the provider panel, then complete or refund each: {$list}.",
            ['order_ids' => $fresh]
        );
    }
}
