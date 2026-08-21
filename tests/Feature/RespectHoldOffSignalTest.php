<?php

namespace Tests\Feature;

use App\Services\AI\GeminiClient;
use App\WhatsApp\AI\GeminiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * A real conversation: a tier-3 re-engagement nudge asked "check back
 * tomorrow, in a few days, or should I hold off for now?" — the customer
 * picked "Hold off for now." The very next reply was "No problem at all!
 * ... maybe checking out Instagram services or learning more about how
 * adverts work?" — a fresh pitch in the same breath as acknowledging the
 * request to stop. That reads as not having listened at all, which is worse
 * than the check-in that prompted "hold off" in the first place.
 */
class RespectHoldOffSignalTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_prompt_instructs_backing_off_fully_on_a_hold_off_signal(): void
    {
        $system = '';
        $client = Mockery::mock(GeminiClient::class);
        $client->shouldReceive('isConfigured')->andReturn(true);
        $client->shouldReceive('generateJson')->andReturnUsing(function (string $p, float $temp = 0.2, ?array $schema = null, ?string $sys = null) use (&$system) {
            $system = $sys;

            return ['reply' => 'No problem — I\'ll leave it here 👍', 'flow' => 'none', 'flow_data' => []];
        });
        $this->app->instance(GeminiClient::class, $client);

        app(GeminiProvider::class)->respond('Hold off for now', ['user' => null, 'authenticated' => true, 'history' => []]);

        $this->assertStringContainsString('request to pause ALL outreach', (string) $system);
        $this->assertStringContainsString('no pitch', (string) $system);
    }
}
