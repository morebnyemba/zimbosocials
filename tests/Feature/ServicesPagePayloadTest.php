<?php

namespace Tests\Feature;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The public catalogue page used to load every active service as a full model —
 * all 21 columns, including six name/description fields across three languages —
 * and serialise the lot into the page props. Against a fully synced upstream
 * catalogue that either exhausted memory (a 500) or produced a payload too big
 * to render, which is why it failed only intermittently.
 *
 * It renders a name per service and nothing else, so that is all it may ship.
 */
class ServicesPagePayloadTest extends TestCase
{
    use RefreshDatabase;

    private function makeServices(int $count): void
    {
        foreach (range(1, $count) as $i) {
            Service::create([
                'name' => "Instagram Followers #{$i}",
                'name_sn' => 'Vateveri', 'name_nd' => 'Abalandeli',
                'description' => str_repeat('long marketing copy. ', 40),
                'description_sn' => str_repeat('tsanangudzo refu. ', 40),
                'description_nd' => str_repeat('incazelo ende. ', 40),
                'category' => $i % 2 === 0 ? 'Instagram' : 'TikTok',
                'type' => 'followers', 'rate' => 1.5,
                'min_qty' => 100, 'max_qty' => 100000, 'is_active' => true,
            ]);
        }
    }

    public function test_the_page_ships_only_what_it_renders(): void
    {
        $this->makeServices(5);

        $this->get(route('marketing.services'))
            ->assertOk()
            ->assertInertia(function ($page) {
                $services = $page->toArray()['props']['services'];
                $first = collect($services)->flatten(1)->first();

                // id + name only — no descriptions, no rates, no timestamps.
                $this->assertSame(['id', 'name'], array_keys($first));
            });
    }

    public function test_descriptions_never_reach_the_browser(): void
    {
        $this->makeServices(5);

        $body = $this->get(route('marketing.services'))->assertOk()->getContent();

        // The heavy fields are what blew the payload up in the first place.
        $this->assertStringNotContainsString('long marketing copy', $body);
        $this->assertStringNotContainsString('tsanangudzo refu', $body);
    }

    public function test_a_large_catalogue_stays_a_reasonable_payload(): void
    {
        // Nothing like a fully synced panel, but enough that a regression to
        // whole models would show up loudly here.
        $this->makeServices(300);

        $body = $this->get(route('marketing.services'))->assertOk()->getContent();

        $this->assertLessThan(
            600 * 1024,
            strlen($body),
            'the catalogue page payload has grown — is it shipping full models again?'
        );
    }

    public function test_the_catalogue_is_cached_between_visits(): void
    {
        $this->makeServices(3);
        Cache::flush();

        $this->get(route('marketing.services'))->assertOk();

        $this->assertNotNull(Cache::get('marketing:services:v1'), 'the catalogue should be cached');
    }

    public function test_syncing_services_clears_the_cached_catalogue(): void
    {
        $this->makeServices(3);
        \App\Models\UpstreamProvider::create([
            'name' => 'Panel', 'url' => 'https://panel.example/api/v2',
            'api_key' => 'k', 'is_active' => true,
        ]);
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response([])]);

        $this->get(route('marketing.services'))->assertOk();
        $this->assertNotNull(Cache::get('marketing:services:v1'));

        $this->artisan('upstream:sync-services');

        // A sync must be visible immediately, not up to an hour later.
        $this->assertNull(Cache::get('marketing:services:v1'));
    }

    public function test_an_aborted_sync_leaves_the_cache_alone(): void
    {
        // No providers configured — nothing changed, so there is nothing to
        // invalidate and the page should keep serving from cache.
        $this->makeServices(3);
        $this->get(route('marketing.services'))->assertOk();

        $this->artisan('upstream:sync-services');

        $this->assertNotNull(Cache::get('marketing:services:v1'));
    }
}
