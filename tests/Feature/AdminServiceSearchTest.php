<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceUpstream;
use App\Models\UpstreamProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminServiceSearchTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function serviceMappedTo(string $externalId, string $name): Service
    {
        $provider = UpstreamProvider::create([
            'name' => 'P', 'url' => 'https://x.test/api', 'api_key' => 'k', 'is_active' => true, 'balance' => 0,
        ]);
        $service = Service::create([
            'name' => $name, 'name_sn' => $name, 'description' => '', 'description_sn' => '',
            'category' => 'Instagram', 'type' => 'followers', 'rate' => 1.0,
            'min_qty' => 100, 'max_qty' => 10000, 'is_active' => true,
        ]);
        ServiceUpstream::create([
            'service_id' => $service->id, 'upstream_provider_id' => $provider->id,
            'external_service_id' => $externalId, 'external_rate' => 0.5,
            'markup_type' => 'percentage', 'markup_value' => 100, 'priority' => 1, 'is_active' => true,
        ]);

        return $service;
    }

    public function test_services_can_be_searched_by_upstream_service_id(): void
    {
        $wanted = $this->serviceMappedTo('8823', 'IG Followers Fast');
        $this->serviceMappedTo('4001', 'IG Likes');

        $this->actingAs($this->admin())
            ->get(route('admin.services.index', ['search' => '8823']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Services/Index')
                ->has('services.data', 1)
                ->where('services.data.0.id', $wanted->id)
            );
    }

    public function test_partial_upstream_id_also_matches(): void
    {
        $this->serviceMappedTo('8823', 'IG Followers Fast');

        $this->actingAs($this->admin())
            ->get(route('admin.services.index', ['search' => '882']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('services.data', 1));
    }
}
