<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\AI\GeminiClient;
use App\WhatsApp\Routing\MessageRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * A brand-new number gets the first-contact welcome. A KNOWN contact returning
 * after a day+ of silence should get a warm, personalised welcome-back — driven
 * by the AI with the returning flag, not a cold "how can I help".
 */
class WelcomeBackTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '263771234567';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.whatsapp.api_token' => 't', 'services.whatsapp.phone_number_id' => '1', 'services.gemini.api_key' => 'k']);
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.x']]])]);
    }

    public function test_returning_after_gap_is_true_only_after_the_window(): void
    {
        $account = WhatsAppAccount::create([
            'wa_phone' => self::PHONE, 'link_status' => 'guest', 'opted_in' => true,
        ]);
        $account->wasRecentlyCreated = false; // simulate a loaded (existing) account

        $account->previousSeenAt = now()->subHours(30);
        $this->assertTrue($account->returningAfterGap(24));

        $account->previousSeenAt = now()->subHours(2);
        $this->assertFalse($account->returningAfterGap(24));

        // Brand-new (no prior visit) is never "returning".
        $account->previousSeenAt = null;
        $this->assertFalse($account->returningAfterGap(24));
    }

    public function test_a_brand_new_number_is_not_flagged_as_returning(): void
    {
        $account = new WhatsAppAccount(['wa_phone' => self::PHONE]);
        $account->wasRecentlyCreated = true;
        $account->previousSeenAt = now()->subDays(5); // irrelevant — it's new

        $this->assertFalse($account->returningAfterGap(24));
    }

    public function test_the_ai_gets_the_welcome_back_flag_for_a_returning_contact(): void
    {
        // A known contact last seen two days ago.
        $account = WhatsAppAccount::create([
            'wa_phone' => self::PHONE, 'display_name' => 'Tendai',
            'link_status' => 'guest', 'opted_in' => true,
        ]);
        $account->forceFill(['last_seen_at' => now()->subDays(2)])->save();

        $prompt = null;
        $mock = Mockery::mock(GeminiClient::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('generateJson')->andReturnUsing(function (...$args) use (&$prompt) {
            $prompt = $args[0] ?? '';

            return ['reply' => 'Welcome back, Tendai! 👋', 'follow_up' => null, 'flow' => 'none', 'flow_data' => []];
        });
        $this->app->instance(GeminiClient::class, $mock);

        app(MessageRouter::class)->handle([
            'from' => self::PHONE, 'wa_message_id' => 'wamid.'.uniqid(), 'type' => 'text',
            'text' => 'hi', 'interactive_id' => null, 'name' => 'Tendai', 'timestamp' => time(), 'raw' => [],
        ]);

        // The returning-customer instructions reached the model...
        $this->assertStringContainsString('WELCOME BACK', (string) $prompt);
        // ...and its reply was sent.
        $out = \App\Models\WhatsAppMessage::where('wa_phone', self::PHONE)->where('direction', 'out')->latest('id')->first();
        $this->assertStringContainsString('Welcome back', (string) $out->body);
    }

    public function test_an_active_contact_gets_no_welcome_back_block(): void
    {
        $account = WhatsAppAccount::create([
            'wa_phone' => self::PHONE, 'display_name' => 'Tendai',
            'link_status' => 'guest', 'opted_in' => true,
        ]);
        $account->forceFill(['last_seen_at' => now()->subMinutes(10)])->save(); // recent

        $prompt = null;
        $mock = Mockery::mock(GeminiClient::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('generateJson')->andReturnUsing(function (...$args) use (&$prompt) {
            $prompt = $args[0] ?? '';

            return ['reply' => 'Hi! What would you like to grow?', 'follow_up' => null, 'flow' => 'none', 'flow_data' => []];
        });
        $this->app->instance(GeminiClient::class, $mock);

        app(MessageRouter::class)->handle([
            'from' => self::PHONE, 'wa_message_id' => 'wamid.'.uniqid(), 'type' => 'text',
            'text' => 'hi', 'interactive_id' => null, 'name' => 'Tendai', 'timestamp' => time(), 'raw' => [],
        ]);

        $this->assertStringNotContainsString('WELCOME BACK', (string) $prompt);
    }
}
