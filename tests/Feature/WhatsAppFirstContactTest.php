<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsAppAccount;
use App\WhatsApp\Intent\IntentEngine;
use App\WhatsApp\Routing\MessageRouter;
use App\WhatsApp\Session\SessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * First contact from an unknown number: a plain greeting drops straight into
 * guided sign-up (name → email → auto-created account); anything substantive
 * goes down the normal ladder instead.
 */
class WhatsAppFirstContactTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '263771234567';

    private function msg(string $text, ?array $adReferral = null): array
    {
        return [
            'from' => self::PHONE,
            'wa_message_id' => 'wamid.'.uniqid('', true),
            'type' => 'text',
            'text' => $text,
            'interactive_id' => null,
            'ad_referral' => $adReferral,
            'name' => 'Tendai',
            'timestamp' => time(),
            'raw' => [],
        ];
    }

    public function test_first_greeting_from_unknown_number_starts_guided_signup(): void
    {
        $router = app(MessageRouter::class);
        $router->handle($this->msg('hi'), 'Tendai');

        $ctx = app(SessionManager::class)->load(self::PHONE);
        $this->assertSame('register', $ctx->flow);
        $this->assertSame('ask_name', $ctx->state);

        // Completing name + email actually creates the account.
        $router->handle($this->msg('Tendai Moyo'));
        $router->handle($this->msg('tendai@example.com'));

        $this->assertDatabaseHas('users', ['email' => 'tendai@example.com', 'name' => 'Tendai Moyo']);
        $account = WhatsAppAccount::where('wa_phone', self::PHONE)->first();
        $this->assertNotNull($account->user_id);
    }

    public function test_known_guest_greeting_does_not_restart_signup(): void
    {
        WhatsAppAccount::create([
            'wa_phone' => self::PHONE,
            'link_status' => 'guest',
            'opted_in' => true,
        ]);

        app(MessageRouter::class)->handle($this->msg('hi'));

        $ctx = app(SessionManager::class)->load(self::PHONE);
        $this->assertNull($ctx->flow); // guest menu, not the register flow
    }

    public function test_substantive_first_message_is_not_hijacked_by_signup(): void
    {
        // AI unavailable → the message falls through the ladder to the menu.
        $intent = Mockery::mock(IntentEngine::class);
        $intent->shouldReceive('resolve')->andReturn(['handled' => false]);
        $this->app->instance(IntentEngine::class, $intent);

        app(MessageRouter::class)->handle($this->msg('how much are instagram followers?'));

        $ctx = app(SessionManager::class)->load(self::PHONE);
        $this->assertNull($ctx->flow);
    }

    public function test_ad_click_with_canned_cta_gets_platform_intro_and_signup(): void
    {
        app(MessageRouter::class)->handle($this->msg(
            'Hi! Can I get more info about this?',
            ['source_type' => 'ad', 'headline' => 'Grow your Instagram today', 'source_url' => 'https://fb.me/xyz']
        ));

        // Intro names the platform and explains what it does...
        $intro = \App\Models\WhatsAppMessage::where('direction', 'out')->first();
        $this->assertStringContainsString(config('app.name'), $intro->body);
        $this->assertStringContainsString('followers, likes, views', $intro->body);

        // ...then guided signup starts.
        $ctx = app(SessionManager::class)->load(self::PHONE);
        $this->assertSame('register', $ctx->flow);
    }

    public function test_canned_cta_text_alone_triggers_the_ad_intro(): void
    {
        // No referral payload (e.g. forwarded ad) — the text heuristic catches it.
        app(MessageRouter::class)->handle($this->msg("I'm interested, tell me more"));

        $ctx = app(SessionManager::class)->load(self::PHONE);
        $this->assertSame('register', $ctx->flow);
    }

    public function test_substantive_ad_message_goes_to_ai_with_first_contact_context(): void
    {
        $captured = [];
        $intent = Mockery::mock(IntentEngine::class);
        $intent->shouldReceive('resolve')
            ->withArgs(function ($text, $phone, $context) use (&$captured) {
                $captured = $context;

                return true;
            })
            ->andReturn(['handled' => false]);
        $this->app->instance(IntentEngine::class, $intent);

        // Product named → a real ask, not a canned CTA: AI handles it, told
        // it's a first contact that came from a specific ad.
        app(MessageRouter::class)->handle($this->msg(
            "I'm interested in tiktok followers",
            ['source_type' => 'ad', 'headline' => 'TikTok growth deals', 'source_url' => null]
        ));

        $this->assertTrue($captured['first_contact']);
        $this->assertSame('TikTok growth deals', $captured['ad_headline']);

        $ctx = app(SessionManager::class)->load(self::PHONE);
        $this->assertNull($ctx->flow); // not hijacked into signup
    }

    public function test_matched_phone_auto_links_and_skips_signup(): void
    {
        User::factory()->create(['phone' => self::PHONE]);

        app(MessageRouter::class)->handle($this->msg('hi'));

        $ctx = app(SessionManager::class)->load(self::PHONE);
        $this->assertNull($ctx->flow); // linked straight away → main menu, no signup
    }
}
