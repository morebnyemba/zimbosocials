<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceUpstream;
use App\Models\UpstreamProvider;
use App\Models\User;
use App\Services\Upstream\UpstreamProviderClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AdminUpstreamProviderImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_services_returns_importable_services_with_default_markup(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $provider = UpstreamProvider::create([
            'name' => 'Provider One',
            'url' => 'https://example.com/api',
            'api_key' => 'secret',
            'is_active' => true,
            'balance' => 0,
        ]);

        $existingService = Service::create([
            'name' => 'Instagram Followers',
            'name_sn' => 'Instagram Followers',
            'description' => '',
            'description_sn' => '',
            'category' => 'Instagram',
            'type' => 'followers',
            'rate' => 1.5000,
            'min_qty' => 100,
            'max_qty' => 5000,
            'is_active' => true,
        ]);

        ServiceUpstream::create([
            'service_id' => $existingService->id,
            'upstream_provider_id' => $provider->id,
            'external_service_id' => '101',
            'external_rate' => 1.0000,
            'priority' => 1,
            'is_active' => true,
        ]);

        $mock = Mockery::mock(UpstreamProviderClient::class);
        $mock->shouldReceive('setProvider')
            ->once()
            ->withArgs(fn (UpstreamProvider $boundProvider): bool => $boundProvider->is($provider))
            ->andReturnSelf();
        $mock->shouldReceive('getServices')->once()->andReturn([
            [
                'service' => '101',
                'name' => 'Instagram Followers',
                'category' => 'Instagram',
                'type' => 'followers',
                'rate' => 1.0,
                'min' => 100,
                'max' => 5000,
                'refill' => true,
                'dripfeed' => false,
            ],
            [
                'service' => '202',
                'name' => 'YouTube Likes',
                'category' => 'YouTube',
                'type' => 'likes',
                'rate' => 2.5,
                'min' => 50,
                'max' => 2000,
                'refill' => false,
                'dripfeed' => true,
            ],
            [
                'service' => '999',
                'name' => '----------',
                'category' => 'Divider',
                'type' => 'default',
                'rate' => 0,
            ],
        ]);
        $this->app->instance(UpstreamProviderClient::class, $mock);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.upstream-providers.available-services', $provider));

        $response->assertOk()
            ->assertJsonCount(2, 'services')
            ->assertJsonFragment([
                'external_service_id' => '101',
                'already_imported' => true,
                'existing_service_name' => 'Instagram Followers',
                'default_markup_percentage' => 20,
            ])
            ->assertJsonFragment([
                'external_service_id' => '202',
                'already_imported' => false,
                'default_markup_percentage' => 20,
            ]);
    }

    public function test_import_services_only_imports_selected_services_with_markup_percentage(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $provider = UpstreamProvider::create([
            'name' => 'Provider Two',
            'url' => 'https://example.com/api',
            'api_key' => 'secret',
            'is_active' => true,
            'balance' => 0,
        ]);

        $mock = Mockery::mock(UpstreamProviderClient::class);
        $mock->shouldReceive('setProvider')
            ->once()
            ->withArgs(fn (UpstreamProvider $boundProvider): bool => $boundProvider->is($provider))
            ->andReturnSelf();
        $mock->shouldReceive('getServices')->once()->andReturn([
            [
                'service' => '501',
                'name' => 'TikTok Views',
                'desc' => 'Fast delivery',
                'category' => 'TikTok',
                'type' => 'views',
                'rate' => 4.0,
                'min' => 100,
                'max' => 10000,
                'refill' => false,
                'dripfeed' => true,
            ],
            [
                'service' => '777',
                'name' => 'Twitter Retweets',
                'desc' => 'Steady refill support',
                'category' => 'Twitter',
                'type' => 'retweets',
                'rate' => 3.0,
                'min' => 50,
                'max' => 5000,
                'refill' => true,
                'dripfeed' => false,
            ],
        ]);
        $this->app->instance(UpstreamProviderClient::class, $mock);

        $response = $this->actingAs($admin)->post(route('admin.upstream-providers.import-services', $provider), [
            'services' => [
                [
                    'external_service_id' => '501',
                    'markup_percentage' => 25,
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('services', [
            'name' => 'TikTok Views',
            'category' => 'TikTok',
            'type' => 'views',
            'rate' => 5.0000,
        ]);

        $service = Service::where('name', 'TikTok Views')->firstOrFail();

        $this->assertDatabaseHas('service_upstreams', [
            'service_id' => $service->id,
            'upstream_provider_id' => $provider->id,
            'external_service_id' => '501',
            'external_rate' => 4.0000,
        ]);

        $this->assertDatabaseMissing('services', [
            'name' => 'Twitter Retweets',
        ]);
    }
}
