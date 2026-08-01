<?php

namespace Tests\Feature;

use App\Models\AiUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * AI spend has to actually reach the dashboard. The stats block above it is
 * cached for a minute, so it would be easy for this to be computed and then
 * quietly dropped — and a cost figure nobody can see is the same as not having
 * one.
 */
class AiUsageDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    public function test_spend_reaches_the_dashboard(): void
    {
        config([
            'services.gemini.rates.input' => 0.30,
            'services.gemini.rates.cached_input' => 0.075,
            'services.gemini.rates.output' => 2.50,
        ]);

        AiUsage::create([
            'day' => now()->toDateString(), 'model' => 'gemini-2.5-flash',
            'requests' => 40, 'prompt_tokens' => 400_000, 'cached_tokens' => 300_000, 'output_tokens' => 20_000,
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Dashboard')
                ->where('stats.ai.today_requests', 40)
                ->where('stats.ai.month_requests', 40)
                // 300k of 400k prompt tokens came from cache.
                ->where('stats.ai.cache_hit_percent', 75)
                ->has('stats.ai.today_cost')
                ->has('stats.ai.month_cost')
            );
    }

    public function test_a_cached_stats_block_does_not_hide_todays_spend(): void
    {
        // Warm the dashboard cache with no usage recorded yet...
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        // ...then spend some money.
        AiUsage::create([
            'day' => now()->toDateString(), 'model' => 'gemini-2.5-flash',
            'requests' => 7, 'prompt_tokens' => 1000, 'cached_tokens' => 0, 'output_tokens' => 100,
        ]);

        // It must show immediately: the whole point is noticing a jump on the
        // day, not a minute-stale zero.
        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn ($page) => $page->where('stats.ai.today_requests', 7));
    }

    public function test_yesterdays_usage_counts_to_the_month_but_not_today(): void
    {
        // Fixed to a safe mid-month date: "yesterday" needs to land in the
        // SAME calendar month as "today" for this test's premise to hold —
        // true on 364 days a year, but not on the 1st.
        $this->travelTo(\Illuminate\Support\Carbon::create(2026, 1, 15, 12));

        AiUsage::create([
            'day' => now()->subDay()->toDateString(), 'model' => 'gemini-2.5-flash',
            'requests' => 5, 'prompt_tokens' => 1000, 'cached_tokens' => 0, 'output_tokens' => 10,
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('stats.ai.today_requests', 0)
                ->where('stats.ai.month_requests', 5)
            );

        $this->travelBack();
    }

    public function test_no_usage_yet_shows_zero_rather_than_breaking(): void
    {
        Cache::flush();

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.ai.month_cost', 0)
                ->where('stats.ai.cache_hit_percent', 0)
            );
    }
}
