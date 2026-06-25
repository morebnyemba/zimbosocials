<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Models\ServiceUpstream;
use App\Models\UpstreamProvider;
use App\Services\Upstream\UpstreamProviderClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncUpstreamServices extends Command
{
    protected $signature = 'upstream:sync-services';

    protected $description = 'Syncs service prices and constraints from all active upstream providers';

    public function handle(UpstreamProviderClient $client): int
    {
        $providers = UpstreamProvider::where('is_active', true)->get();

        if ($providers->isEmpty()) {
            $this->warn('No active upstream providers found. Aborting sync.');

            return self::SUCCESS;
        }

        $profitMargin = (float) config('upstream.profit_margin', 1.20); // Default 20% margin
        $updatedCount = 0;

        foreach ($providers as $provider) {
            $this->info("Fetching services from provider: {$provider->name}...");
            $client->setProvider($provider);
            $upstreamServices = $client->getServices();

            if (empty($upstreamServices)) {
                $this->error("Failed to fetch services from {$provider->name}.");

                continue;
            }

            // Key the upstream services by their ID
            $upstreamMap = [];
            foreach ($upstreamServices as $us) {
                if (isset($us['service'])) {
                    $upstreamMap[(string) $us['service']] = $us;
                }
            }

            // Fetch pivot records linked to this provider
            $pivots = ServiceUpstream::with('service')->where('upstream_provider_id', $provider->id)->get();

            foreach ($pivots as $pivot) {
                $externalId = $pivot->external_service_id;

                if (! isset($upstreamMap[$externalId])) {
                    $this->warn("Provider {$provider->name} missing external ID {$externalId}. Disabling pivot.");
                    $pivot->update(['is_active' => false]);

                    continue;
                }

                $usData = $upstreamMap[$externalId];
                $upstreamRate = (float) ($usData['rate'] ?? 0);

                if ($upstreamRate <= 0) {
                    continue;
                }

                // Update the external_rate on the pivot table
                if ($pivot->external_rate != $upstreamRate) {
                    $pivot->update(['external_rate' => $upstreamRate]);
                }

                // If this is the highest priority (lowest number) active upstream for the service, update the main service price
                $service = $pivot->service;
                $primaryUpstream = $service->upstreams()->first();

                if ($primaryUpstream && $primaryUpstream->id === $pivot->id) {
                    $newRate = round($upstreamRate * $profitMargin, 4);

                    $updates = [
                        'rate' => $newRate,
                        'min_qty' => (int) ($usData['min'] ?? $service->min_qty),
                        'max_qty' => (int) ($usData['max'] ?? $service->max_qty),
                        'is_refill' => (bool) ($usData['refill'] ?? $service->is_refill),
                        'is_dripfeed' => (bool) ($usData['dripfeed'] ?? $service->is_dripfeed),
                    ];

                    $changed = false;
                    foreach ($updates as $key => $val) {
                        if ($service->{$key} != $val) {
                            $changed = true;
                            break;
                        }
                    }

                    if ($changed) {
                        $service->update($updates);
                        $updatedCount++;
                        Log::info("Synced service #{$service->id} via Provider {$provider->name}: New rate \${$newRate}");
                    }
                }
            }
        }

        $this->info("Successfully synced services. Updated: {$updatedCount}");

        return self::SUCCESS;
    }
}
