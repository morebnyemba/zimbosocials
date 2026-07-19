<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use App\Services\AI\ServiceEnricher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminServiceEnhanceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function ugly(): Service
    {
        return Service::create([
            'name' => '⚡BEST IG FOLLOWERS [HQ] ~ID3382', 'name_sn' => 'old sn', 'name_nd' => 'old nd',
            'description' => 'keep me', 'description_sn' => '',
            'category' => 'Instagram', 'type' => 'followers', 'rate' => 1.0,
            'min_qty' => 100, 'max_qty' => 10000, 'is_active' => true,
        ]);
    }

    /** Bind a deterministic enricher so the endpoint is testable without Gemini. */
    private function fakeEnricher(bool $available, array $map): void
    {
        $this->app->bind(ServiceEnricher::class, fn () => new class($available, $map) extends ServiceEnricher
        {
            public function __construct(private bool $available, private array $map) {}

            public function isAvailable(): bool
            {
                return $this->available;
            }

            public function enrich(array $services): array
            {
                return $this->map;
            }
        });
    }

    public function test_ai_enhance_updates_the_service_name_and_translations(): void
    {
        $service = $this->ugly();
        $this->fakeEnricher(true, [
            (string) $service->id => ['name' => 'Instagram Followers', 'name_sn' => 'Vateveri veInstagram', 'name_nd' => 'Abalandeli be-Instagram'],
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.services.enhance-names'), ['service_ids' => [$service->id]])
            ->assertRedirect();

        $service->refresh();
        $this->assertSame('Instagram Followers', $service->name);
        $this->assertSame('Vateveri veInstagram', $service->name_sn);
        // Curated description is left untouched.
        $this->assertSame('keep me', $service->description);
    }

    /**
     * The failure that hit production: the model flattened a whole group of
     * sibling services to one generic title. A colliding name must be refused so
     * the catalogue never ends up with six identical "Facebook Followers".
     */
    public function test_a_colliding_name_is_refused_and_the_original_kept(): void
    {
        $a = $this->ugly();
        $b = Service::create([
            'name' => 'Facebook Followers HQ 30D', 'name_sn' => '', 'name_nd' => '',
            'description' => '', 'description_sn' => '',
            'category' => 'Instagram', 'type' => 'followers', 'rate' => 2.0,
            'min_qty' => 100, 'max_qty' => 10000, 'is_active' => true,
        ]);

        // The AI returns the SAME name for both services.
        $this->fakeEnricher(true, [
            (string) $a->id => ['name' => 'Facebook Followers'],
            (string) $b->id => ['name' => 'Facebook Followers'],
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.services.enhance-names'), ['service_ids' => [$a->id, $b->id]])
            ->assertRedirect();

        // First one takes the name; the second keeps its original — never a duplicate.
        $this->assertSame('Facebook Followers', $a->fresh()->name);
        $this->assertSame('Facebook Followers HQ 30D', $b->fresh()->name);
    }

    public function test_names_are_restorable_from_the_audit_trail(): void
    {
        $service = $this->ugly();
        $original = $service->name;
        $this->fakeEnricher(true, [(string) $service->id => ['name' => 'Instagram Followers']]);

        $this->actingAs($this->admin())
            ->post(route('admin.services.enhance-names'), ['service_ids' => [$service->id]]);
        $this->assertSame('Instagram Followers', $service->fresh()->name);

        $this->artisan('services:restore-names')->assertSuccessful();

        $this->assertSame($original, $service->fresh()->name);
    }

    public function test_enhance_is_a_noop_when_ai_is_unavailable(): void
    {
        $service = $this->ugly();
        $original = $service->name;
        $this->fakeEnricher(false, []);

        $this->actingAs($this->admin())
            ->post(route('admin.services.enhance-names'), ['service_ids' => [$service->id]])
            ->assertRedirect();

        $this->assertSame($original, $service->fresh()->name);
    }
}
