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
