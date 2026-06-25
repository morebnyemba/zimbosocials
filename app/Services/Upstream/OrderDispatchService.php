<?php

namespace App\Services\Upstream;

use App\Models\Order;
use App\Models\ServiceUpstream;

class OrderDispatchService
{
    public function __construct(private readonly UpstreamProviderClient $client) {}

    public function dispatch(Order $order): array
    {
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
                $order->update([
                    'external_order_id' => $result['external_order_id'],
                    'status' => 'processing',
                    'push_attempts' => $attempts,
                    'pushed_to_upstream' => true,
                    'pushed_at' => now(),
                    'upstream_last_error' => null,
                    'upstream_provider_id' => $provider->id,
                ]);

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
}
