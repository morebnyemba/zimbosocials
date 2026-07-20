<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppNotification;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Campaign sends used to leave no trace: no transcript row and no
 * wa_message_id, so Meta's delivery receipts could never be matched back and a
 * broadcast simply looked like "nothing happened". Every template send is now
 * recorded, including WHY it failed — notably Meta code 131049, the per-user
 * marketing cap that applies across all businesses.
 */
class TemplateSendVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '263771234567';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.whatsapp.api_token' => 't', 'services.whatsapp.phone_number_id' => '1']);
    }

    private function send(bool $requireTemplate = true): void
    {
        (new SendWhatsAppNotification(self::PHONE, 'marketing_broadcast', 'Big sale', 'Grow today', ['A', 'S', 'B'], 'en', requireTemplate: $requireTemplate))
            ->handle(app(WhatsAppService::class));
    }

    public function test_a_successful_template_is_recorded_with_its_message_id(): void
    {
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.OK']]])]);

        $this->send();

        $msg = WhatsAppMessage::where('wa_phone', self::PHONE)->first();
        $this->assertNotNull($msg);
        $this->assertSame('wamid.OK', $msg->wa_message_id, 'needed so delivery receipts can attach');
        $this->assertSame('out', $msg->direction);
        $this->assertNull($msg->delivery_status, 'starts blank — the webhook fills it in');
    }

    public function test_the_per_user_marketing_cap_is_recorded_as_a_failure(): void
    {
        // Meta 131049: the recipient already hit their daily marketing limit
        // across ALL businesses — nothing we can do about it.
        Http::fake(['*' => Http::response([
            'error' => ['message' => 'Message failed to send because of ecosystem health', 'code' => 131049],
        ], 400)]);

        $this->send();

        $msg = WhatsAppMessage::where('wa_phone', self::PHONE)->first();
        $this->assertNotNull($msg, 'a rejected send must still be visible');
        $this->assertSame('failed', $msg->delivery_status);
        $this->assertSame(131049, $msg->payload['error_code']);
    }

    public function test_the_recipient_and_template_are_identifiable(): void
    {
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.OK']]])]);

        $this->send();

        $msg = WhatsAppMessage::where('wa_phone', self::PHONE)->first();
        $this->assertSame('marketing_broadcast', $msg->intent);
        $this->assertStringContainsString('Big sale', (string) $msg->body);
    }
}
