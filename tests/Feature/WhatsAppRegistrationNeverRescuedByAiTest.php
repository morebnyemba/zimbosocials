<?php

namespace Tests\Feature;

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\Services\AI\GeminiClient;
use App\WhatsApp\Routing\MessageRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Seen live: a customer typed "Ahh" instead of an email mid-registration, the
 * AI "rescued" the failed validation with an unrelated pitch about service
 * pricing, and the real "that's not a valid email" message only surfaced a
 * turn later — reading as a non-sequitur about whatever they'd said by then.
 * Nothing can be ordered or funded without an account, so registration must
 * never hand a failed step to the AI — always the plain, correct retry.
 */
class WhatsAppRegistrationNeverRescuedByAiTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '263771234567';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.whatsapp.api_token' => 't',
            'services.whatsapp.phone_number_id' => '1',
            'services.gemini.api_key' => 'k',
        ]);
    }

    private function textMessage(string $text): array
    {
        return [
            'from' => self::PHONE,
            'wa_message_id' => 'wamid.'.uniqid('', true),
            'type' => 'text',
            'text' => $text,
            'interactive_id' => null,
            'media' => null,
            'name' => 'Tester',
            'timestamp' => time(),
            'raw' => [],
        ];
    }

    public function test_an_invalid_email_never_reaches_the_ai(): void
    {
        WhatsAppAccount::create([
            'wa_phone' => self::PHONE, 'link_status' => 'guest', 'opted_in' => true,
            'current_flow' => null,
        ]);

        // The AI must never even be asked — if it were, this mock would answer
        // with an off-topic pitch instead of the real validation error.
        $mock = Mockery::mock(GeminiClient::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldNotReceive('generateJson');
        $this->app->instance(GeminiClient::class, $mock);

        $router = app(MessageRouter::class);
        $sessions = app(\App\WhatsApp\Session\SessionManager::class);
        $ctx = $sessions->load(self::PHONE);
        $ctx->setFlow('register', 'ask_email');
        $ctx->set('reg_name', 'Tash Banda');
        $sessions->save($ctx);

        $router->handle($this->textMessage('Ahh'));

        $out = WhatsAppMessage::where('direction', 'out')->latest('id')->first();
        $this->assertStringContainsString('valid email', (string) $out->body);

        // Still on the email step — nothing was silently skipped.
        $after = $sessions->load(self::PHONE);
        $this->assertSame('register', $after->flow);
        $this->assertSame('ask_email', $after->state);
    }

    public function test_an_invalid_name_never_reaches_the_ai_either(): void
    {
        WhatsAppAccount::create([
            'wa_phone' => self::PHONE, 'link_status' => 'guest', 'opted_in' => true,
        ]);

        $mock = Mockery::mock(GeminiClient::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldNotReceive('generateJson');
        $this->app->instance(GeminiClient::class, $mock);

        $sessions = app(\App\WhatsApp\Session\SessionManager::class);
        $ctx = $sessions->load(self::PHONE);
        $ctx->setFlow('register', 'ask_name');
        $sessions->save($ctx);

        app(MessageRouter::class)->handle($this->textMessage('A'));

        $out = WhatsAppMessage::where('direction', 'out')->latest('id')->first();
        $this->assertStringContainsString('full name', (string) $out->body);
    }
}
