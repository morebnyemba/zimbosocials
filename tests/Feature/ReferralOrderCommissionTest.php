<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Upstream\OrderDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ReferralOrderCommissionTest extends TestCase
{
    use RefreshDatabase;

    private function bindDispatchStub(): void
    {
        $mock = Mockery::mock(OrderDispatchService::class);
        $mock->shouldReceive('dispatch')->andReturn(['ok' => false]);
        $this->app->instance(OrderDispatchService::class, $mock);
    }

    public function test_no_commission_on_first_referred_order_even_if_above_threshold(): void
    {
        config([
            'services.referral.order_commission_percent' => 2.0,
            'services.referral.order_commission_min_total' => 20.0,
        ]);

        $this->bindDispatchStub();

        $referrer = User::factory()->create(['balance' => 0]);
        $referred = User::factory()->create([
            'referred_by' => $referrer->getKey(),
            'balance' => 100,
        ]);

        $service = Service::create([
            'name' => 'High Value Service',
            'name_sn' => 'High Value Service',
            'description' => 'Test',
            'description_sn' => 'Test',
            'category' => 'instagram',
            'type' => 'followers',
            'rate' => 100.00,
            'min_qty' => 10,
            'max_qty' => 100000,
            'is_active' => true,
            'display_order' => 1,
        ]);

        // charge = (300 / 1000) * 100 = 30 (> 20), but this is first order
        $this->actingAs($referred)->post('/orders', [
            'service_id' => $service->getKey(),
            'link' => 'https://instagram.com/p/firsthigh',
            'quantity' => 300,
        ]);

        $referrer->refresh();
        $this->assertEquals(0.0, (float) $referrer->balance);

        $this->assertDatabaseCount('transactions', 1); // only order_charge from referred user
    }

    public function test_commission_is_awarded_on_second_referred_order_only_if_order_total_is_above_20(): void
    {
        config([
            'services.referral.order_commission_percent' => 2.0,
            'services.referral.order_commission_min_total' => 20.0,
        ]);

        $this->bindDispatchStub();

        $referrer = User::factory()->create(['balance' => 0]);
        $referred = User::factory()->create([
            'referred_by' => $referrer->getKey(),
            'balance' => 100,
        ]);

        $service = Service::create([
            'name' => 'Tiered Service',
            'name_sn' => 'Tiered Service',
            'description' => 'Test',
            'description_sn' => 'Test',
            'category' => 'instagram',
            'type' => 'followers',
            'rate' => 100.00,
            'min_qty' => 10,
            'max_qty' => 100000,
            'is_active' => true,
            'display_order' => 1,
        ]);

        // First order (charge 1) -> no commission (first order and below threshold)
        $this->actingAs($referred)->post('/orders', [
            'service_id' => $service->getKey(),
            'link' => 'https://instagram.com/p/firstsmall',
            'quantity' => 10,
        ]);

        // Second order (charge 30) -> eligible commission 2% of 30 = 0.6
        $this->actingAs($referred)->post('/orders', [
            'service_id' => $service->getKey(),
            'link' => 'https://instagram.com/p/secondlarge',
            'quantity' => 300,
        ]);

        $referrer->refresh();
        $this->assertEquals(0.6, (float) $referrer->balance);

        $bonus = Transaction::query()
            ->where('user_id', $referrer->getKey())
            ->where('type', 'bonus')
            ->where('method', 'referral_order')
            ->first();

        $this->assertNotNull($bonus);
        $this->assertEquals(0.6, (float) $bonus->getAttribute('amount'));
    }

    public function test_no_commission_on_second_order_when_total_is_20_or_less(): void
    {
        config([
            'services.referral.order_commission_percent' => 2.0,
            'services.referral.order_commission_min_total' => 20.0,
        ]);

        $this->bindDispatchStub();

        $referrer = User::factory()->create(['balance' => 0]);
        $referred = User::factory()->create([
            'referred_by' => $referrer->getKey(),
            'balance' => 100,
        ]);

        $service = Service::create([
            'name' => 'Edge Service',
            'name_sn' => 'Edge Service',
            'description' => 'Test',
            'description_sn' => 'Test',
            'category' => 'instagram',
            'type' => 'followers',
            'rate' => 100.00,
            'min_qty' => 10,
            'max_qty' => 100000,
            'is_active' => true,
            'display_order' => 1,
        ]);

        // First order
        $this->actingAs($referred)->post('/orders', [
            'service_id' => $service->getKey(),
            'link' => 'https://instagram.com/p/firstedge',
            'quantity' => 10,
        ]);

        // Second order charge exactly 20 (qty 200, rate 100) -> not eligible because must be > 20
        $this->actingAs($referred)->post('/orders', [
            'service_id' => $service->getKey(),
            'link' => 'https://instagram.com/p/secondedge',
            'quantity' => 200,
        ]);

        $referrer->refresh();
        $this->assertEquals(0.0, (float) $referrer->balance);

        $bonusCount = Transaction::query()
            ->where('user_id', $referrer->getKey())
            ->where('type', 'bonus')
            ->where('method', 'referral_order')
            ->count();

        $this->assertSame(0, $bonusCount);
    }
}
