<?php

namespace App\Services\Upstream;

use App\Models\Order;
use App\Models\ServiceUpstream;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;

class OrderDispatchService
{
    public function __construct(private readonly UpstreamProviderClient $client) {}

    public function dispatch(Order $order): array
    {
        // Guard against dispatching an order that was cancelled (and refunded)
        // or already pushed by a competing worker between queueing and now.
        $order->refresh();

        if ($order->pushed_to_upstream) {
            return [
                'ok' => true,
                'message' => 'Order was already pushed to upstream.',
                'raw' => null,
                'external_order_id' => $order->external_order_id,
            ];
        }

        if ($order->status !== 'pending') {
            return [
                'ok' => false,
                'message' => "Order is no longer pending (status: {$order->status}); dispatch skipped.",
                'raw' => null,
                'external_order_id' => null,
            ];
        }

        $order->loadMissing('service.upstreams.provider');

        $upstreams = $order->service->upstreams;

        if ($upstreams->isEmpty()) {
            $order->update([
                'push_attempts' => (int) $order->push_attempts + 1,
                'pushed_to_upstream' => false,
                'upstream_last_error' => 'No active upstream provider configured for this service.',
            ]);

            return [
                'ok' => false,
                'message' => 'No active upstream provider configured.',
                'raw' => null,
                'external_order_id' => null,
            ];
        }

        $attempts = (int) $order->push_attempts + 1;
        $lastResult = null;

        /** @var ServiceUpstream $upstream */
        foreach ($upstreams as $upstream) {
            $provider = $upstream->provider;

            if (! $provider || ! $provider->is_active) {
                continue;
            }

            $this->client->setProvider($provider);
            $result = $this->client->placeOrder($order, $upstream->external_service_id);
            $lastResult = $result;

            if ($result['ok']) {
                $this->recordSuccessfulPush($order, $result, $provider->id, $attempts);

                return $result;
            }
        }

        // If we reach here, all providers failed
        $order->update([
            'push_attempts' => $attempts,
            'pushed_to_upstream' => false,
            'upstream_last_error' => $lastResult ? $lastResult['message'] : 'All providers failed.',
        ]);

        return $lastResult ?? [
            'ok' => false,
            'message' => 'All upstream providers failed.',
            'raw' => null,
            'external_order_id' => null,
        ];
    }

    /**
     * Record a successful upstream push under a row lock. If the user managed
     * to cancel (and get refunded) while the HTTP push was in flight, we must
     * NOT flip the order back to processing — that would hand out the refund
     * and the delivery. Keep the cancelled status, record the external id,
     * and alert admins to reconcile (cancel upstream or reverse the refund).
     */
    private function recordSuccessfulPush(Order $order, array $result, int $providerId, int $attempts): void
    {
        $collided = false;

        DB::transaction(function () use ($order, $result, $providerId, $attempts, &$collided): void {
            $locked = Order::lockForUpdate()->findOrFail($order->id);

            $base = [
                'external_order_id' => $result['external_order_id'],
                'push_attempts' => $attempts,
                'pushed_to_upstream' => true,
                'pushed_at' => now(),
                'upstream_provider_id' => $providerId,
            ];

            if ($locked->status === 'pending') {
                $locked->update($base + [
                    'status' => 'processing',
                    'upstream_last_error' => null,
                ]);

                return;
            }

            $collided = true;
            $locked->update($base + [
                'upstream_last_error' => "Pushed upstream but order was locally {$locked->status} during dispatch — needs manual reconciliation.",
            ]);
        });

        $order->refresh();

        if ($collided) {
            NotificationService::notifyAdmins(
                'admin_order_dispatch_conflict',
                "Order #{$order->id} needs reconciliation",
                "Order #{$order->id} was cancelled/refunded locally while the upstream push was in flight, but the provider accepted it (external ID {$result['external_order_id']}). Cancel it upstream or reverse the refund.",
                ['order_id' => $order->id, 'external_order_id' => $result['external_order_id']]
            );
        }
    }
}
