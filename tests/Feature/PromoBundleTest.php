<?php

namespace Tests\Feature;

use App\Models\PromoBundle;
use App\Models\Service;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Upstream\OrderDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A promo bundle is a flat price for an exact quantity ("3,000 for $12").
 * It is applied inside Service::calculateCharge, which every path uses — the
 * quote, the confirm card and the wallet debit — so they can never disagree.
 */
class PromoBundleTest extends TestCase
{
    use RefreshDatabase;

    private function service(float $rate = 5.0): Service
    {
        return Service::create([
            'name' => 'Facebook Followers', 'name_sn' => 'x', 'description' => '', 'description_sn' => '',
            'category' => 'Facebook', 'type' => 'followers', 'rate' => $rate,
            'min_qty' => 100, 'max_qty' => 100000, 'is_active' => true,
        ]);
    }

    private function bundle(Service $s, int $qty, float $price): PromoBundle
    {
        return PromoBundle::create(['service_id' => $s->id, 'quantity' => $qty, 'price' => $price, 'is_active' => true]);
    }

    public function test_the_bundle_price_replaces_the_rate_at_that_exact_quantity(): void
    {
        $service = $this->service(rate: 5.0);          // 3,000 would be $15
        $this->bundle($service, 3000, 12.00);

        $this->assertSame(12.0, $service->calculateCharge(3000));
    }

    public function test_other_quantities_still_use_the_normal_rate(): void
    {
        $service = $this->service(rate: 5.0);
        $this->bundle($service, 3000, 12.00);

        // One short of, and one over, the bundle quantity — normal rate applies.
        $this->assertSame(10.0, $service->calculateCharge(2000));
        $this->assertSame(20.0, $service->calculateCharge(4000));
        $this->assertSame(14.995, $service->calculateCharge(2999));
    }

    public function test_an_inactive_bundle_is_ignored(): void
    {
        $service = $this->service(rate: 5.0);
        $this->bundle($service, 3000, 12.00)->update(['is_active' => false]);

        $this->assertSame(15.0, $service->calculateCharge(3000));
    }

    public function test_the_customer_is_actually_charged_the_bundle_price(): void
    {
        $service = $this->service(rate: 5.0);
        $this->bundle($service, 3000, 12.00);
        $user = User::factory()->create(['balance' => 100]);

        $result = app(OrderService::class)->placeOrder(
            $user, $service, 'https://facebook.com/jane', 3000, app(OrderDispatchService::class), 'test'
        );

        $this->assertTrue($result['ok']);
        $this->assertSame(12.0, (float) $result['order']->charge, 'the wallet debit must match the promo');
        $this->assertSame(88.0, (float) $user->fresh()->balance);
    }

    public function test_a_bundle_that_is_not_a_discount_is_rejected(): void
    {
        $service = $this->service(rate: 5.0); // 3,000 normally costs $15
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('admin.services.bundles.store'), [
                'service_id' => $service->id, 'quantity' => 3000, 'price' => 20.00,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('promo_bundles', 0);
    }
}
