<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppNotification;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Marketing Messages (MM Lite) is Meta's delivery-optimised path for marketing
 * templates. Same payload, different endpoint, and it must be enabled on the
 * WABA — so it is opt-in and always falls back to the standard endpoint rather
 * than losing a send.
 */
class MarketingMessagesLiteTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '263771234567';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.whatsapp.api_token' => 't',
            'services.whatsapp.phone_number_id' => '1',
            'services.whatsapp.graph_version' => 'v21.0',
        ]);
    }

    /** @return array<int,string> the paths Meta was called on, in order */
    private function sendCampaignMessage(callable $responder): array
    {
        $paths = [];
        Http::fake(function ($request) use (&$paths, $responder) {
            $paths[] = parse_url((string) $request->url(), PHP_URL_PATH);

            return $responder(count($paths));
        });

        (new SendWhatsAppNotification(self::PHONE, 'marketing_broadcast', 'S', 'B', ['A', 'S', 'B'], 'en', requireTemplate: true))
            ->handle(app(WhatsAppService::class));

        return $paths;
    }

    public function test_campaigns_use_the_standard_endpoint_when_mm_lite_is_off(): void
    {
        config(['services.whatsapp.mm_lite' => false]);

        $paths = $this->sendCampaignMessage(fn () => Http::response(['messages' => [['id' => 'wamid.x']]]));

        $this->assertSame(['/v21.0/1/messages'], $paths);
    }

    public function test_campaigns_route_through_mm_lite_when_enabled(): void
    {
        config(['services.whatsapp.mm_lite' => true]);

        $paths = $this->sendCampaignMessage(fn () => Http::response(['messages' => [['id' => 'wamid.x']]]));

        $this->assertSame(['/v21.0/1/marketing_messages'], $paths);
    }

    public function test_a_failed_mm_lite_send_falls_back_to_the_standard_endpoint(): void
    {
        config(['services.whatsapp.mm_lite' => true]);

        // First call (MM Lite) fails — e.g. the WABA isn't enabled yet.
        $paths = $this->sendCampaignMessage(fn (int $n) => $n === 1
            ? Http::response(['error' => ['message' => 'not enabled', 'code' => 100]], 400)
            : Http::response(['messages' => [['id' => 'wamid.x']]]));

        $this->assertSame(['/v21.0/1/marketing_messages', '/v21.0/1/messages'], $paths,
            'enabling MM Lite before onboarding must never lose a send');
    }

    public function test_ordinary_notifications_never_use_mm_lite(): void
    {
        config(['services.whatsapp.mm_lite' => true]);

        $paths = [];
        Http::fake(function ($request) use (&$paths) {
            $paths[] = parse_url((string) $request->url(), PHP_URL_PATH);

            return Http::response(['messages' => [['id' => 'wamid.x']]]);
        });

        // No requireTemplate → a utility/service message, not marketing.
        (new SendWhatsAppNotification(self::PHONE, 'deposit_confirmed', 'S', 'B', ['A'], 'en'))
            ->handle(app(WhatsAppService::class));

        $this->assertSame(['/v21.0/1/messages'], $paths);
    }
}
