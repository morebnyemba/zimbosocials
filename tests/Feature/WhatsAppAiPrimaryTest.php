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

    /** An interactive tap (menu row / button) — deterministic, never AI. */
    private function tap(string $id): array
    {
        return array_merge($this->msg(''), ['type' => 'interactive', 'interactive_id' => $id]);
    }

    private function mockIntent(array $resolveResult, ?string $voiced = null): void
    {
        $intent = Mockery::mock(IntentEngine::class);
        $intent->shouldReceive('resolve')->andReturn($resolveResult);
        // The one-voice pass; null = fall back to the scripted step text.
        $intent->shouldReceive('voice')->andReturn($voiced)->byDefault();
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
        $router->handle($this->tap('fl_order')); // menu tap → order flow, pick_category
        $router->handle($this->msg('actually I want to deposit')); // invalid pick → AI → deposit

        $ctx = app(SessionManager::class)->load(self::PHONE);
        $this->assertSame('deposit', $ctx->flow);
        // The prefilled $20 fast-forwarded past ask_amount to the method menu.
        $this->assertSame('choose_method', $ctx->state);
        $this->assertEquals(20.0, (float) $ctx->get('deposit_amount'));
    }

    public function test_mid_flow_side_question_gets_one_answer_and_no_step_reblast(): void
    {
        $this->seedUserAndAccount();
        $this->makeService('Instagram', 'Instagram Followers');

        $this->mockIntent([
            'handled' => true,
            'reply' => 'Refills top you back up if numbers drop. ✅ So — which platform are we growing?',
            'follow_up' => null,
            'flow' => null,
            'flow_data' => [],
        ]);

        $router = app(MessageRouter::class);
        $router->handle($this->tap('fl_order'));
        $before = \Illuminate\Support\Facades\DB::table('whatsapp_messages')->where('direction', 'out')->count();
        $router->handle($this->msg('what does refill mean?'));

        // Flow state untouched — their next input still lands on the step.
        $ctx = app(SessionManager::class)->load(self::PHONE);
        $this->assertSame('order', $ctx->flow);
        $this->assertSame('pick_category', $ctx->state);

        // ONE voice: only the AI's answer went out; the step prompt was NOT
        // re-sent on top of it (the AI steers back in its own words).
        $out = \Illuminate\Support\Facades\DB::table('whatsapp_messages')->where('direction', 'out')->count();
        $this->assertSame($before + 1, $out);
        $this->assertDatabaseHas('whatsapp_messages', [
            'wa_phone' => self::PHONE,
            'direction' => 'out',
            'body' => 'Refills top you back up if numbers drop. ✅ So — which platform are we growing?',
        ]);
    }

    public function test_ai_flow_trigger_speaks_with_one_voiced_message(): void
    {
        $this->seedUserAndAccount();

        // AI decides deposit AND the voice pass fuses draft + step into one text.
        $this->mockIntent([
            'handled' => true,
            'reply' => "Sure — let's top up *\$20*! 💰",
            'follow_up' => 'Want anything else?', // must be suppressed on flow turns
            'flow' => 'deposit',
            'flow_data' => ['amount' => 20],
        ], voiced: "Sure — let's top up *\$20*! 💰 How would you like to pay?");

        $before = \Illuminate\Support\Facades\DB::table('whatsapp_messages')->where('direction', 'out')->count();
        app(MessageRouter::class)->handle($this->msg('deposit 20'));

        // Exactly ONE outbound message: the fused voice as the body, with the
        // method list attached (flattened into the text fallback in tests).
        $out = \Illuminate\Support\Facades\DB::table('whatsapp_messages')->where('direction', 'out')->get()->slice($before);
        $this->assertCount(1, $out);
        $this->assertStringContainsString("Sure — let's top up *\$20*! 💰 How would you like to pay?", $out->first()->body);
        // The scripted step text was replaced, not duplicated.
        $this->assertStringNotContainsString('Deposit *20.00', $out->first()->body);

        $ctx = app(SessionManager::class)->load(self::PHONE);
        $this->assertSame('deposit', $ctx->flow);
        $this->assertSame('choose_method', $ctx->state);
    }

    public function test_money_confirm_steps_are_never_voiced(): void
    {
        $this->seedUserAndAccount();

        $intent = Mockery::mock(IntentEngine::class);
        $intent->shouldReceive('resolve')->andReturn([
            'handled' => true,
            'reply' => 'Setting up your EcoCash top-up now!',
            'follow_up' => null,
            'flow' => 'deposit',
            'flow_data' => ['amount' => 10, 'method' => 'ecocash', 'phone' => '0771234567'],
        ]);
        // Full prefills land on the CONFIRM step — the voice pass must not run.
        $intent->shouldNotReceive('voice');
        $this->app->instance(IntentEngine::class, $intent);

        app(MessageRouter::class)->handle($this->msg('deposit 10 by ecocash on 0771234567'));

        $ctx = app(SessionManager::class)->load(self::PHONE);
        $this->assertSame('confirm', $ctx->state);

        // The verbatim scripted summary (exact amounts) is what went out.
        $confirm = \App\Models\WhatsAppMessage::where('direction', 'out')->latest('id')->first();
        $this->assertStringContainsString('10.00', $confirm->body);
        $this->assertStringContainsString('EcoCash', $confirm->body);
    }

    public function test_ai_unavailable_falls_back_to_flow_error_text(): void
    {
        $this->seedUserAndAccount();
        $this->makeService('Instagram', 'Instagram Followers');

        $this->mockIntent(['handled' => false]);

        $router = app(MessageRouter::class);
        $router->handle($this->tap('fl_order'));
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

    public function test_ai_answer_outside_flow_is_not_followed_by_menu(): void
    {
        $this->seedUserAndAccount();

        $this->mockIntent([
            'handled' => true,
            'reply' => 'We deliver most orders within a few hours. ⏱️',
            'follow_up' => null,
            'flow' => null,
            'flow_data' => [],
        ]);

        app(MessageRouter::class)->handle($this->msg('how fast is delivery?'));

        // Exactly one outbound message: the AI reply. No menu chaser.
        $this->assertSame(
            1,
            \Illuminate\Support\Facades\DB::table('whatsapp_messages')->where('direction', 'out')->count()
        );
    }

    public function test_ai_naming_current_flow_without_data_does_not_restart_it(): void
    {
        $this->seedUserAndAccount();
        $this->makeService('Instagram', 'Instagram Followers');

        // Model echoes the active flow (a common failure mode) with no new data.
        $this->mockIntent([
            'handled' => true,
            'reply' => 'Instagram Followers are our most popular! 🚀',
            'follow_up' => null,
            'flow' => 'order',
            'flow_data' => [],
        ]);

        $router = app(MessageRouter::class);
        $router->handle($this->tap('fl_order'));
        $router->handle($this->msg('which one is most popular?'));

        // Still in the same flow at the same step — re-rendered, not restarted.
        $ctx = app(SessionManager::class)->load(self::PHONE);
        $this->assertSame('order', $ctx->flow);
        $this->assertSame('pick_category', $ctx->state);
    }

    public function test_completed_flow_is_not_followed_by_menu(): void
    {
        $this->seedUserAndAccount();
        $this->mockIntent(['handled' => false]); // deterministic path

        // 'balance' completes in one step: exactly one outbound (the balance
        // card) — no menu chaser after the flow finishes.
        app(MessageRouter::class)->handle($this->msg('balance'));

        $this->assertSame(
            1,
            \Illuminate\Support\Facades\DB::table('whatsapp_messages')->where('direction', 'out')->count()
        );
    }

    public function test_global_ai_budget_caps_spend_across_all_users(): void
    {
        config(['services.whatsapp.ai_global_daily_limit' => 2]);
        $guard = app(\App\WhatsApp\AI\AIGuard::class);

        $guard->record('263770000001');
        $guard->record('263770000002');

        // Two calls consumed the global budget — a third phone is refused
        // even though its own per-phone counter is zero.
        $this->assertFalse($guard->allow('263770000003'));

        config(['services.whatsapp.ai_global_daily_limit' => 0]);
        $this->assertTrue($guard->allow('263770000003')); // 0 = unlimited
    }

    public function test_session_lock_is_released_after_handling(): void
    {
        $this->seedUserAndAccount();
        $this->mockIntent(['handled' => false]);

        app(MessageRouter::class)->handle($this->msg('menu'));

        // The per-phone lock must be free again for the next message.
        $lock = \Illuminate\Support\Facades\Cache::lock('wa:sess-lock:'.self::PHONE, 1);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    public function test_response_schema_constrains_flow_to_real_ids(): void
    {
        $schema = \App\WhatsApp\AI\GeminiProvider::responseSchema();
        $enum = $schema['properties']['flow']['enum'];

        $this->assertContains('order', $enum);
        $this->assertContains('handoff', $enum);
        $this->assertContains('none', $enum);
        $this->assertNotContains('nonexistent_flow', $enum);
        $this->assertSame(['reply'], $schema['required']);
    }

    public function test_provider_normalizes_none_flow_and_passes_handoff(): void
    {
        $kb = app(\App\WhatsApp\Intent\KnowledgeBase::class);

        $client = Mockery::mock(\App\Services\AI\GeminiClient::class);
        $client->shouldReceive('isConfigured')->andReturn(true);
        $client->shouldReceive('generateJson')->andReturn(
            ['reply' => 'Hey there!', 'flow' => 'none', 'flow_data' => []],
            ['reply' => 'A human will reply shortly.', 'flow' => 'handoff', 'flow_data' => []],
        );

        $provider = new \App\WhatsApp\AI\GeminiProvider($client, $kb);
        $ctx = ['user' => null, 'authenticated' => true, 'history' => []];

        $this->assertNull($provider->respond('hi', $ctx)['flow']);
        $res = $provider->respond('i need a human', $ctx);
        $this->assertSame('handoff', $res['flow']);
        $this->assertSame(\App\WhatsApp\AI\GeminiProvider::PROMPT_VERSION, $res['prompt_version']);
    }

    public function test_handoff_pauses_bot_and_alerts_admins_without_starting_a_flow(): void
    {
        $this->seedUserAndAccount();

        $this->mockIntent([
            'handled' => true,
            'reply' => "I'm bringing in a team member right now. 🙏",
            'follow_up' => null,
            'flow' => 'handoff',
            'flow_data' => [],
        ]);

        app(MessageRouter::class)->handle($this->msg('my deposit is missing, this is a scam!!'));

        $account = \App\Models\WhatsAppAccount::where('wa_phone', self::PHONE)->first();
        $this->assertTrue($account->inAgentHandoff());

        $ctx = app(SessionManager::class)->load(self::PHONE);
        $this->assertNull($ctx->flow);

        // Follow-up messages are now left for the human agent (bot silent).
        app(MessageRouter::class)->handle($this->msg('hello??'));
        $this->assertSame(
            1,
            \Illuminate\Support\Facades\DB::table('whatsapp_messages')->where('direction', 'out')->count()
        );
    }

    public function test_ai_decision_is_logged_with_prompt_version(): void
    {
        $this->seedUserAndAccount();

        $this->mockIntent([
            'handled' => true,
            'reply' => 'Let me set that up! 🚀',
            'follow_up' => null,
            'flow' => 'deposit',
            'flow_data' => ['amount' => 20],
            'prompt_version' => \App\WhatsApp\AI\GeminiProvider::PROMPT_VERSION,
        ]);

        app(MessageRouter::class)->handle($this->msg('deposit 20'));

        $out = \App\Models\WhatsAppMessage::where('direction', 'out')->where('handled_by', 'ai')->first();
        $this->assertNotNull($out);
        $this->assertSame('deposit', $out->payload['flow']);
        $this->assertSame(\App\WhatsApp\AI\GeminiProvider::PROMPT_VERSION, $out->payload['prompt_version']);
    }

    public function test_stale_button_tap_after_completed_flow_gets_nudge_not_menu(): void
    {
        $this->seedUserAndAccount();

        // Tap a flow-internal button with NO active flow (e.g. double-tapping
        // ✅ Place order after the first tap already completed the order).
        app(MessageRouter::class)->handle($this->tap('fs:yes'));

        $out = \Illuminate\Support\Facades\DB::table('whatsapp_messages')->where('direction', 'out')->get();
        $this->assertCount(1, $out);
        $this->assertStringContainsString('expired', $out[0]->body);
    }

    public function test_control_keywords_never_consult_the_ai(): void
    {
        $this->seedUserAndAccount();

        $intent = Mockery::mock(IntentEngine::class);
        $intent->shouldNotReceive('resolve');
        $this->app->instance(IntentEngine::class, $intent);

        $router = app(MessageRouter::class);
        $router->handle($this->msg('menu'));
        $router->handle($this->msg('cancel'));
        $router->handle($this->msg('help'));

        $ctx = app(SessionManager::class)->load(self::PHONE);
        $this->assertNull($ctx->flow);
    }

    public function test_shortcut_keywords_go_through_ai_first(): void
    {
        $this->seedUserAndAccount();

        $this->mockIntent([
            'handled' => true,
            'reply' => "Let's top up your wallet! 💰",
            'follow_up' => null,
            'flow' => 'deposit',
            'flow_data' => ['amount' => 10],
        ]);

        app(MessageRouter::class)->handle($this->msg('deposit'));

        $ctx = app(SessionManager::class)->load(self::PHONE);
        $this->assertSame('deposit', $ctx->flow);
        $this->assertSame('choose_method', $ctx->state); // AI's $10 prefill applied
    }

    public function test_shortcut_keywords_fall_back_to_deterministic_command_when_ai_unavailable(): void
    {
        $this->seedUserAndAccount();

        $this->mockIntent(['handled' => false]);

        app(MessageRouter::class)->handle($this->msg('deposit'));

        $ctx = app(SessionManager::class)->load(self::PHONE);
        $this->assertSame('deposit', $ctx->flow);
        $this->assertSame('ask_amount', $ctx->state); // no AI prefill — flow asks
    }

    /**
     * "Ndoda uise matiktok followers 100 pa link iyi tiktok.com/@mamaanitah" —
     * the AI extracts service + bare link + quantity; the flow must normalize
     * the scheme-less link and fast-forward all the way to confirm.
     */
    public function test_bare_link_from_ai_is_normalized_and_flow_jumps_to_confirm(): void
    {
        $user = $this->seedUserAndAccount();
        $service = $this->makeService('TikTok', 'TikTok Followers', 2.0);

        $ctx = new SessionContext(self::PHONE);
        $ctx->set('_user_id', $user->id);
        $ctx->set('_prefill_service_id', $service->id);
        $ctx->set('_prefill_link', 'tiktok.com/@mamaanitah');
        $ctx->set('_prefill_quantity', 100);

        $res = app(FlowEngine::class)->start($ctx, 'order');

        $this->assertSame('confirm', $ctx->state);
        $this->assertSame('https://tiktok.com/@mamaanitah', $ctx->get('order_link'));
        $this->assertStringContainsString('Confirm your order', (string) $res->reply);
    }

    public function test_typed_bare_link_is_accepted_at_enter_link(): void
    {
        $user = $this->seedUserAndAccount();
        $this->makeService('TikTok', 'TikTok Followers');

        $ctx = new SessionContext(self::PHONE);
        $ctx->set('_user_id', $user->id);
        $engine = app(FlowEngine::class);
        $engine->start($ctx, 'order');
        $engine->advance($ctx, '1');
        $engine->advance($ctx, '1');
        $engine->advance($ctx, 'tiktok.com/@mamaanitah');

        $this->assertSame('enter_quantity', $ctx->state);
        $this->assertSame('https://tiktok.com/@mamaanitah', $ctx->get('order_link'));
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
