<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Transaction;
use App\Models\UpstreamProvider;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\Upstream\UpstreamProviderClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncUpstreamOrders extends Command
{
    protected $signature = 'upstream:sync-orders';
    protected $description = 'Syncs status of pending/processing orders from the upstream providers';

    public function handle(UpstreamProviderClient $client): int
    {
        $orders = Order::where('pushed_to_upstream', true)
            ->whereNotNull('external_order_id')
            ->whereNotNull('upstream_provider_id')
            ->whereIn('status', ['pending', 'processing', 'inprogress'])
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No active orders to sync.');
            return self::SUCCESS;
        }

        $ordersByProvider = $orders->groupBy('upstream_provider_id');
        $updatedCount = 0;

        foreach ($ordersByProvider as $providerId => $providerOrders) {
            $provider = UpstreamProvider::find($providerId);
            
            if (!$provider || !$provider->is_active) {
                $this->warn("Provider #{$providerId} is inactive or missing. Skipping its orders.");
                continue;
            }

            $client->setProvider($provider);
            $chunks = $providerOrders->chunk(100);

            foreach ($chunks as $chunk) {
                $orderMap = $chunk->keyBy('external_order_id');
                $externalIds = $orderMap->keys()->toArray();

                $statuses = $client->getStatus($externalIds);

                if (empty($statuses)) {
                    $this->error("Failed to fetch status for chunk from provider #{$provider->id}.");
                    continue;
                }

                foreach ($statuses as $externalId => $data) {
                    if (isset($data['error'])) {
                        continue; // e.g. "Incorrect order ID"
                    }

                    $order = $orderMap->get($externalId);
                    if (!$order) {
                        continue;
                    }

                    $upstreamStatus = strtolower($data['status'] ?? '');
                    
                    // Map upstream status to local status
                    $localStatus = match ($upstreamStatus) {
                        'pending', 'in progress', 'inprogress' => 'processing',
                        'processing' => 'processing',
                        'completed' => 'completed',
                        'partial' => 'partial',
                        'canceled', 'cancelled' => 'canceled',
                        default => null,
                    };

                    if ($localStatus && $localStatus !== $order->status) {
                        $this->processOrderUpdate($order, $localStatus, $data);
                        $updatedCount++;
                    } else if ($order->status === 'processing') {
                        // Update start_count and remains silently if no status change
                        $order->update([
                            'start_count' => $data['start_count'] ?? $order->start_count,
                            'remains'     => $data['remains'] ?? $order->remains,
                        ]);
                    }
                }
            }
        }

        $this->info("Successfully synced orders. Updated: {$updatedCount}");
        return self::SUCCESS;
    }

    private function processOrderUpdate(Order $order, string $newStatus, array $upstreamData): void
    {
        DB::transaction(function () use ($order, $newStatus, $upstreamData) {
            $oldStatus = $order->status;
            $user = User::find($order->user_id);
            $charge = (float) $order->charge;
            $remains = (int) ($upstreamData['remains'] ?? 0);
            $startCount = (int) ($upstreamData['start_count'] ?? $order->start_count);
            
            $refundAmount = 0;

            if ($newStatus === 'canceled') {
                $refundAmount = $charge;
            } elseif ($newStatus === 'partial') {
                // Refund proportional to remains
                if ($order->quantity > 0 && $remains > 0) {
                    $refundAmount = round(($remains / $order->quantity) * $charge, 4);
                }
            }

            // Update order
            $order->update([
                'status' => $newStatus,
                'start_count' => $startCount,
                'remains' => $remains,
            ]);

            // Process refund if needed
            if ($refundAmount > 0 && $user) {
                $user->increment('balance', $refundAmount);
                
                Transaction::create([
                    'user_id' => $user->id,
                    'type' => 'refund',
                    'amount' => $refundAmount,
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
                // Just a normal status change notification
                NotificationService::notify(
                    $user->id,
                    'order_status_changed',
                    "Order #{$order->id} Updated",
                    "Your order #{$order->id} is now {$newStatus}.",
                    ['order_id' => $order->id, 'status' => $newStatus, 'service_name' => $order->service->name, 'quantity' => $order->quantity]
                );
            }

            Log::info("Order #{$order->id} synced: {$oldStatus} -> {$newStatus}. Refund: \${$refundAmount}");
        });
    }
}
