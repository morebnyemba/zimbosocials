<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The admin services page caches three whole-table aggregates. They must be
 * cached as plain arrays: the database cache store serialises whatever it is
 * given, and a serialised Collection read back by a different build comes out
 * as __PHP_Incomplete_Class and takes the entire page down with a 500 until the
 * entry happens to expire.
 */
class AdminServicesCacheTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function makeServices(): void
    {
        foreach ([['Instagram', true], ['Instagram', true], ['TikTok', false]] as $i => [$category, $active]) {
            Service::create([
                'name' => "Service {$i}", 'name_sn' => 'x', 'description' => '', 'description_sn' => '',
                'category' => $category, 'type' => 'followers', 'rate' => 1.0,
                'min_qty' => 100, 'max_qty' => 100000, 'is_active' => $active,
            ]);
        }
    }

    public function test_nothing_but_plain_data_is_written_to_the_cache(): void
    {
        $this->makeServices();

        $this->actingAs($this->admin())->get(route('admin.services.index'))->assertOk();

        foreach (['categories', 'category_counts', 'active_counts'] as $key) {
            $cached = Cache::get("admin:services:{$key}:v2");
            $this->assertIsArray($cached, "admin:services:{$key} must be cached as an array, not an object");
        }
    }

    public function test_the_page_still_works_on_a_second_visit(): void
    {
        // The first visit populates the cache; the second reads it back. If the
        // cached shape is wrong, this is where it blows up.
        $this->makeServices();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.services.index'))->assertOk();

        $this->actingAs($admin)->get(route('admin.services.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Services/Index')
                ->where('stats.total', 3)
                ->where('stats.active', 2)
                ->where('stats.inactive', 1)
            );
    }

    public function test_a_poisoned_object_cache_cannot_break_the_page(): void
    {
        $this->makeServices();

        // Exactly what the old code stored, under the old keys.
        Cache::put('admin:services:categories', collect(['Instagram']), 600);
        Cache::put('admin:services:active_counts', collect([1 => 2]), 600);

        // Versioned keys mean the page never reads those again.
        $this->actingAs($this->admin())->get(route('admin.services.index'))->assertOk();
    }
}
