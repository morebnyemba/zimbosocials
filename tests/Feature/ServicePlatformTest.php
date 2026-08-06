<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\UpstreamProvider;
use App\Models\User;
use App\Services\Upstream\UpstreamProviderClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Mockery;
use Tests\TestCase;

/**
 * 'platform' is a small canonical set decoupled from the free-text
 * 'category' field, so every reader (order screen, catalog) can look up an
 * icon by an exact key instead of guessing from category text. See
 * Service::inferPlatform() and Service::PLATFORMS.
 */
class ServicePlatformTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    public function test_infer_platform_matches_known_platforms(): void
    {
        $this->assertSame('instagram', Service::inferPlatform('Instagram Followers'));
        $this->assertSame('youtube', Service::inferPlatform('YouTube - Watch Hours'));
        $this->assertSame('tiktok', Service::inferPlatform('🔥 TIKTOK Views 🔥'));
        $this->assertSame('twitter', Service::inferPlatform('Twitter / X'));
        $this->assertSame('whatsapp', Service::inferPlatform('WhatsApp Channel'));
    }

    public function test_infer_platform_falls_back_to_other_for_unrecognized_text(): void
    {
        $this->assertSame('other', Service::inferPlatform('Starter Growth Pack'));
    }

    public function test_creating_a_service_without_a_platform_infers_it_from_category(): void
    {
        $this->actingAs($this->admin())->post(route('admin.services.store'), [
            'name' => 'Instagram Followers (Real)',
            'name_sn' => 'Instagram Followers (Real)',
            'category' => 'Instagram',
            'type' => 'followers',
            'rate' => 1.2,
            'min_qty' => 100,
            'max_qty' => 10000,
        ])->assertRedirect();

        $this->assertDatabaseHas('services', ['name' => 'Instagram Followers (Real)', 'platform' => 'instagram']);
    }

    public function test_an_explicit_platform_overrides_the_inferred_one(): void
    {
        $this->actingAs($this->admin())->post(route('admin.services.store'), [
            'name' => 'Cross-posted Growth Bundle',
            'name_sn' => 'Cross-posted Growth Bundle',
            'category' => 'Instagram',
            'platform' => 'tiktok',
            'type' => 'followers',
            'rate' => 1.2,
            'min_qty' => 100,
            'max_qty' => 10000,
        ])->assertRedirect();

        $this->assertDatabaseHas('services', ['name' => 'Cross-posted Growth Bundle', 'platform' => 'tiktok']);
    }

    public function test_an_invalid_platform_is_rejected(): void
    {
        $this->actingAs($this->admin())->post(route('admin.services.store'), [
            'name' => 'Bad Platform Service',
            'category' => 'Instagram',
            'platform' => 'not-a-real-platform',
            'type' => 'followers',
            'rate' => 1.2,
            'min_qty' => 100,
            'max_qty' => 10000,
        ])->assertSessionHasErrors('platform');
    }

    public function test_updating_a_service_re_infers_platform_when_left_blank(): void
    {
        $service = Service::create([
            'name' => 'Old Name', 'name_sn' => 'Old Name', 'category' => 'Instagram', 'platform' => 'tiktok',
            'type' => 'followers', 'rate' => 1.0, 'min_qty' => 100, 'max_qty' => 1000,
        ]);

        $this->actingAs($this->admin())->put(route('admin.services.update', $service), [
            'name' => 'Old Name',
            'name_sn' => 'Old Name',
            'category' => 'YouTube',
            'type' => 'followers',
            'rate' => 1.0,
            'min_qty' => 100,
            'max_qty' => 1000,
        ])->assertRedirect();

        $this->assertSame('youtube', $service->fresh()->platform);
    }

    public function test_bulk_import_from_upstream_infers_platform(): void
    {
        $provider = UpstreamProvider::create([
            'name' => 'Provider Platform Test', 'url' => 'https://example.com/api',
            'api_key' => 'secret', 'is_active' => true, 'balance' => 0,
        ]);

        $mock = Mockery::mock(UpstreamProviderClient::class);
        $mock->shouldReceive('setProvider')->once()->andReturnSelf();
        $mock->shouldReceive('getServices')->once()->andReturn([[
            'service' => '501', 'name' => 'TikTok Views', 'category' => 'TikTok', 'type' => 'views',
            'rate' => 4.0, 'min' => 100, 'max' => 10000, 'refill' => false, 'dripfeed' => true,
        ]]);
        $this->app->instance(UpstreamProviderClient::class, $mock);

        $this->actingAs($this->admin())->post(route('admin.upstream-providers.import-services', $provider), [
            'services' => [['external_service_id' => '501', 'markup_type' => 'percentage', 'markup_value' => 25]],
        ])->assertRedirect();

        $this->assertDatabaseHas('services', ['name' => 'TikTok Views', 'platform' => 'tiktok']);
    }

    public function test_orders_create_page_exposes_platform_per_service(): void
    {
        Service::create([
            'name' => 'Instagram Followers', 'name_sn' => 'Instagram Followers', 'category' => 'Instagram', 'platform' => 'instagram',
            'type' => 'followers', 'rate' => 1.0, 'min_qty' => 100, 'max_qty' => 1000, 'is_active' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('orders.create'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Orders/Create')
                ->where('services.0.platform', 'instagram')
            );
    }

    /**
     * Orders/Create groups by two independent levels: 'platforms' (the small
     * canonical set, in Service::PLATFORMS order) and each service's own
     * 'category' (whatever an admin has grouped it into). This is the fix
     * for a bug where both levels collapsed onto the same values because
     * category held platform-like text.
     */
    public function test_orders_create_page_exposes_platforms_distinct_from_categories(): void
    {
        Service::create([
            'name' => 'Instagram Followers', 'name_sn' => 'x', 'category' => 'Followers', 'platform' => 'instagram',
            'type' => 'default', 'rate' => 1.0, 'min_qty' => 100, 'max_qty' => 1000, 'is_active' => true,
        ]);
        Service::create([
            'name' => 'Instagram Likes', 'name_sn' => 'x', 'category' => 'Likes', 'platform' => 'instagram',
            'type' => 'default', 'rate' => 1.0, 'min_qty' => 100, 'max_qty' => 1000, 'is_active' => true,
        ]);
        Service::create([
            'name' => 'YouTube Views', 'name_sn' => 'x', 'category' => 'Views', 'platform' => 'youtube',
            'type' => 'default', 'rate' => 1.0, 'min_qty' => 100, 'max_qty' => 1000, 'is_active' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('orders.create'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Orders/Create')
                // Canonical order (Service::PLATFORMS), not alphabetical or insertion order.
                ->where('platforms', ['instagram', 'youtube'])
                ->has('services', 3)
            );
    }

    public function test_services_page_exposes_platform_per_service(): void
    {
        Service::create([
            'name' => 'Instagram Followers', 'name_sn' => 'Instagram Followers', 'category' => 'Instagram', 'platform' => 'instagram',
            'type' => 'followers', 'rate' => 1.0, 'min_qty' => 100, 'max_qty' => 1000, 'is_active' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('services.index'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Services')
                ->where('services.0.platform', 'instagram')
            );
    }
}
