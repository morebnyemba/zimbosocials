<?php

namespace Tests\Feature;

use App\Models\WhatsAppKnowledge;
use App\Services\AI\GeminiClient;
use App\WhatsApp\AI\GeminiProvider;
use App\WhatsApp\Intent\KnowledgeBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Three one-off services (advertising setup & training, ads account fix,
 * account recovery/unbanning) live outside both the growth catalogue and the
 * managed-campaign packages, so without their own grounding the model has
 * nothing to quote a price from — same failure mode as advert prices before
 * they were injected (see AdvertPricesInContextTest).
 */
class ExtraServicesInContextTest extends TestCase
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

    public function test_every_configured_extra_service_reaches_the_model(): void
    {
        $prompt = $this->capturePrompt('can you teach me to run my own ads?');

        $this->assertStringContainsString('ACCOUNT & ADVERTISING SUPPORT', $prompt);

        foreach ((array) config('extra_services.items') as $item) {
            $price = rtrim(rtrim(number_format((float) $item['price'], 2), '0'), '.');
            $this->assertStringContainsString((string) $item['label'], $prompt);
            $this->assertStringContainsString("\${$price}", $prompt);
        }
    }

    public function test_the_context_states_these_are_the_only_prices(): void
    {
        $prompt = $this->capturePrompt('how much to fix my ads account?');

        $this->assertStringContainsString('never invent a price', $prompt);
    }

    public function test_the_knowledge_base_entry_is_seeded_and_findable(): void
    {
        $entry = WhatsAppKnowledge::where('title', 'Account & advertising support')->first();
        $this->assertNotNull($entry, 'the account/advertising support KB entry should ship with the migration');

        foreach ((array) config('extra_services.items') as $item) {
            $price = rtrim(rtrim(number_format((float) $item['price'], 2), '0'), '.');
            $this->assertStringContainsString("\${$price}", (string) $entry->answer);
        }

        $kb = app(KnowledgeBase::class);
        foreach (['my facebook account was hacked', 'instagram account banned help', 'teach me to advertise myself'] as $query) {
            $titles = collect($kb->search($query, 3))->pluck('title')->all();
            $this->assertContains('Account & advertising support', $titles, "KB should surface this for: {$query}");
        }
    }
}
