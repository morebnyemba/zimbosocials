<?php

namespace App\Services\Upstream;

use App\Models\Order;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Single refill path shared by the web button and the reseller API: checks
 * eligibility, gates repeat requests, calls the provider, and records the
 * result on the order.
 */
class OrderRefillService
{
    public function __construct(private readonly UpstreamProviderClient $client) {}

    /**
     * @return array{ok: bool, message: string}
     */
    public function request(Order $order): array
    {
        $order->loadMissing('service', 'provider');

        if (! $order->service?->is_refill) {
            return ['ok' => false, 'message' => 'This service does not support refills.'];
        }

        if (! in_array($order->status, ['completed', 'partial'], true)) {
            return ['ok' => false, 'message' => 'Refills are only available once an order has finished delivering.'];
        }

        if (! $order->pushed_to_upstream || ! $order->external_order_id || ! $order->provider?->is_active) {
            return ['ok' => false, 'message' => 'This order cannot be refilled automatically — please open a support ticket.'];
        }

        if ($order->refill_requested_at !== null && $order->refill_requested_at->gt(now()->subDay())) {
            return ['ok' => false, 'message' => 'A refill was already requested for this order in the last 24 hours.'];
        }

        // Atomic gate against double clicks / racing API calls.
        if (! Cache::add("order:refill:{$order->id}", true, 60)) {
            return ['ok' => false, 'message' => 'A refill request for this order is already being processed.'];
        }

        $this->client->setProvider($order->provider);
        $result = $this->client->requestRefill((string) $order->external_order_id);

        if (! $result['ok']) {
            Log::warning("Refill request failed for order #{$order->id}", ['message' => $result['message']]);

            return ['ok' => false, 'message' => 'The provider declined the refill: '.$result['message']];
        }

        $order->update([
            'refill_requested_at' => now(),
            'external_refill_id' => $result['refill_id'],
        ]);

        NotificationService::notify(
            (int) $order->user_id,
            'order_status_changed',
            "Refill Requested for Order #{$order->id}",
            'Your refill request has been submitted to the delivery network. Lost engagement is typically restored within 24–72 hours.',
            ['order_id' => $order->id]
        );

        return ['ok' => true, 'message' => 'Refill requested — lost engagement is typically restored within 24–72 hours.'];
    }
}
