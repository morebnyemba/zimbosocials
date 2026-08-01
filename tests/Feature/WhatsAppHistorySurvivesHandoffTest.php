<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\AI\GeminiClient;
use App\WhatsApp\Routing\MessageRouter;
use App\WhatsApp\Session\SessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * A transcript showed the bot re-asking something already answered hours
 * earlier, right after an admin handoff had lapsed. Verifying here whether
 * that's a real persistence bug or just a symptom of a weaker model at the
 * time (Flash-Lite was in use for that conversation) — _ai_history is a
 * '_'-prefixed context key, which SessionContext::resetFlow() and
 * AccountStore::startAgentHandoff() both deliberately leave untouched, and
 * nothing deletes a whatsapp_sessions row except the admin's explicit
 * "reset conversation" action. This test pins down that it does survive.
 */
class WhatsAppHistorySurvivesHandoffTest extends TestCase
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
        Http::fake(fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid('', true)]]]));
    }

    private function linkedAccount(): WhatsAppAccount
    {
        $user = User::factory()->create(['balance' => 0]);

        return WhatsAppAccount::create([
            'wa_phone' => self::PHONE, 'user_id' => $user->id,
            'link_status' => 'linked', 'opted_in' => true,
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

    private function mockAi(string $reply): void
    {
        $mock = Mockery::mock(GeminiClient::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('generateJson')->andReturn([
            'reply' => $reply, 'follow_up' => null, 'flow' => 'none', 'flow_data' => [],
        ]);
        $this->app->instance(GeminiClient::class, $mock);
    }

    public function test_ai_history_is_intact_after_a_handoff_lapses(): void
    {
        $account = $this->linkedAccount();

        $this->mockAi('Sharp! Which TikTok service would you like?');
        app(MessageRouter::class)->handle($this->textMessage('I want tiktok followers'));

        $sessions = app(SessionManager::class);
        $before = $sessions->load(self::PHONE);
        $this->assertNotEmpty($before->get('_ai_history', []), 'history should be recorded after the first exchange');

        // Hand off to a human — this must not touch the session row at all.
        app(\App\WhatsApp\Persistence\AccountStore::class)->startAgentHandoff(self::PHONE, hours: 2);
        // ...and the handoff has since lapsed (what happened in the real transcript).
        $account->update(['agent_handoff_until' => now()->subMinute()]);

        $this->mockAi('Sharp! Following up on TikTok followers — how many would you like?');
        app(MessageRouter::class)->handle($this->textMessage('hie'));

        $after = $sessions->load(self::PHONE);
        $history = $after->get('_ai_history', []);
        $this->assertNotEmpty($history);
        $this->assertSame('I want tiktok followers', $history[0]['user'], 'the pre-handoff exchange must still be in memory');
    }
}
