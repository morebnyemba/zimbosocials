<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\Upstream\OrderDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Retries pushing an order to its upstream provider after the synchronous
 * in-request push failed. Throws on failure so the queue's tries/backoff
 * drive the retries; after the final attempt, failed() cancels the order and
 * refunds the customer so their money never stays stuck on an undeliverable
 * order.
 */
class DispatchOrderUpstream implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [10, 30, 120, 300, 600]; // progressive backoff

    public function __construct(
        public readonly int $orderId,
    ) {}

    public function handle(OrderDispatchService $dispatcher): void
    {
        $order = Order::find($this->orderId);

        if (! $order || $order->pushed_to_upstream) {
            return; // Already handled or order deleted
        }

        if ($order->status !== 'pending') {
            return; // Cancelled/resolved while queued — nothing to push
        }

        $result = $dispatcher->dispatch($order);

        if ($result['ok']) {
            NotificationService::notify(
                $order->user_id,
                'order_status_changed',
                "Order #{$order->id} Processing",
                'Your order has been submitted and is now processing.',
                ['order_id' => $order->id, 'status' => 'processing']
            );

            return;
        }

        // Unknown outcome (connection lost mid-request): the provider may
        // have the order — retrying could buy the delivery twice. Admins were
        // alerted by the dispatch service; stop retrying quietly.
        if ($result['unknown'] ?? false) {
            return;
        }

        // Throw so the queue retries with backoff; failed() handles the
        // refund once every attempt is exhausted.
        throw new \RuntimeException(
            "Upstream dispatch failed for order #{$order->id}: {$result['message']}"
        );
    }

    /**
     * All retries exhausted — cancel the order and refund the charge so the
     * customer's money doesn't stay stuck on an order that can't be delivered.
     */
    public function failed(\Throwable $e): void
    {
        $refundAmount = 0.0;
        $userId = null;

        DB::transaction(function () use (&$refundAmount, &$userId): void {
            $order = Order::lockForUpdate()->find($this->orderId);

            // Only refund if the order is still stuck exactly where we left it —
            // pending and never pushed. Anything else (cancelled by the user,
            // pushed by a late retry, resolved by an admin) already moved the money.
            if (! $order || $order->pushed_to_upstream || $order->status !== 'pending') {
                return;
            }

            $order->update([
                'status' => 'cancelled',
                'upstream_last_error' => 'Auto-cancelled: all dispatch attempts failed. Charge refunded.',
            ]);

            $user = User::lockForUpdate()->find($order->user_id);
            if (! $user) {
                Log::error("DispatchOrderUpstream: user {$order->user_id} missing for refund of order #{$order->id}");

                return;
            }

            $remaining = $order->remainingRefundable();
            if ($remaining > 0) {
                $user->creditBalance(
                    $remaining,
                    'refund',
                    "Auto-refund: order #{$order->id} could not be submitted to the provider",
                    'refund',
                    $order
                );
            }

            $refundAmount = $remaining;
            $userId = (int) $user->id;
        });

        if ($userId !== null) {
            NotificationService::notify(
                $userId,
                'order_refunded',
                "Order #{$this->orderId} Refunded",
                "We couldn't submit your order after several attempts, so it was cancelled and \${$refundAmount} was refunded to your wallet.",
                ['order_id' => $this->orderId, 'refund_amount' => $refundAmount]
            );

            NotificationService::notifyAdmins(
                'admin_order_dispatch_failed',
                "Order #{$this->orderId} auto-refunded",
                "Order #{$this->orderId} failed all upstream dispatch attempts and was cancelled + refunded (\${$refundAmount}). Last error: {$e->getMessage()}",
                ['order_id' => $this->orderId, 'refund_amount' => $refundAmount]
            );
        }
    }
}
