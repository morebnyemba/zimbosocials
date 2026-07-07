<?php

namespace App\Services\Upstream;

use App\Models\Order;
use App\Models\UpstreamProvider;
use Illuminate\Support\Facades\Http;

class UpstreamProviderClient
{
    protected ?UpstreamProvider $provider;

    public function __construct(?UpstreamProvider $provider = null)
    {
        $this->provider = $provider;
    }

    public function setProvider(UpstreamProvider $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function placeOrder(Order $order, string $providerServiceId): array
    {
        if (! $this->provider || ! $this->provider->is_active) {
            return [
                'ok' => false,
                'message' => 'Upstream provider is missing or inactive.',
                'raw' => null,
                'external_order_id' => null,
            ];
        }

        $url = $this->provider->url;
        $key = $this->provider->api_key;

        if (empty($url) || empty($key)) {
            return [
                'ok' => false,
                'message' => 'Missing upstream provider credentials.',
                'raw' => null,
                'external_order_id' => null,
            ];
        }

        $payload = [
            'key' => $key,
            'action' => 'add',
            'service' => $providerServiceId,
            'link' => $order->link,
            'quantity' => $order->quantity,
        ];

        // Drip-feed (standard SMM panel API): quantity per run + runs + interval
        if ((int) $order->runs > 1) {
            $payload['runs'] = (int) $order->runs;
            $payload['interval'] = (int) $order->interval_minutes;
        }

        try {
            $response = Http::timeout((int) config('upstream.timeout', 20))
                ->asForm()
                ->post($url, $payload);

            $body = $response->json();

            if (! $response->ok()) {
                return [
                    'ok' => false,
                    'message' => 'HTTP '.$response->status(),
                    'raw' => $body,
                    'external_order_id' => null,
                ];
            }

            if (is_array($body) && isset($body['order'])) {
                return [
                    'ok' => true,
                    'message' => 'Order pushed successfully.',
                    'raw' => $body,
                    'external_order_id' => (string) $body['order'],
                ];
            }

            $errorMessage = is_array($body) && isset($body['error'])
                ? (string) $body['error']
                : 'Unexpected upstream response.';

            return [
                'ok' => false,
                'message' => $errorMessage,
                'raw' => $body,
                'external_order_id' => null,
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // The connection died mid-request (timeout, reset). The provider
            // MAY have accepted the order before we lost them — retrying or
            // failing over blindly could purchase the same delivery twice.
            return [
                'ok' => false,
                'unknown' => true,
                'message' => 'Connection lost mid-request — the provider may have received the order. Manual verification required before any retry.',
                'raw' => null,
                'external_order_id' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'raw' => null,
                'external_order_id' => null,
            ];
        }
    }

    /**
     * Request a refill for a delivered order (standard SMM panel API:
     * action=refill). Returns ['ok' => bool, 'refill_id' => ?string, 'message' => string].
     */
    public function requestRefill(string $externalOrderId): array
    {
        if (! $this->provider || ! $this->provider->is_active) {
            return ['ok' => false, 'refill_id' => null, 'message' => 'Upstream provider is missing or inactive.'];
        }

        try {
            $response = Http::timeout((int) config('upstream.timeout', 20))
                ->asForm()
                ->post($this->provider->url, [
                    'key' => $this->provider->api_key,
                    'action' => 'refill',
                    'order' => $externalOrderId,
                ]);

            $body = $response->json();

            if ($response->ok() && is_array($body) && isset($body['refill'])) {
                return ['ok' => true, 'refill_id' => (string) $body['refill'], 'message' => 'Refill requested.'];
            }

            $error = is_array($body) && isset($body['error'])
                ? (string) $body['error']
                : 'Unexpected upstream response (HTTP '.$response->status().').';

            return ['ok' => false, 'refill_id' => null, 'message' => $error];
        } catch (\Throwable $e) {
            return ['ok' => false, 'refill_id' => null, 'message' => $e->getMessage()];
        }
    }

    public function getStatus(array $orderIds): array
    {
        if (! $this->provider || empty($orderIds)) {
            return [];
        }

        $url = $this->provider->url;
        $key = $this->provider->api_key;

        try {
            $response = Http::timeout((int) config('upstream.timeout', 20))
                ->asForm()
                ->post($url, [
                    'key' => $key,
                    'action' => 'status',
                    'orders' => implode(',', $orderIds),
                ]);

            if ($response->ok() && is_array($response->json())) {
                return $response->json();
            }

            return [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getServices(): array
    {
        if (! $this->provider) {
            return [];
        }

        $url = $this->provider->url;
        $key = $this->provider->api_key;

        try {
            $response = Http::timeout((int) config('upstream.timeout', 20))
                ->asForm()
                ->post($url, [
                    'key' => $key,
                    'action' => 'services',
                ]);

            if ($response->ok() && is_array($response->json())) {
                return $response->json();
            }

            return [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getBalance(): ?float
    {
        if (! $this->provider) {
            return null;
        }

        $url = $this->provider->url;
        $key = $this->provider->api_key;

        try {
            $response = Http::timeout((int) config('upstream.timeout', 20))
                ->asForm()
                ->post($url, [
                    'key' => $key,
                    'action' => 'balance',
                ]);

            if ($response->ok() && is_array($response->json())) {
                $data = $response->json();
                if (isset($data['balance'])) {
                    return (float) $data['balance'];
                }
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
