<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\UpstreamProvider;
use App\Models\User;
use App\Services\Upstream\UpstreamProviderClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

/**
 * Refill, drip-feed and withdrawal-confirmation feature coverage.
 */
class FeatureGapsTest extends TestCase
{
    use RefreshDatabase;

    private function service(array $attrs = []): Service
    {
        return Service::factory()->create(array_merge([
            'is_active' => true,
            'category' => 'Instagram',
            'rate' => 10.0,
            'min_qty' => 10,
            'max_qty' => 100000,
        ], $attrs));
    }

    // ─── Refill ──────────────────────────────────────────────────────────────

    public function test_user_can_request_refill_for_completed_refill_order(): void
    {
        $user = User::factory()->create();
        $service = $this->service(['is_refill' => true]);
        $provider = UpstreamProvider::create([
            'name' => 'Prov', 'url' => 'https://example.com/api', 'api_key' => 'k', 'is_active' => true, 'balance' => 0,
        ]);
        $order = Order::create([
            'user_id' => $user->id, 'service_id' => $service->id,
            'link' => 'https://instagram.com/p/x', 'quantity' => 1000, 'charge' => 10, 'rate_at_order' => 10,
            'status' => 'completed', 'pushed_to_upstream' => true,
            'external_order_id' => 'EXT-9', 'upstream_provider_id' => $provider->id,
        ]);

        $mock = Mockery::mock(UpstreamProviderClient::class);
        $mock->shouldReceive('setProvider')->andReturnSelf();
        $mock->shouldReceive('requestRefill')->once()->with('EXT-9')
            ->andReturn(['ok' => true, 'refill_id' => 'R-1', 'message' => 'Refill requested.']);
        $this->app->instance(UpstreamProviderClient::class, $mock);

        $this->actingAs($user)
            ->post(route('orders.refill', $order))
            ->assertRedirect()
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertNotNull($order->refill_requested_at);
        $this->assertSame('R-1', $order->external_refill_id);
    }

    public function test_refill_rejected_for_non_refill_service(): void
    {
        $user = User::factory()->create();
        $service = $this->service(['is_refill' => false]);
        $order = Order::create([
            'user_id' => $user->id, 'service_id' => $service->id,
            'link' => 'https://instagram.com/p/y', 'quantity' => 100, 'charge' => 1, 'rate_at_order' => 10,
            'status' => 'completed',
        ]);

        $this->actingAs($user)
            ->post(route('orders.refill', $order))
            ->assertSessionHas('error');
    }

    public function test_refill_gated_within_24_hours(): void
    {
        $user = User::factory()->create();
        $service = $this->service(['is_refill' => true]);
        $provider = UpstreamProvider::create([
            'name' => 'Prov', 'url' => 'https://example.com/api', 'api_key' => 'k', 'is_active' => true, 'balance' => 0,
        ]);
        $order = Order::create([
            'user_id' => $user->id, 'service_id' => $service->id,
            'link' => 'https://instagram.com/p/z', 'quantity' => 100, 'charge' => 1, 'rate_at_order' => 10,
            'status' => 'completed', 'pushed_to_upstream' => true,
            'external_order_id' => 'EXT-10', 'upstream_provider_id' => $provider->id,
            'refill_requested_at' => now()->subHours(2),
        ]);

        // No client mock — reaching upstream would fail loudly.
        $this->actingAs($user)
            ->post(route('orders.refill', $order))
            ->assertSessionHas('error');
    }

    // ─── Drip-feed ───────────────────────────────────────────────────────────

    public function test_dripfeed_order_charges_total_quantity(): void
    {
        $user = User::factory()->create(['balance' => 100]);
        $service = $this->service(['is_dripfeed' => true, 'rate' => 10.0]);

        $mock = Mockery::mock(\App\Services\Upstream\OrderDispatchService::class);
        $mock->shouldReceive('dispatch')->andReturn(['ok' => false, 'message' => 'stub']);
        $this->app->instance(\App\Services\Upstream\OrderDispatchService::class, $mock);

        // 500 per run × 4 runs = 2000 total → (2000/1000) × $10 = $20
        $this->actingAs($user)->post('/orders', [
            'service_id' => $service->id,
            'link' => 'https://instagram.com/p/drip',
            'quantity' => 500,
            'runs' => 4,
            'interval' => 30,
        ])->assertRedirect();

        $order = Order::latest('id')->firstOrFail();
        $this->assertSame(4, (int) $order->runs);
        $this->assertSame(30, (int) $order->interval_minutes);
        $this->assertSame(2000, $order->totalQuantity());
        $this->assertEquals(20.0, (float) $order->charge);
        $this->assertEquals(80.0, (float) $user->fresh()->balance);
    }

    public function test_dripfeed_rejected_for_non_dripfeed_service(): void
    {
        $user = User::factory()->create(['balance' => 100]);
        $service = $this->service(['is_dripfeed' => false]);

        $this->actingAs($user)->post('/orders', [
            'service_id' => $service->id,
            'link' => 'https://instagram.com/p/nodrip',
            'quantity' => 500,
            'runs' => 4,
            'interval' => 30,
        ])->assertSessionHasErrors(['runs']);

        $this->assertDatabaseCount('orders', 0);
    }

    // ─── Withdrawal confirmation code ────────────────────────────────────────

    public function test_withdrawal_requires_valid_email_code(): void
    {
        Mail::fake();
        $user = User::factory()->create(['role' => 'marketer', 'balance' => 50]);

        // Without a code → validation error, money untouched
        $this->actingAs($user)->post(route('wallet.withdraw'), [
            'amount' => 20, 'method' => 'ecocash', 'reference' => '0771234567',
        ])->assertSessionHasErrors(['code']);
        $this->assertEquals(50.0, (float) $user->fresh()->balance);

        // Send a code, overwrite with a known one, then withdraw
        $this->actingAs($user)->post(route('wallet.withdraw.send-code'))->assertSessionHas('success');
        Cache::put("withdraw:code:{$user->id}", hash('sha256', '654321'), now()->addMinutes(10));

        $this->actingAs($user)->post(route('wallet.withdraw'), [
            'amount' => 20, 'method' => 'ecocash', 'reference' => '0771234567', 'code' => '654321',
        ])->assertSessionHas('success');

        $this->assertEquals(30.0, (float) $user->fresh()->balance);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id, 'type' => 'withdrawal', 'status' => 'pending',
        ]);
    }

    public function test_wrong_withdrawal_code_is_rejected(): void
    {
        Mail::fake();
        $user = User::factory()->create(['role' => 'marketer', 'balance' => 50]);

        $this->actingAs($user)->post(route('wallet.withdraw.send-code'));

        $this->actingAs($user)->post(route('wallet.withdraw'), [
            'amount' => 20, 'method' => 'ecocash', 'reference' => '0771234567', 'code' => '000000',
        ])->assertSessionHasErrors(['code']);

        $this->assertEquals(50.0, (float) $user->fresh()->balance);
        $this->assertDatabaseCount('transactions', 0);
    }
}
