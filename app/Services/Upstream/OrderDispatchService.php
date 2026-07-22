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
            $result = $this->client->placeOrder($order, $upstream->external_service_id, $upstream->link_type);
            $lastResult = $result;

            if ($result['ok']) {
                $this->recordSuccessfulPush($order, $result, $provider->id, $attempts);

                return $result;
            }

            // Connection lost mid-request: the provider may have accepted the
            // order, so failing over to another provider (or retrying) could
            // buy the same delivery twice. Stop and hand it to a human.
            if ($result['unknown'] ?? false) {
                $order->update([
                    'push_attempts' => $attempts,
                    'pushed_to_upstream' => false,
                    'upstream_provider_id' => $provider->id,
                    'upstream_last_error' => 'UNKNOWN OUTCOME: '.$result['message'],
                ]);

                NotificationService::notifyAdmins(
                    'admin_order_dispatch_unknown',
                    "Order #{$order->id} needs manual verification",
                    "The connection to {$provider->name} dropped while submitting order #{$order->id} — it may or may not exist upstream. Check the provider panel before retrying or refunding.",
                    ['order_id' => $order->id, 'provider_id' => $provider->id]
                );

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
     * Cancel an order that could not be delivered and refund whatever is still
     * owed — atomic under row locks, idempotent, and safe to call from multiple
     * places (the dispatch job's failed() handler and the pending-recovery
     * command). Only acts on an order still stuck exactly where we left it
     * (pending, never pushed); anything else already moved the money. Returns
     * the amount refunded (0.0 if it was skipped).
     */
    public function cancelAndRefundUndeliverable(int $orderId, string $reason): float
    {
        $refundAmount = 0.0;
        $userId = null;

        DB::transaction(function () use ($orderId, $reason, &$refundAmount, &$userId): void {
            $order = Order::lockForUpdate()->find($orderId);

            // Guard: never refund an order that was pushed upstream (it may be
            // delivering) or already resolved (cancelled/completed elsewhere).
            if (! $order || $order->pushed_to_upstream || $order->status !== 'pending') {
                return;
            }

            $order->update([
                'status' => 'cancelled',
                'upstream_last_error' => $reason,
            ]);

            $user = \App\Models\User::lockForUpdate()->find($order->user_id);
            if (! $user) {
                \Illuminate\Support\Facades\Log::error("cancelAndRefundUndeliverable: user {$order->user_id} missing for order #{$order->id}");

                return;
            }

            $remaining = $order->remainingRefundable();
            if ($remaining > 0) {
                $user->creditBalance($remaining, 'refund', "Auto-refund: order #{$order->id} — {$reason}", 'refund', $order);
            }

            $refundAmount = $remaining;
            $userId = (int) $user->id;
        });

        if ($userId !== null) {
            NotificationService::notify(
                $userId,
                'order_refunded',
                "Order #{$orderId} Refunded",
                "We couldn't submit your order, so it was cancelled and \${$refundAmount} was refunded to your wallet.",
                ['order_id' => $orderId, 'refund_amount' => $refundAmount]
            );
        }

        return $refundAmount;
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
