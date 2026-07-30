<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Service;
use App\Models\UpstreamProvider;
use App\Models\User;
use App\Services\Upstream\OrderStatusSyncService;
use App\Services\Upstream\UpstreamProviderClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Upstream panels are inconsistent about numbers. An order they have not
 * started yet commonly comes back with start_count or remains as an empty
 * string rather than the field being absent.
 *
 * That has to mean "not reported". Read as zero it does two bad things: an
 * empty string in an integer column is a hard SQL error under strict mode, and
 * a zero `remains` is our signal that an order has been fully delivered.
 */
class UpstreamPartialDataTest extends TestCase
{
    use RefreshDatabase;

    private function order(): Order
    {
        $user = User::factory()->create();
        $service = Service::create([
            'name' => 'TikTok Followers', 'name_sn' => 'x', 'description' => '', 'description_sn' => '',
            'category' => 'TikTok', 'type' => 'followers', 'rate' => 10.0,
            'min_qty' => 10, 'max_qty' => 100000, 'is_active' => true,
        ]);
        $provider = UpstreamProvider::create([
            'name' => 'Panel', 'url' => 'https://panel.example/api/v2', 'api_key' => 'k', 'is_active' => true,
        ]);

        return Order::create([
            'user_id' => $user->id, 'service_id' => $service->id,
            'link' => 'https://www.tiktok.com/@someone', 'quantity' => 100,
            'charge' => 1.0, 'rate_at_order' => 10.0, 'status' => 'processing',
            'external_order_id' => '319429257', 'pushed_to_upstream' => true,
            'upstream_provider_id' => $provider->id, 'remains' => 100,
        ]);
    }

    public function test_an_unreported_remains_does_not_complete_the_order(): void
    {
        $sync = app(OrderStatusSyncService::class);

        // The exact shape that caused it: barely-started order, empty strings.
        $this->assertNotSame('completed', $sync->resolveLocalStatus([
            'status' => 'Processing', 'start_count' => '', 'remains' => '',
        ]));

        // Other ways panels say "nothing here".
        foreach (['-', 'null', 'N/A', ' '] as $junk) {
            $this->assertNotSame('completed', $sync->resolveLocalStatus([
                'status' => 'Processing', 'remains' => $junk,
            ]), "remains '{$junk}' must not be read as delivered");
        }
    }

    public function test_a_genuine_zero_still_completes_the_order(): void
    {
        $sync = app(OrderStatusSyncService::class);

        // The signal we actually rely on must keep working.
        $this->assertSame('completed', $sync->resolveLocalStatus(['status' => 'Processing', 'remains' => 0]));
        $this->assertSame('completed', $sync->resolveLocalStatus(['status' => 'Processing', 'remains' => '0']));
    }

    public function test_syncing_an_order_with_empty_counts_does_not_error(): void
    {
        $order = $this->order();

        $client = Mockery::mock(UpstreamProviderClient::class);
        $client->shouldReceive('setProvider')->andReturnSelf();
        $client->shouldReceive('getStatus')->andReturn([
            '319429257' => ['status' => 'Processing', 'start_count' => '', 'remains' => ''],
        ]);

        // Previously threw: "Incorrect integer value: '' for column start_count".
        $result = app(OrderStatusSyncService::class)->syncSingleOrder($order, $client);

        $this->assertIsArray($result);
        $order->refresh();
        $this->assertSame('processing', $order->status, 'an unstarted order must not be marked complete');
        // What we already knew is kept, rather than being zeroed.
        $this->assertSame(100, (int) $order->remains);
    }

    public function test_reported_counts_are_stored(): void
    {
        $order = $this->order();

        $client = Mockery::mock(UpstreamProviderClient::class);
        $client->shouldReceive('setProvider')->andReturnSelf();
        $client->shouldReceive('getStatus')->andReturn([
            '319429257' => ['status' => 'Processing', 'start_count' => '250', 'remains' => '40'],
        ]);

        app(OrderStatusSyncService::class)->syncSingleOrder($order, $client);

        $order->refresh();
        $this->assertSame(250, (int) $order->start_count);
        $this->assertSame(40, (int) $order->remains);
    }
}
