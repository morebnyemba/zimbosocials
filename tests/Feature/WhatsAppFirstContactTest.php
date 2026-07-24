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

    public function test_first_greeting_gets_intro_and_no_signup_hurdle(): void
    {
        $router = app(MessageRouter::class);
        $router->handle($this->msg('hi'), 'Tendai');

        // Intro + invitation to business — no register flow, no user row yet
        // (accounts are created silently the moment they take an action).
        $ctx = app(SessionManager::class)->load(self::PHONE);
        $this->assertNull($ctx->flow);
        $this->assertDatabaseCount('users', 0);

        $intro = \App\Models\WhatsAppMessage::where('direction', 'out')->first();
        // Invites them straight into business — growing a page or a sponsored advert.
        $this->assertStringContainsString('what would you like to do', $intro->body);
        $this->assertStringContainsString('Grow your page', $intro->body);
    }

    public function test_taking_an_action_auto_registers_silently_and_proceeds(): void
    {
        \App\Models\Service::create([
            'name' => 'Instagram Followers', 'name_sn' => 'x', 'description' => '', 'description_sn' => '',
            'category' => 'Instagram', 'type' => 'followers', 'rate' => 1.0,
            'min_qty' => 100, 'max_qty' => 100000, 'is_active' => true,
        ]);

        $router = app(MessageRouter::class);
        $router->handle($this->msg('hi'), 'Tendai');
        $router->handle($this->tap('fl_order')); // guest taps New order

        // Account exists — synthetic {digits}@domain email, display name, linked.
        $this->assertDatabaseHas('users', [
            'email' => '263771234567@zimbosocials.co.zw',
            'name' => 'Tendai',
        ]);
        $account = WhatsAppAccount::where('wa_phone', self::PHONE)->first();
        $this->assertTrue($account->isLinked());

        // And the order flow started in the SAME turn — straight to business.
        $ctx = app(SessionManager::class)->load(self::PHONE);
        $this->assertSame('order', $ctx->flow);
        $this->assertSame('pick_category', $ctx->state);
    }

    public function test_auto_registration_is_idempotent_on_recontact(): void
    {
        $registrar = app(\App\WhatsApp\Auth\WhatsAppRegistrar::class);
        WhatsAppAccount::create(['wa_phone' => self::PHONE, 'link_status' => 'guest', 'opted_in' => true]);

        $first = $registrar->autoRegister(self::PHONE, 'Tendai');
        $second = $registrar->autoRegister(self::PHONE, 'Tendai');

        $this->assertTrue($first['ok']);
        $this->assertTrue($second['ok']);
        $this->assertSame($first['user']->id, $second['user']->id);
        $this->assertSame(1, \App\Models\User::count());
    }

    public function test_no_real_email_is_ever_sent_to_synthetic_addresses(): void
    {
        $this->assertTrue(\App\WhatsApp\Auth\WhatsAppRegistrar::isAutoEmail('263771234567@zimbosocials.co.zw'));
        $this->assertFalse(\App\WhatsApp\Auth\WhatsAppRegistrar::isAutoEmail('tendai@gmail.com'));

        \Illuminate\Support\Facades\Mail::fake();
        (new \App\Jobs\SendEmailNotification('263771234567@zimbosocials.co.zw', 'T', 'Subj', 'Body'))->handle();
        \Illuminate\Support\Facades\Mail::assertNothingSent();
    }

    private function tap(string $id): array
    {
        return [
            'from' => self::PHONE,
            'wa_message_id' => 'wamid.'.uniqid('', true),
            'type' => 'interactive',
            'text' => '',
            'interactive_id' => $id,
            'ad_referral' => null,
            'name' => 'Tendai',
            'timestamp' => time(),
            'raw' => [],
        ];
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
        $this->assertStringContainsString('followers, likes and views', $intro->body);

        // ...and invites them straight to business (no signup flow).
        $ctx = app(SessionManager::class)->load(self::PHONE);
        $this->assertNull($ctx->flow);
    }

    public function test_canned_cta_text_alone_triggers_the_ad_intro(): void
    {
        // No referral payload (e.g. forwarded ad) — the text heuristic catches it.
        app(MessageRouter::class)->handle($this->msg("I'm interested, tell me more"));

        // Ad intro sent, no signup flow — accounts appear when they act.
        $ctx = app(SessionManager::class)->load(self::PHONE);
        $this->assertNull($ctx->flow);
        $this->assertStringContainsString(
            'thanks for reaching out',
            \App\Models\WhatsAppMessage::where('direction', 'out')->first()->body
        );
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
