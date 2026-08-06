<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppSession;
use App\Services\AI\GeminiClient;
use App\WhatsApp\Session\SessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Three AI-composed check-ins (2h/12h/23h) for anyone who went quiet — linked
 * customer or unlinked guest alike — timed to land before Meta's 24h
 * free-form window closes. Each tier must fire exactly once, must never
 * mention the underlying window/deadline to the customer, and must never
 * touch a contact with no real session or one already handed to a human.
 */
class WhatsAppNudgeIdleCustomersTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '263771234567';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.gemini.api_key' => 'k']);
    }

    private function linkedAccountWithSession(array $sessionAttrs = []): WhatsAppAccount
    {
        $user = User::factory()->create();
        $account = WhatsAppAccount::create([
            'wa_phone' => self::PHONE, 'user_id' => $user->id,
            'link_status' => 'linked', 'opted_in' => true,
            'last_seen_at' => now()->subHours(3),
        ]);

        WhatsAppSession::create(array_merge([
            'wa_phone' => self::PHONE,
            'current_flow' => null,
            'current_state' => null,
            'status' => 'idle',
            'last_activity' => now()->subHours(3),
            'expires_at' => now()->subHours(2),
            'context' => ['_ai_history' => [['user' => 'do you do youtube views?', 'model' => 'Not yet, but we do TikTok and IG!']]],
        ], $sessionAttrs));

        return $account;
    }

    private function mockAiText(string $reply): void
    {
        $mock = Mockery::mock(GeminiClient::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('generateText')->andReturn($reply);
        $this->app->instance(GeminiClient::class, $mock);
    }

    public function test_a_customer_idle_3_hours_gets_the_tier_1_nudge(): void
    {
        $this->linkedAccountWithSession();
        $this->mockAiText('Hey! Still around? Happy to help with TikTok or IG whenever you are ready 😊');

        $this->artisan('whatsapp:nudge-idle-customers')->assertSuccessful();

        $out = WhatsAppMessage::where('direction', 'out')->latest('id')->first();
        $this->assertNotNull($out);
        $this->assertStringContainsString('Still around', (string) $out->body);

        $session = WhatsAppSession::where('wa_phone', self::PHONE)->first();
        $this->assertSame(1, $session->nudge_tier);
        $this->assertNotNull($session->nudged_at);
    }

    public function test_the_same_tier_is_never_sent_twice(): void
    {
        $this->linkedAccountWithSession(['nudge_tier' => 1, 'nudged_at' => now()->subHour()]);
        $this->mockAiText('This should never be sent.');

        $this->artisan('whatsapp:nudge-idle-customers')->assertSuccessful();

        $this->assertSame(0, WhatsAppMessage::where('direction', 'out')->count());
    }

    public function test_a_customer_idle_13_hours_gets_tier_2_not_tier_1_again(): void
    {
        $this->linkedAccountWithSession([
            'last_activity' => now()->subHours(13),
            'nudge_tier' => 1,
            'nudged_at' => now()->subHours(11),
        ]);
        $this->mockAiText("It's been a bit — no rush, just checking in whenever you're free!");

        $this->artisan('whatsapp:nudge-idle-customers')->assertSuccessful();

        $session = WhatsAppSession::where('wa_phone', self::PHONE)->first();
        $this->assertSame(2, $session->nudge_tier);
        $this->assertSame(1, WhatsAppMessage::where('direction', 'out')->count());
    }

    public function test_a_contact_with_no_session_at_all_is_untouched(): void
    {
        // Nothing to describe to the AI and nothing to mark as nudged — there
        // must be a real WhatsAppSession row for this command to act on.
        WhatsAppAccount::create([
            'wa_phone' => self::PHONE, 'link_status' => 'guest', 'opted_in' => true,
            'last_seen_at' => now()->subHours(5),
        ]);
        $this->mockAiText('Should never be called.');

        $this->artisan('whatsapp:nudge-idle-customers')->assertSuccessful();

        $this->assertSame(0, WhatsAppMessage::where('direction', 'out')->count());
    }

    public function test_an_unlinked_guest_with_a_real_conversation_still_gets_nudged(): void
    {
        WhatsAppAccount::create([
            'wa_phone' => self::PHONE, 'link_status' => 'guest', 'opted_in' => true,
            'last_seen_at' => now()->subHours(3),
        ]);
        WhatsAppSession::create([
            'wa_phone' => self::PHONE,
            'current_flow' => null, 'current_state' => null, 'status' => 'idle',
            'last_activity' => now()->subHours(3), 'expires_at' => now()->subHours(2),
            'context' => ['_ai_history' => [['user' => 'how much for tiktok followers?', 'model' => 'From $10 per 1,000!']]],
        ]);
        $this->mockAiText('Hey! Still keen on those TikTok followers whenever you are ready 😊');

        $this->artisan('whatsapp:nudge-idle-customers')->assertSuccessful();

        $out = WhatsAppMessage::where('direction', 'out')->latest('id')->first();
        $this->assertNotNull($out, 'an unlinked guest with a real conversation should still be nudged');
        $session = WhatsAppSession::where('wa_phone', self::PHONE)->first();
        $this->assertSame(1, $session->nudge_tier);
    }

    public function test_a_customer_already_handed_off_to_a_human_is_skipped(): void
    {
        $account = $this->linkedAccountWithSession();
        $account->update(['agent_handoff_until' => now()->addHours(1)]);
        $this->mockAiText('Should never be called.');

        $this->artisan('whatsapp:nudge-idle-customers')->assertSuccessful();

        $this->assertSame(0, WhatsAppMessage::where('direction', 'out')->count());
    }

    /**
     * A loop handoff's 2h window lapsing does not mean the underlying
     * frustration is resolved — only a human picking it up does. Nudging on
     * top of an unresolved escalation is exactly the "the bot doesn't
     * listen" pattern that drives a customer to block the number.
     */
    public function test_a_lapsed_but_recent_handoff_still_skips_the_nudge(): void
    {
        $account = $this->linkedAccountWithSession();
        $account->update(['agent_handoff_until' => now()->subHours(2)]); // window closed, but recent
        $this->mockAiText('Should never be called.');

        $this->artisan('whatsapp:nudge-idle-customers')->assertSuccessful();

        $this->assertSame(0, WhatsAppMessage::where('direction', 'out')->count());
    }

    public function test_a_handoff_from_days_ago_no_longer_blocks_nudges(): void
    {
        $account = $this->linkedAccountWithSession();
        $account->update(['agent_handoff_until' => now()->subDays(3)]);
        $this->mockAiText('Hey! Still around?');

        $this->artisan('whatsapp:nudge-idle-customers')->assertSuccessful();

        $this->assertSame(1, WhatsAppMessage::where('direction', 'out')->count());
    }

    public function test_a_flagged_loop_without_a_full_handoff_still_skips_the_nudge(): void
    {
        $this->linkedAccountWithSession(['context' => ['_loop_nudged' => true]]);
        $this->mockAiText('Should never be called.');

        $this->artisan('whatsapp:nudge-idle-customers')->assertSuccessful();

        $this->assertSame(0, WhatsAppMessage::where('direction', 'out')->count());
    }

    public function test_a_stall_at_the_order_confirm_step_is_described_to_the_ai(): void
    {
        $service = Service::create([
            'name' => 'TikTok Followers', 'name_sn' => 'x', 'description' => '', 'description_sn' => '',
            'category' => 'TikTok', 'type' => 'followers', 'rate' => 1.0,
            'min_qty' => 10, 'max_qty' => 100000, 'is_active' => true,
        ]);
        $this->linkedAccountWithSession([
            'current_flow' => 'order', 'current_state' => 'confirm',
            'context' => ['order_service_id' => $service->id, 'order_quantity' => 1000],
        ]);

        $captured = null;
        $mock = Mockery::mock(GeminiClient::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('generateText')->andReturnUsing(function (...$args) use (&$captured) {
            $captured = $args[0];

            return 'Still keen on those TikTok followers? Ready when you are!';
        });
        $this->app->instance(GeminiClient::class, $mock);

        $this->artisan('whatsapp:nudge-idle-customers')->assertSuccessful();

        $this->assertStringContainsString('TikTok Followers', (string) $captured);
        $this->assertStringContainsString('1,000', (string) $captured);
    }

    public function test_a_reply_resets_the_tier_for_a_future_stall(): void
    {
        $this->linkedAccountWithSession(['nudge_tier' => 2, 'nudged_at' => now()->subMinutes(5)]);

        $sessions = app(SessionManager::class);
        $ctx = $sessions->load(self::PHONE);
        $sessions->save($ctx);

        $session = WhatsAppSession::where('wa_phone', self::PHONE)->first();
        $this->assertSame(0, $session->nudge_tier);
        $this->assertNull($session->nudged_at);
    }
}
