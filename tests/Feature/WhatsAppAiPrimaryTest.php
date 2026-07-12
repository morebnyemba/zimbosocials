<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\WhatsApp\Flow\FlowEngine;
use App\WhatsApp\Intent\IntentEngine;
use App\WhatsApp\Routing\MessageRouter;
use App\WhatsApp\Session\SessionContext;
use App\WhatsApp\Session\SessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * AI-primary routing: mid-flow free text the flow doesn't understand is decided
 * by the AI brain — it can switch flows, adjust the current one, or just answer
 * (returning the user to their step). Deterministic guardrails (buttons, yes/no
 * confirms, commands) stay pre-AI.
 */
class WhatsAppAiPrimaryTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '263771234567';

    private function seedUserAndAccount(float $balance = 100): User
    {
        $user = User::factory()->create(['balance' => $balance]);
        WhatsAppAccount::create([
            'wa_phone' => self::PHONE,
            'user_id' => $user->id,
            'link_status' => 'linked',
            'opted_in' => true,
        ]);

        return $user;
    }

    private function makeService(string $category, string $name, float $rate = 1.0): Service
    {
        return Service::create([
            'name' => $name, 'name_sn' => $name, 'description' => '', 'description_sn' => '',
            'category' => $category, 'type' => 'default', 'rate' => $rate,
            'min_qty' => 100, 'max_qty' => 10000, 'is_active' => true,
        ]);
    }

    private function msg(string $text): array
    {
        return [
            'from' => self::PHONE,
            'wa_message_id' => 'wamid.'.uniqid('', true),
            'type' => 'text',
            'text' => $text,
            'interactive_id' => null,
            'name' => 'Tester',
            'timestamp' => time(),
            'raw' => [],
        ];
    }

    private function mockIntent(array $resolveResult): void
    {
        $intent = Mockery::mock(IntentEngine::class);
        $intent->shouldReceive('resolve')->andReturn($resolveResult);
        $this->app->instance(IntentEngine::class, $intent);
    }

    public function test_mid_flow_message_lets_ai_switch_to_another_flow(): void
    {
        $this->seedUserAndAccount();
        $this->makeService('Instagram', 'Instagram Followers');

        $this->mockIntent([
            'handled' => true,
            'reply' => "Sure — let's top up *$20* instead. 💰",
            'follow_up' => null,
            'flow' => 'deposit',
            'flow_data' => ['amount' => 20],
        ]);

        $router = app(MessageRouter::class);
        $router->handle($this->msg('order'));                       // → order flow, pick_category
        $router->handle($this->msg('actually I want to deposit')); // invalid pick → AI → deposit

        $ctx = app(SessionManager::class)->load(self::PHONE);
        $this->assertSame('deposit', $ctx->flow);
        // The prefilled $20 fast-forwarded past ask_amount to the method menu.
        $this->assertSame('choose_method', $ctx->state);
        $this->assertEquals(20.0, (float) $ctx->get('deposit_amount'));
    }

    public function test_mid_flow_side_question_is_answered_and_step_reprompted(): void
    {
        $this->seedUserAndAccount();
        $this->makeService('Instagram', 'Instagram Followers');

        $this->mockIntent([
            'handled' => true,
            'reply' => 'Refills top you back up if numbers drop. ✅',
            'follow_up' => null,
            'flow' => null,
            'flow_data' => [],
        ]);

        $router = app(MessageRouter::class);
        $router->handle($this->msg('order'));
        $router->handle($this->msg('what does refill mean?'));

        // Answered, but the user is still exactly where they were.
        $ctx = app(SessionManager::class)->load(self::PHONE);
        $this->assertSame('order', $ctx->flow);
        $this->assertSame('pick_category', $ctx->state);

        $this->assertDatabaseHas('whatsapp_messages', [
            'wa_phone' => self::PHONE,
            'direction' => 'out',
            'body' => 'Refills top you back up if numbers drop. ✅',
        ]);
    }

    public function test_ai_unavailable_falls_back_to_flow_error_text(): void
    {
        $this->seedUserAndAccount();
        $this->makeService('Instagram', 'Instagram Followers');

        $this->mockIntent(['handled' => false]);

        $router = app(MessageRouter::class);
        $router->handle($this->msg('order'));
        $router->handle($this->msg('gibberish input'));

        $ctx = app(SessionManager::class)->load(self::PHONE);
        $this->assertSame('order', $ctx->flow);
        $this->assertSame('pick_category', $ctx->state);

        $this->assertDatabaseHas('whatsapp_messages', [
            'wa_phone' => self::PHONE,
            'direction' => 'out',
            'body' => 'Please reply with a valid number, or type *cancel*.',
        ]);
    }

    public function test_ai_can_adjust_quantity_at_confirm_without_losing_progress(): void
    {
        $user = $this->seedUserAndAccount();
        $service = $this->makeService('Instagram', 'Instagram Followers', 2.0);

        // User already walked to confirm with qty 1000...
        $ctx = new SessionContext(self::PHONE);
        $ctx->set('_user_id', $user->id);
        $engine = app(FlowEngine::class);
        $engine->start($ctx, 'order');
        $engine->advance($ctx, '1');
        $engine->advance($ctx, '1');
        $engine->advance($ctx, 'https://instagram.com/jane');
        $engine->advance($ctx, '1000');
        $this->assertSame('confirm', $ctx->state);

        // "make it 2000 instead" at confirm → retry (NOT a silent cancel).
        $res = $engine->advance($ctx, 'make it 2000 instead');
        $this->assertTrue($res->isRetry());

        // AI re-enters 'order' with only the new quantity — service + link survive.
        $ctx->set('_prefill_quantity', 2000);
        $res = $engine->start($ctx, 'order');

        $this->assertSame('confirm', $ctx->state);
        $this->assertSame($service->id, (int) $ctx->get('order_service_id'));
        $this->assertSame('https://instagram.com/jane', $ctx->get('order_link'));
        $this->assertSame(2000, (int) $ctx->get('order_quantity'));
        $this->assertStringContainsString('2000', (string) $res->reply);
    }
}
