<?php

namespace Tests\Feature;

use App\Models\PromoBundle;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Service::calculateCharge already applies bundle pricing server-side; these
 * confirm the web pages that let a customer actually SEE a deal now get the
 * bundle data too, instead of only the AI (GeminiProvider) knowing about it.
 */
class PromoBundleWebSurfaceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): Service
    {
        return Service::create([
            'name' => 'Instagram Followers', 'name_sn' => 'Instagram Vateveri',
            'description' => 'Test service', 'description_sn' => 'Test service',
            'category' => 'instagram', 'type' => 'followers',
            'rate' => 5.00, 'min_qty' => 100, 'max_qty' => 10000,
            'is_active' => true, 'display_order' => 1,
        ]);
    }

    public function test_orders_create_page_receives_the_services_promo_bundles(): void
    {
        $service = $this->service();
        PromoBundle::create(['service_id' => $service->id, 'quantity' => 3000, 'price' => 12.00, 'is_active' => true]);

        $this->actingAs(User::factory()->create())
            ->get(route('orders.create'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Orders/Create')
                ->where('services.0.promo_bundles.0.quantity', 3000)
                ->where('services.0.promo_bundles.0.price', '12.00')
            );
    }

    public function test_services_page_receives_the_services_promo_bundles(): void
    {
        $service = $this->service();
        PromoBundle::create(['service_id' => $service->id, 'quantity' => 3000, 'price' => 12.00, 'is_active' => true]);

        $this->actingAs(User::factory()->create())
            ->get(route('services.index'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Services')
                ->where('services.0.promo_bundles.0.quantity', 3000)
            );
    }
}
