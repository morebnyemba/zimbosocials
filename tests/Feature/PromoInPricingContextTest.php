<?php

namespace Tests\Feature;

use App\Models\PromoBundle;
use App\Models\Service;
use App\Services\AI\GeminiClient;
use App\WhatsApp\AI\GeminiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * When the assistant quotes prices, the relevant flat-price PROMO bundles must
 * travel with them — so the model can (and is told to) surface every better
 * deal instead of quoting the bare rate. This guards the CONTEXT half: the
 * bundle is in the prompt the model sees. The model's actual inclusion in its
 * reply is scored separately by whatsapp:ai-eval.
 */
class PromoInPricingContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_services_promo_bundle_is_present_in_the_ai_prompt(): void
    {
        $service = Service::create([
            'name' => 'TikTok Followers', 'name_sn' => 'x', 'description' => '', 'description_sn' => '',
            'category' => 'TikTok', 'type' => 'followers', 'rate' => 7.0,
            'min_qty' => 10, 'max_qty' => 100000, 'is_active' => true,
        ]);
        PromoBundle::create(['service_id' => $service->id, 'quantity' => 3000, 'price' => 18.00, 'is_active' => true]);

        // Capture the prompt the provider builds for a pricing question.
        $prompt = null;
        $client = Mockery::mock(GeminiClient::class);
        $client->shouldReceive('isConfigured')->andReturn(true);
        $client->shouldReceive('generateJson')->andReturnUsing(function (string $p, float $temperature = 0.2, ?array $schema = null, ?string $system = null) use (&$prompt) {
            // Catalogue + promos now ride in the cached system instruction.
            $prompt = $system."\n".$p;

            return ['reply' => 'ok', 'flow' => 'none', 'flow_data' => []];
        });
        $this->app->instance(GeminiClient::class, $client);

        app(GeminiProvider::class)->respond('how much for tiktok followers?', [
            'user' => null, 'authenticated' => true, 'history' => [],
        ]);

        $this->assertNotNull($prompt);
        // The rate AND the promo bundle both reach the model.
        $this->assertStringContainsString('TikTok Followers', $prompt);
        $this->assertStringContainsString('🔥 PROMO', $prompt);
        $this->assertStringContainsString('3,000', $prompt);
        $this->assertStringContainsString('18', $prompt);
    }
}
