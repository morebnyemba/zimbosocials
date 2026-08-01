<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\AI\GeminiClient;
use App\WhatsApp\Routing\CommandRegistry;
use App\WhatsApp\Routing\MessageRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Balance and order-tracking questions are still answered by the AI first —
 * that stays true even when phrased naturally ("show me my balance", "where
 * is my order 1231?"), not just as the bare keyword. What changed is that a
 * natural phrasing is now RECOGNISED at all: previously only the exact word
 * matched, so if the AI was ever unavailable these fell through to the
 * generic free-text path with no deterministic safety net. Now they resolve
 * to the right flow either way — via the AI when it's up, or straight to the
 * deterministic flow (with order_id already extracted) when it isn't.
 */
class WhatsAppDataLookupPhrasingTest extends TestCase
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

    private function linkedAccount(float $balance = 42.5): User
    {
        $user = User::factory()->create(['balance' => $balance]);
        WhatsAppAccount::create([
            'wa_phone' => self::PHONE, 'user_id' => $user->id,
            'link_status' => 'linked', 'opted_in' => true,
        ]);

        return $user;
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

    /** AI is up and answers — this is still first-choice for these questions. */
    private function mockAi(string $reply): void
    {
        $mock = Mockery::mock(GeminiClient::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('generateJson')->andReturn([
            'reply' => $reply, 'follow_up' => null, 'flow' => 'none', 'flow_data' => [],
        ]);
        $this->app->instance(GeminiClient::class, $mock);
    }

    /** AI is down — the deterministic flow must still answer correctly. */
    private function mockAiUnavailable(): void
    {
        $mock = Mockery::mock(GeminiClient::class);
        $mock->shouldReceive('isConfigured')->andReturn(false);
        $mock->shouldNotReceive('generateJson');
        $this->app->instance(GeminiClient::class, $mock);
    }

    public function test_a_natural_balance_question_still_reaches_the_ai(): void
    {
        $this->linkedAccount(42.5);
        $this->mockAi('You have $42.50 in your wallet 💰');

        app(MessageRouter::class)->handle($this->textMessage('show me my balance'));

        $out = \App\Models\WhatsAppMessage::where('direction', 'out')->latest('id')->first();
        $this->assertStringContainsString('42.50', (string) $out->body);
        $this->assertSame('ai', $out->handled_by);
    }

    public function test_a_natural_order_tracking_question_still_reaches_the_ai(): void
    {
        $user = $this->linkedAccount();
        $this->mockAi("Order #1 is on its way!");

        app(MessageRouter::class)->handle($this->textMessage('where is my order 1?'));

        $out = \App\Models\WhatsAppMessage::where('direction', 'out')->latest('id')->first();
        $this->assertSame('ai', $out->handled_by);
    }

    public function test_balance_falls_back_deterministically_when_ai_is_down(): void
    {
        $this->linkedAccount(42.5);
        $this->mockAiUnavailable();

        app(MessageRouter::class)->handle($this->textMessage('show me my balance'));

        $out = \App\Models\WhatsAppMessage::where('direction', 'out')->latest('id')->first();
        $this->assertStringContainsString('42.50', (string) $out->body);
    }

    public function test_order_tracking_falls_back_and_still_jumps_straight_to_the_order(): void
    {
        $user = $this->linkedAccount();
        $this->mockAiUnavailable();
        $service = Service::create([
            'name' => 'IG Followers', 'name_sn' => 'x', 'description' => '', 'description_sn' => '',
            'category' => 'Instagram', 'type' => 'followers', 'rate' => 1.0,
            'min_qty' => 100, 'max_qty' => 100000, 'is_active' => true,
        ]);
        $order = Order::create([
            'user_id' => $user->id, 'service_id' => $service->id,
            'link' => 'https://instagram.com/jane', 'quantity' => 100,
            'charge' => 10, 'rate_at_order' => 1.0, 'status' => 'processing',
        ]);

        app(MessageRouter::class)->handle($this->textMessage("where is my order {$order->id}?"));

        $out = \App\Models\WhatsAppMessage::where('direction', 'out')->latest('id')->first();
        $this->assertStringContainsString((string) $order->id, (string) $out->body);
        $this->assertStringNotContainsString('Which order?', (string) $out->body);
    }

    public function test_a_bare_track_word_still_asks_for_the_order_number_when_ai_is_down(): void
    {
        $this->linkedAccount();
        $this->mockAiUnavailable();

        app(MessageRouter::class)->handle($this->textMessage('track'));

        $out = \App\Models\WhatsAppMessage::where('direction', 'out')->latest('id')->first();
        $this->assertStringContainsString('order number', (string) $out->body);
    }

    public function test_the_shortcut_never_interrupts_an_active_flow(): void
    {
        // Mid-flow, "order" appearing in free text must not be hijacked into
        // a track lookup — the active step still owns the input.
        $registry = new CommandRegistry;
        $this->assertNull($registry->match('actually make it 2000 for my order'));
    }

    public function test_phrase_matching_extracts_the_order_number(): void
    {
        $registry = new CommandRegistry;

        $this->assertSame(['command' => 'track', 'order_id' => 1231], $registry->matchPhrase('where is my order 1231?'));
        $this->assertSame(['command' => 'track', 'order_id' => null], $registry->matchPhrase('can you track my order'));
        $this->assertSame(['command' => 'balance', 'order_id' => null], $registry->matchPhrase('show me my balance please'));
        $this->assertNull($registry->matchPhrase('I want 1000 instagram followers'));
    }
}
