<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiServiceRecommendationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_recommended_services(): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        $customer = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $followerService = Service::factory()->create([
            'name' => 'Instagram Followers',
            'category' => 'instagram',
            'type' => 'followers',
            'is_active' => true,
        ]);
        $viewService = Service::factory()->create([
            'name' => 'Instagram Views',
            'category' => 'instagram',
            'type' => 'views',
            'is_active' => true,
        ]);

        Order::factory()->create([
            'user_id' => $customer->id,
            'service_id' => $followerService->id,
            'status' => 'completed',
            'quantity' => 1000,
            'charge' => 10,
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            ['id' => $viewService->id, 'reason' => 'Great complement to your recent follower order.'],
                        ]),
                    ]]],
                ]],
            ], 200),
        ]);

        $response = $this->actingAs($customer)->get(route('dashboard'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->has('recommended_services', 1)
                ->where('recommended_services.0.id', $viewService->id)
            );
    }

    public function test_dashboard_returns_empty_recommendations_when_ai_unconfigured(): void
    {
        config(['services.gemini.api_key' => null]);

        $customer = User::factory()->create(['role' => 'user', 'is_active' => true]);

        $response = $this->actingAs($customer)->get(route('dashboard'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('recommended_services', [])
            );
    }
}
