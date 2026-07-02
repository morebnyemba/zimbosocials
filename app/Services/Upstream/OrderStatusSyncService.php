<?php

namespace App\Services\Upstream;

use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Single source of truth for resolving an upstream provider's raw order-status
 * payload into a local order status and applying it. Shared by the scheduled
 * upstream:sync-orders command and the admin "force sync" action, so both stay
 * bug-for-bug consistent.
 */
class OrderStatusSyncService
{
    /**
     * Map an upstream status payload to a local order status.
     *
     * Many upstream providers never flip their own status string to
     * "Completed" even once delivery is actually finished — the order just
     * sits reporting "Processing"/"Pending" forever with remains=0. We trust
     * remains=0 (fully delivered) over a stale or unrecognized status string,
     * except for explicit terminal states where the provider is telling us
     * something more specific.
     *
     * @return string|null Null means "no change" (unrecognized status, no remains signal).
     */
    public function resolveLocalStatus(array $data): ?string
    {
        $upstreamStatus = strtolower($data['status'] ?? '');

        // Local enum uses British spelling ('cancelled').
        $localStatus = match ($upstreamStatus) {
            'pending', 'in progress', 'inprogress' => 'processing',
            'processing' => 'processing',
            'completed' => 'completed',
            'partial' => 'partial',
            'canceled', 'cancelled' => 'cancelled',
            default => null,
        };

        if (
            array_key_exists('remains', $data)
            && (int) $data['remains'] === 0
            && ! in_array($localStatus, ['partial', 'cancelled', 'refunded'], true)
        ) {
            $localStatus = 'completed';
        }

        return $localStatus;
    }

    /**
     * Apply a resolved status change to an order: updates the row, processes
     * any refund owed (cancelled/partial), and notifies the user.
     */
    public function applyStatusUpdate(Order $order, string $newStatus, array $upstreamData, string $source = 'sync'): void
    {
        DB::transaction(function () use ($order, $newStatus, $upstreamData, $source) {
            $oldStatus = $order->status;
            $user = User::find($order->user_id);
            $charge = (float) $order->charge;
            $remains = (int) ($upstreamData['remains'] ?? 0);
            $startCount = (int) ($upstreamData['start_count'] ?? $order->start_count);

            $refundAmount = 0;

            if (in_array($newStatus, ['cancelled', 'refunded', 'failed'])) {
                $refundAmount = $charge;
            } elseif ($newStatus === 'partial') {
                if ($order->quantity > 0 && $remains > 0) {
                    $refundAmount = round(($remains / $order->quantity) * $charge, 4);
                }
            }

            $order->update([
                'status' => $newStatus,
                'start_count' => $startCount,
                'remains' => $remains,
            ]);

            if ($refundAmount > 0 && $user) {
                $lockedUser = User::lockForUpdate()->findOrFail($user->id);
                $balanceBefore = (float) $lockedUser->balance;
                $lockedUser->increment('balance', $refundAmount);

                Transaction::create([
                    'user_id' => $lockedUser->id,
                    'order_id' => $order->id,
                    'type' => 'refund',
                    'amount' => $refundAmount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceBefore + $refundAmount,
                    'status' => 'completed',
                    'notes' => "Auto-refund for order #{$order->id} ({$newStatus})",
                ]);

                NotificationService::notify(
                    $user->id,
                    'order_refunded',
                    "Order #{$order->id} Refunded",
                    "Your order #{$order->id} was marked as {$newStatus}. \${$refundAmount} has been refunded to your wallet.",
                    ['order_id' => $order->id, 'refund_amount' => $refundAmount]
                );
            } elseif ($user) {
                NotificationService::notify(
                    $user->id,
                    'order_status_changed',
                    "Order #{$order->id} Updated",
                    "Your order #{$order->id} is now {$newStatus}.",
                    ['order_id' => $order->id, 'status' => $newStatus, 'service_name' => $order->service->name, 'quantity' => $order->quantity]
                );
            }

            Log::info("Order #{$order->id} synced ({$source}): {$oldStatus} -> {$newStatus}. Refund: \${$refundAmount}");
        });
    }

    /**
     * Fetch and apply the current upstream status for a single order right now
     * (used by the admin "force sync" action). Returns a result summary.
     */
    public function syncSingleOrder(Order $order, UpstreamProviderClient $client): array
    {
        if (! $order->pushed_to_upstream || ! $order->external_order_id || ! $order->upstream_provider_id) {
            return ['changed' => false, 'message' => 'This order was never pushed to an upstream provider.'];
        }

        $provider = $order->provider;
        if (! $provider || ! $provider->is_active) {
            return ['changed' => false, 'message' => 'The upstream provider for this order is missing or inactive.'];
        }

        $client->setProvider($provider);
        $statuses = $client->getStatus([$order->external_order_id]);
        $data = $statuses[$order->external_order_id] ?? null;

        if (! $data || isset($data['error'])) {
            $message = $data['error'] ?? 'Provider returned no status for this order.';

            return ['changed' => false, 'message' => "Upstream error: {$message}"];
        }

        $localStatus = $this->resolveLocalStatus($data);

        if (! $localStatus || $localStatus === $order->status) {
            // Still refresh remains/start_count even if the status itself hasn't changed.
            $order->update([
                'start_count' => $data['start_count'] ?? $order->start_count,
                'remains' => $data['remains'] ?? $order->remains,
            ]);

            return [
                'changed' => false,
                'message' => "No status change (upstream reports \"{$data['status']}\"" . (isset($data['remains']) ? ", remains {$data['remains']}" : '') . ').',
            ];
        }

        $oldStatus = $order->status;
        $this->applyStatusUpdate($order, $localStatus, $data, 'admin_force_sync');

        return [
            'changed' => true,
            'message' => "Status updated: {$oldStatus} → {$localStatus}.",
        ];
    }
}
