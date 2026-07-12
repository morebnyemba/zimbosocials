<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Service;
use App\Models\UpstreamProvider;
use App\Models\User;
use App\Services\Upstream\UpstreamProviderClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * User-facing on-demand order status refresh (POST /orders/{order}/sync).
 */
class OrderUserSyncTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(): Service
    {
        return Service::create([
            'name' => 'IG Followers',
            'name_sn' => 'IG Followers',
            'description' => 'Test',
            'description_sn' => 'Test',
            'category' => 'instagram',
            'type' => 'followers',
            'rate' => 10.00,
            'min_qty' => 10,
            'max_qty' => 100000,
            'is_active' => true,
            'display_order' => 1,
        ]);
    }

    private function makePushedOrder(User $user, Service $service): Order
    {
        $provider = UpstreamProvider::create([
            'name' => 'Prov',
            'url' => 'https://example.com/api',
            'api_key' => 'secret',
            'is_active' => true,
            'balance' => 0,
        ]);

        return Order::create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'link' => 'https://instagram.com/p/x',
            'quantity' => 1000,
            'charge' => 10,
            'rate_at_order' => 10,
            'status' => 'processing',
            'pushed_to_upstream' => true,
            'external_order_id' => 'EXT-1',
            'upstream_provider_id' => $provider->id,
        ]);
    }

    public function test_user_cannot_sync_someone_elses_order(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $order = $this->makePushedOrder($owner, $this->makeService());

        $this->actingAs($other)
            ->post(route('orders.sync-status', $order))
            ->assertForbidden();
    }

    public function test_sync_applies_upstream_status_to_order(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $order = $this->makePushedOrder($user, $this->makeService());

        $mock = Mockery::mock(UpstreamProviderClient::class);
        $mock->shouldReceive('setProvider')->andReturnSelf();
        $mock->shouldReceive('getStatus')->with(['EXT-1'])->andReturn([
            'EXT-1' => ['status' => 'Completed', 'remains' => 0, 'start_count' => 500],
        ]);
        $this->app->instance(UpstreamProviderClient::class, $mock);

        $this->actingAs($user)
            ->from(route('orders.show', $order))
            ->post(route('orders.sync-status', $order))
            ->assertRedirect(route('orders.show', $order));

        $this->assertSame('completed', $order->fresh()->status);
    }

    /**
     * Regression: a just-placed order's first sync often gets a plain STRING
     * error from the provider ("Incorrect order ID") because the order isn't
     * indexed upstream yet. This used to TypeError → HTTP 500 on the order
     * page's auto-sync. It must degrade to a friendly redirect instead.
     */
    public function test_first_sync_string_error_from_provider_does_not_500(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $order = $this->makePushedOrder($user, $this->makeService());

        $mock = Mockery::mock(UpstreamProviderClient::class);
        $mock->shouldReceive('setProvider')->andReturnSelf();
        $mock->shouldReceive('getStatus')->with(['EXT-1'])->andReturn([
            'EXT-1' => 'Incorrect order ID',
        ]);
        $this->app->instance(UpstreamProviderClient::class, $mock);

        $this->actingAs($user)
            ->from(route('orders.show', $order))
            ->post(route('orders.sync-status', $order))
            ->assertRedirect(route('orders.show', $order));

        $this->assertSame('processing', $order->fresh()->status); // untouched
    }

    public function test_scheduled_sync_survives_string_error_and_still_updates_others(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $service = $this->makeService();
        $fresh = $this->makePushedOrder($user, $service); // EXT-1 → string error
        $done = Order::create([
            'user_id' => $user->id, 'service_id' => $service->id,
            'link' => 'https://instagram.com/p/y', 'quantity' => 1000,
            'charge' => 10, 'rate_at_order' => 10, 'status' => 'processing',
            'pushed_to_upstream' => true, 'external_order_id' => 'EXT-2',
            'upstream_provider_id' => $fresh->upstream_provider_id,
        ]);

        $mock = Mockery::mock(UpstreamProviderClient::class);
        $mock->shouldReceive('setProvider')->andReturnSelf();
        $mock->shouldReceive('getStatus')->andReturn([
            'EXT-1' => 'Incorrect order ID',
            'EXT-2' => ['status' => 'Completed', 'remains' => 0, 'start_count' => 500],
        ]);
        $this->app->instance(UpstreamProviderClient::class, $mock);

        $this->artisan('upstream:sync-orders')->assertSuccessful();

        $this->assertSame('processing', $fresh->fresh()->status);
        $this->assertSame('completed', $done->fresh()->status); // run wasn't killed
    }

    public function test_sync_is_gated_per_order(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $order = $this->makePushedOrder($user, $this->makeService());

        $mock = Mockery::mock(UpstreamProviderClient::class);
        $mock->shouldReceive('setProvider')->once()->andReturnSelf();
        // Provider must be hit exactly once — the second request is cache-gated.
        $mock->shouldReceive('getStatus')->once()->andReturn([
            'EXT-1' => ['status' => 'In progress', 'remains' => 400],
        ]);
        $this->app->instance(UpstreamProviderClient::class, $mock);

        $this->actingAs($user)->post(route('orders.sync-status', $order));
        $this->actingAs($user)
            ->post(route('orders.sync-status', $order))
            ->assertSessionHas('info');
    }

    public function test_unpushed_order_is_not_synced(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $service = $this->makeService();

        $order = Order::create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'link' => 'https://instagram.com/p/y',
            'quantity' => 100,
            'charge' => 1,
            'rate_at_order' => 10,
            'status' => 'pending',
            'pushed_to_upstream' => false,
        ]);

        // No client mock bound — reaching upstream would fail loudly.
        $this->actingAs($user)
            ->post(route('orders.sync-status', $order))
            ->assertRedirect();

        $this->assertSame('pending', $order->fresh()->status);
    }
}
