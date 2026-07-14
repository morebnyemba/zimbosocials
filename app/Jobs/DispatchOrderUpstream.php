<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\NotificationService;
use App\Services\Upstream\OrderDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
        $refundAmount = app(OrderDispatchService::class)->cancelAndRefundUndeliverable(
            $this->orderId,
            'Auto-cancelled: all dispatch attempts failed. Charge refunded.'
        );

        if ($refundAmount > 0) {
            NotificationService::notifyAdmins(
                'admin_order_dispatch_failed',
                "Order #{$this->orderId} auto-refunded",
                "Order #{$this->orderId} failed all upstream dispatch attempts and was cancelled + refunded (\${$refundAmount}). Last error: {$e->getMessage()}",
                ['order_id' => $this->orderId, 'refund_amount' => $refundAmount]
            );
        }
    }
}
