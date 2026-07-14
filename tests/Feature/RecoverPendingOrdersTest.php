<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Service;
use App\Models\UpstreamProvider;
use App\Models\User;
use App\Services\Upstream\OrderDispatchService;
use App\Services\Upstream\UpstreamProviderClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * The queue-independent safety net: no pending order stays pending forever.
 */
class RecoverPendingOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function service(): Service
    {
        $provider = UpstreamProvider::create([
            'name' => 'Prov', 'url' => 'https://example.com/api', 'api_key' => 'k',
            'is_active' => true, 'balance' => 0,
        ]);
        $service = Service::create([
            'name' => 'IG Followers', 'name_sn' => 'x', 'description' => '', 'description_sn' => '',
            'category' => 'Instagram', 'type' => 'followers', 'rate' => 10.0,
            'min_qty' => 100, 'max_qty' => 100000, 'is_active' => true,
        ]);
        \App\Models\ServiceUpstream::create([
            'service_id' => $service->id, 'upstream_provider_id' => $provider->id,
            'external_service_id' => 'EXT-1', 'external_rate' => 5.0,
            'markup_type' => 'percentage', 'markup_value' => 100, 'priority' => 1, 'is_active' => true,
        ]);

        return $service;
    }

    private function pendingOrder(User $user, Service $service, int $ageMinutes): Order
    {
        $order = Order::create([
            'user_id' => $user->id, 'service_id' => $service->id,
            'link' => 'https://instagram.com/x', 'quantity' => 1000,
            'charge' => 10, 'rate_at_order' => 10, 'status' => 'pending',
            'pushed_to_upstream' => false,
        ]);
        // Mirror what placeOrder does: the charge is recorded on the ledger, so
        // remainingRefundable() (and thus the refund) reflects real money.
        \App\Models\Transaction::create([
            'user_id' => $user->id, 'order_id' => $order->id,
            'type' => 'order_charge', 'amount' => -10,
            'balance_before' => 10, 'balance_after' => 0,
            'status' => 'completed', 'method' => 'wallet',
        ]);
        // Backdate so it's past the grace/deadline window.
        $order->forceFill(['created_at' => now()->subMinutes($ageMinutes)])->save();

        return $order;
    }

    private function mockClient(array $placeResult): void
    {
        $mock = Mockery::mock(UpstreamProviderClient::class);
        $mock->shouldReceive('setProvider')->andReturnSelf();
        $mock->shouldReceive('placeOrder')->andReturn($placeResult);
        $this->app->instance(UpstreamProviderClient::class, $mock);
        // Rebind the dispatch service so it uses the mocked client.
        $this->app->bind(OrderDispatchService::class, fn ($app) => new OrderDispatchService($app->make(UpstreamProviderClient::class)));
    }

    public function test_recovery_redispatches_a_stuck_pending_order(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $order = $this->pendingOrder($user, $this->service(), 40); // past grace, before deadline

        $this->mockClient(['ok' => true, 'message' => 'ok', 'raw' => [], 'external_order_id' => '999']);

        $this->artisan('orders:recover-pending')->assertSuccessful();

        $order->refresh();
        $this->assertTrue((bool) $order->pushed_to_upstream);
        $this->assertSame('processing', $order->status);
    }

    public function test_recovery_cancels_and_refunds_past_the_deadline(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $order = $this->pendingOrder($user, $this->service(), 200); // past 120m deadline

        // Provider keeps rejecting → un-pushable.
        $this->mockClient(['ok' => false, 'message' => 'Incorrect service ID', 'raw' => [], 'external_order_id' => null]);

        $this->artisan('orders:recover-pending')->assertSuccessful();

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertFalse((bool) $order->pushed_to_upstream);
        // Charge returned to wallet.
        $this->assertEquals(10.0, (float) $user->fresh()->balance);
        $this->assertDatabaseHas('transactions', ['order_id' => $order->id, 'type' => 'refund', 'amount' => 10]);
    }

    public function test_recovery_waits_before_the_deadline(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $order = $this->pendingOrder($user, $this->service(), 40); // past grace, before deadline

        $this->mockClient(['ok' => false, 'message' => 'temporary provider error', 'raw' => [], 'external_order_id' => null]);

        $this->artisan('orders:recover-pending')->assertSuccessful();

        // Not cancelled yet — still within the deadline window.
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_recovery_never_cancels_unknown_outcome_orders(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $order = $this->pendingOrder($user, $this->service(), 300);
        $order->update(['upstream_last_error' => 'UNKNOWN OUTCOME: connection lost mid-request']);

        // placeOrder must NOT be called for an unknown-outcome order.
        $mock = Mockery::mock(UpstreamProviderClient::class);
        $mock->shouldReceive('setProvider')->andReturnSelf();
        $mock->shouldNotReceive('placeOrder');
        $this->app->instance(UpstreamProviderClient::class, $mock);

        $this->artisan('orders:recover-pending')->assertSuccessful();

        // Left exactly as-is for manual verification; no refund.
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertEquals(0.0, (float) $user->fresh()->balance);
    }

    public function test_recovery_ignores_fresh_orders_within_grace(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $order = $this->pendingOrder($user, $this->service(), 5); // within 30m grace

        $mock = Mockery::mock(UpstreamProviderClient::class);
        $mock->shouldReceive('setProvider')->andReturnSelf();
        $mock->shouldNotReceive('placeOrder');
        $this->app->instance(UpstreamProviderClient::class, $mock);

        $this->artisan('orders:recover-pending')->assertSuccessful();

        $this->assertSame('pending', $order->fresh()->status);
    }
}
