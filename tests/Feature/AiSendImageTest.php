<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\AI\GeminiClient;
use App\WhatsApp\Routing\MessageRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Mockery;
use Tests\TestCase;

/**
 * The AI can attach one of the branded advert images to a standalone reply —
 * no flow required — by picking a NAME from flow_data.send_image. It never
 * sees the images themselves; the name is schema-constrained (see
 * GeminiProvider::responseSchema()) so it can only ever pick a real one.
 */
class AiSendImageTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '263771234567';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.whatsapp.api_token' => 't', 'services.whatsapp.phone_number_id' => '1', 'services.gemini.api_key' => 'k']);
        // WhatsAppGateway only treats an https:// source as a fetchable link
        // (anything else is read as an already-uploaded media id) — the
        // UrlGenerator's root is bound from APP_URL at boot, so config()
        // alone doesn't move it; force it so asset() matches what production
        // (a real https site) actually sends.
        URL::forceRootUrl('https://zimbosocials.co.zw');
        URL::forceScheme('https');
        Http::fake(fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid('', true)]]]));
        WhatsAppAccount::create(['wa_phone' => self::PHONE, 'user_id' => User::factory()->create()->id, 'link_status' => 'linked', 'opted_in' => true]);
    }

    private function respondWith(array $decision): void
    {
        $mock = Mockery::mock(GeminiClient::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('generateJson')->andReturn(array_merge([
            'reply' => 'Sharp, here you go!',
            'follow_up' => null,
            'flow' => 'none',
            'flow_data' => [],
        ], $decision));
        $this->app->instance(GeminiClient::class, $mock);

        app(MessageRouter::class)->handle([
            'from' => self::PHONE, 'wa_message_id' => 'wamid.'.uniqid('', true), 'type' => 'text',
            'text' => 'tell me about advertising', 'interactive_id' => null, 'name' => 'Tendai',
            'timestamp' => time(), 'raw' => [],
        ]);
    }

    /** Every outbound WhatsApp send whose payload sent an image with the given link fragment. */
    private function imageSendsContaining(string $needle): int
    {
        $count = 0;
        foreach (Http::recorded() as [$request, $response]) {
            /** @var Request $request */
            $body = $request->data();
            if (($body['type'] ?? null) === 'image' && str_contains((string) ($body['image']['link'] ?? ''), $needle)) {
                $count++;
            }
        }

        return $count;
    }

    private function anyImageSent(): bool
    {
        foreach (Http::recorded() as [$request, $response]) {
            /** @var Request $request */
            if (($request->data()['type'] ?? null) === 'image') {
                return true;
            }
        }

        return false;
    }

    public function test_the_ai_can_send_the_why_zimbosocials_image_standalone(): void
    {
        $this->respondWith(['flow_data' => ['send_image' => 'why_zimbosocials']]);

        $this->assertSame(1, $this->imageSendsContaining('why-zimbosocials.png'));
    }

    public function test_the_ai_can_send_a_specific_package_image_standalone(): void
    {
        $this->respondWith(['flow_data' => ['send_image' => 'week1']]);

        $this->assertSame(1, $this->imageSendsContaining('images/adverts/week1.jpg'));
    }

    public function test_none_sends_no_image(): void
    {
        $this->respondWith(['flow_data' => ['send_image' => 'none']]);

        $this->assertFalse($this->anyImageSent());
    }

    public function test_the_image_never_doubles_up_with_a_flow_that_sends_its_own(): void
    {
        // Model (incorrectly) sets both a flow and a send_image in the same
        // turn — the flow's own image must win, never both.
        $this->respondWith(['flow' => 'advertise', 'flow_data' => ['send_image' => 'why_zimbosocials']]);

        // The flow's own intro image sends (why_zimbosocials, coincidentally
        // the same graphic) but only ONCE — never the AI's plus the flow's.
        $this->assertSame(1, $this->imageSendsContaining('why-zimbosocials.png'));
    }
}
