<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceUpstream;
use App\Models\UpstreamProvider;
use App\Services\Upstream\UpstreamProviderClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SyncUpstreamServicesMarkupTest extends TestCase
{
    use RefreshDatabase;

    private function makeProviderReturning(array $services): UpstreamProvider
    {
        $provider = UpstreamProvider::create([
            'name' => 'Provider',
            'url' => 'https://example.com/api',
            'api_key' => 'secret',
            'is_active' => true,
            'balance' => 0,
        ]);

        $mock = Mockery::mock(UpstreamProviderClient::class);
        $mock->shouldReceive('setProvider')->andReturnSelf();
        $mock->shouldReceive('getServices')->andReturn($services);
        $this->app->instance(UpstreamProviderClient::class, $mock);

        return $provider;
    }

    public function test_sync_preserves_high_per_service_markup_instead_of_resetting_to_20_percent(): void
    {
        $provider = $this->makeProviderReturning([
            ['service' => '101', 'rate' => 1.0, 'min' => 100, 'max' => 5000],
        ]);

        // Service imported at a 150% markup → sell rate 2.50 on a 1.00 cost.
        $service = Service::create([
            'name' => 'Instagram Followers',
            'name_sn' => 'Instagram Followers',
            'description' => '',
            'description_sn' => '',
            'category' => 'Instagram',
            'type' => 'followers',
            'rate' => 2.5000,
            'min_qty' => 100,
            'max_qty' => 5000,
            'is_active' => true,
        ]);

        ServiceUpstream::create([
            'service_id' => $service->id,
            'upstream_provider_id' => $provider->id,
            'external_service_id' => '101',
            'external_rate' => 1.0000,
            'markup_type' => 'percentage',
            'markup_value' => 150,
            'priority' => 1,
            'is_active' => true,
        ]);

        $this->artisan('upstream:sync-services')->assertSuccessful();

        // Upstream cost unchanged (1.00) → the 150% markup must hold, NOT drop to 1.20.
        $this->assertEquals(2.5000, (float) $service->fresh()->rate);
    }

    public function test_sync_reapplies_markup_when_upstream_cost_changes(): void
    {
        // Upstream cost drops from 1.00 to 0.80.
        $provider = $this->makeProviderReturning([
            ['service' => '101', 'rate' => 0.8, 'min' => 100, 'max' => 5000],
        ]);

        $service = Service::create([
            'name' => 'Instagram Followers',
            'name_sn' => 'Instagram Followers',
            'description' => '',
            'description_sn' => '',
            'category' => 'Instagram',
            'type' => 'followers',
            'rate' => 2.5000,
            'min_qty' => 100,
            'max_qty' => 5000,
            'is_active' => true,
        ]);

        ServiceUpstream::create([
            'service_id' => $service->id,
            'upstream_provider_id' => $provider->id,
            'external_service_id' => '101',
            'external_rate' => 1.0000,
            'markup_type' => 'percentage',
            'markup_value' => 150,
            'priority' => 1,
            'is_active' => true,
        ]);

        $this->artisan('upstream:sync-services')->assertSuccessful();

        // 0.80 cost × (1 + 150%) = 2.00 — margin preserved, price tracks cost down.
        $this->assertEquals(2.0000, (float) $service->fresh()->rate);
    }
}
