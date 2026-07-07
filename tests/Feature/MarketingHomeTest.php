<?php

namespace Tests\Feature;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_uses_a_capped_service_pool_for_live_activity(): void
    {
        foreach (range(1, 250) as $index) {
            Service::create([
                'name' => "Service {$index}",
                'name_sn' => "Service {$index}",
                'description' => "Description {$index}",
                'description_sn' => "Description {$index}",
                'category' => $index % 2 === 0 ? 'instagram' : 'tiktok',
                'type' => 'default',
                'rate' => 1.2500,
                'min_qty' => 100,
                'max_qty' => 10000,
                'is_active' => true,
                'is_dripfeed' => false,
                'is_refill' => false,
                'display_order' => $index,
            ]);
        }

        $response = $this->get(route('marketing.home'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Marketing/Home')
            ->has('activityServices', 80)
            ->has('categories', 2)
        );
    }
}
