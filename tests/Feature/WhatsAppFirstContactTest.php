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

    private function msg(string $text): array
    {
        return [
            'from' => self::PHONE,
            'wa_message_id' => 'wamid.'.uniqid('', true),
            'type' => 'text',
            'text' => $text,
            'interactive_id' => null,
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

    public function test_matched_phone_auto_links_and_skips_signup(): void
    {
        User::factory()->create(['phone' => self::PHONE]);

        app(MessageRouter::class)->handle($this->msg('hi'));

        $ctx = app(SessionManager::class)->load(self::PHONE);
        $this->assertNull($ctx->flow); // linked straight away → main menu, no signup
    }
}
