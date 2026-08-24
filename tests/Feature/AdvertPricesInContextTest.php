<?php

namespace Tests\Feature;

use App\Services\AI\GeminiClient;
use App\WhatsApp\AI\GeminiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Advert packages are priced in config, not in the service catalogue, so they
 * were never injected into the AI's context. Asked about advert pricing outside
 * the menu flow the model had nothing to ground on and invented figures —
 * quoting $12/$25/$45/$80 against real packages of $10/$20/$35/$60, in the same
 * conversation where the menu had just shown the correct ones.
 */
class AdvertPricesInContextTest extends TestCase
{
    use RefreshDatabase;

    private function capturePrompt(string $message): string
    {
        $prompt = '';
        $client = Mockery::mock(GeminiClient::class);
        $client->shouldReceive('isConfigured')->andReturn(true);
        // Static grounding (catalogue, advert packages, support items, payment
        // details) rides in the cached SYSTEM instruction; per-user context
        // rides in the user turn. What the model sees is both, so assert on both.
        $client->shouldReceive('generateJson')->andReturnUsing(
            function (string $p, float $temperature = 0.2, ?array $schema = null, ?string $system = null) use (&$prompt) {
                $prompt = $system."\n".$p;

                return ['reply' => 'ok', 'flow' => 'none', 'flow_data' => []];
            }
        );
        $this->app->instance(GeminiClient::class, $client);

        app(GeminiProvider::class)->respond($message, ['user' => null, 'authenticated' => true, 'history' => []]);

        return $prompt;
    }

    public function test_every_configured_package_reaches_the_model(): void
    {
        $prompt = $this->capturePrompt('how much are your adverts?');

        $this->assertStringContainsString('SPONSORED ADVERT PACKAGES', $prompt);

        foreach ((array) config('adverts.packages') as $key => $package) {
            $price = rtrim(rtrim(number_format((float) $package['price'], 2), '0'), '.');
            $this->assertStringContainsString("key={$key}", $prompt, "package {$key} missing from context");
            $this->assertStringContainsString("\${$price}", $prompt, "price for {$key} missing from context");
        }
    }

    public function test_the_context_states_these_are_the_only_prices(): void
    {
        $prompt = $this->capturePrompt('what do adverts cost?');

        // Without this the model treated the figures as a starting point.
        $this->assertStringContainsString('Never invent an advert price', $prompt);
    }

    public function test_video_inclusion_is_stated_per_package(): void
    {
        $prompt = $this->capturePrompt('do adverts come with a video?');

        // Which tiers include the AI video is a real commercial difference, and
        // the model was previously guessing it too.
        $this->assertStringContainsString('includes an AI video advert', $prompt);
        $this->assertStringContainsString('boost only, no video', $prompt);
    }
}
