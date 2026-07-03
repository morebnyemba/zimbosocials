<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\UpstreamProvider;
use App\Services\Upstream\OrderStatusSyncService;
use App\Services\Upstream\UpstreamProviderClient;
use Illuminate\Console\Command;

class SyncUpstreamOrders extends Command
{
    protected $signature = 'upstream:sync-orders';

    protected $description = 'Syncs status of pending/processing orders from the upstream providers';

    public function handle(UpstreamProviderClient $client, OrderStatusSyncService $syncService): int
    {
        // 'in_progress' (with underscore) is the real orders.status enum value —
        // this previously filtered on 'inprogress' (no underscore), a typo that
        // never matched, silently excluding any order sitting at in_progress
        // from the scheduled sync forever (Force Sync worked on the same order
        // because it has no status pre-filter at all).
        $orders = Order::where('pushed_to_upstream', true)
            ->whereNotNull('external_order_id')
            ->whereNotNull('upstream_provider_id')
            ->whereIn('status', ['pending', 'processing', 'in_progress'])
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No active orders to sync.');

            return self::SUCCESS;
        }

        $ordersByProvider = $orders->groupBy('upstream_provider_id');
        $updatedCount = 0;

        foreach ($ordersByProvider as $providerId => $providerOrders) {
            $provider = UpstreamProvider::find($providerId);

            if (! $provider || ! $provider->is_active) {
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
                    if (! $order) {
                        continue;
                    }

                    $localStatus = $syncService->resolveLocalStatus($data);

                    if ($localStatus && $localStatus !== $order->status) {
                        $syncService->applyStatusUpdate($order, $localStatus, $data, 'scheduled_sync');
                        $updatedCount++;
                    } elseif ($order->status === 'processing') {
                        // Update start_count and remains silently if no status change
                        $order->update([
                            'start_count' => $data['start_count'] ?? $order->start_count,
                            'remains' => $data['remains'] ?? $order->remains,
                        ]);
                    }
                }
            }
        }

        $this->info("Successfully synced orders. Updated: {$updatedCount}");

        return self::SUCCESS;
    }
}
