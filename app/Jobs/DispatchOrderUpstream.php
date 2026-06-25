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

        $result = $dispatcher->dispatch($order);

        if ($result['ok']) {
            NotificationService::notify(
                $order->user_id,
                'order_status_changed',
                "Order #{$order->id} Processing",
                'Your order has been submitted and is now processing.',
                ['order_id' => $order->id, 'status' => 'processing']
            );
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $e): void
    {
        $order = Order::find($this->orderId);
        if ($order) {
            NotificationService::notify(
                $order->user_id,
                'order_status_changed',
                "Order #{$order->id} — Attention Needed",
                'Your order encountered an issue during processing. Our team has been notified.',
                ['order_id' => $order->id, 'error' => $e->getMessage()]
            );
        }
    }
}
