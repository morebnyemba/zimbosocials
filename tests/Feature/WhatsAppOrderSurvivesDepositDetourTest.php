<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppSavedOrder;
use App\Services\AI\GeminiClient;
use App\Services\DepositService;
use App\WhatsApp\Routing\MessageRouter;
use App\WhatsApp\Session\SessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Seen live: a customer had picked a service and quantity, was mid the
 * order flow's enter_link step, then detoured to deposit funds. The deposit
 * flow completing wiped order_service_id/order_quantity/order_link (any
 * flow completion resets every non-'_' context key), so once she topped up,
 * the order restarted from a blank slate and asked for everything again —
 * even a TikTok handle she'd already confirmed in chat minutes earlier.
 *
 * Fix: switching away from an in-progress order now stashes what's already
 * known via OrderResumeService, so the existing deposit-resume path has it.
 */
class WhatsAppOrderSurvivesDepositDetourTest extends TestCase
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

    private function linkedUser(float $balance = 0): User
    {
        $user = User::factory()->create(['balance' => $balance]);
        WhatsAppAccount::create([
            'wa_phone' => self::PHONE, 'user_id' => $user->id,
            'link_status' => 'linked', 'opted_in' => true,
        ]);

        return $user;
    }

    private function service(): Service
    {
        return Service::create([
            'name' => 'TikTok Followers', 'name_sn' => 'x', 'description' => '', 'description_sn' => '',
            'category' => 'TikTok', 'type' => 'followers', 'rate' => 10.0,
            'min_qty' => 10, 'max_qty' => 100000,
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

    private function mockAiSwitchesToDeposit(): void
    {
        $mock = Mockery::mock(GeminiClient::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('generateJson')->andReturn([
            'reply' => "Sure, let's get that dollar added to your wallet!",
            'follow_up' => null, 'flow' => 'deposit', 'flow_data' => ['amount' => 1],
        ]);
        $this->app->instance(GeminiClient::class, $mock);
    }

    public function test_an_in_progress_order_survives_a_deposit_detour_and_resumes_at_the_link_step(): void
    {
        $user = $this->linkedUser(balance: 0);
        $service = $this->service();

        $sessions = app(SessionManager::class);
        $ctx = $sessions->load(self::PHONE);
        $ctx->setFlow('order', 'enter_link');
        $ctx->set('order_service_id', $service->id);
        $ctx->set('order_quantity', 100);
        $sessions->save($ctx);

        $this->mockAiSwitchesToDeposit();
        app(MessageRouter::class)->handle($this->textMessage('i just want to add a dollar'));

        // The in-progress order was stashed before the deposit flow took over.
        $stash = WhatsAppSavedOrder::where('user_id', $user->id)->first();
        $this->assertNotNull($stash, 'the order should be stashed when switching away to deposit');
        $this->assertSame($service->id, $stash->service_id);
        $this->assertSame(100, $stash->quantity);
        $this->assertSame('', (string) $stash->link);

        // Now the deposit actually clears (e.g. via the admin/WhatsApp approval path).
        $tx = Transaction::create([
            'user_id' => $user->id, 'type' => 'deposit', 'amount' => 1.0,
            'balance_before' => 0, 'balance_after' => 0,
            'method' => 'manual_ecocash', 'status' => 'pending',
        ]);
        app(DepositService::class)->credit($tx, 'test');

        // The order resumed — landing back on enter_link (link is still the
        // only thing missing), NOT a fresh start that forgot the service/qty.
        $after = $sessions->load(self::PHONE);
        $this->assertSame('order', $after->flow);
        $this->assertSame('enter_link', $after->state);
        $this->assertSame($service->id, (int) $after->get('order_service_id'));
        $this->assertSame(100, (int) $after->get('order_quantity'));
    }

    public function test_a_confirmed_link_survives_the_detour_too_and_resumes_straight_to_confirm(): void
    {
        $user = $this->linkedUser(balance: 0);
        $service = $this->service();

        $sessions = app(SessionManager::class);
        $ctx = $sessions->load(self::PHONE);
        $ctx->setFlow('order', 'enter_quantity');
        $ctx->set('order_service_id', $service->id);
        $ctx->set('order_quantity', 100);
        $ctx->set('order_link', 'https://tiktok.com/@lucia06');
        $sessions->save($ctx);

        $this->mockAiSwitchesToDeposit();
        app(MessageRouter::class)->handle($this->textMessage('i just want to add a dollar'));

        $stash = WhatsAppSavedOrder::where('user_id', $user->id)->first();
        $this->assertSame('https://tiktok.com/@lucia06', $stash->link);

        $tx = Transaction::create([
            'user_id' => $user->id, 'type' => 'deposit', 'amount' => 1000.0,
            'balance_before' => 0, 'balance_after' => 0,
            'method' => 'manual_ecocash', 'status' => 'pending',
        ]);
        app(DepositService::class)->credit($tx, 'test');

        $after = $sessions->load(self::PHONE);
        $this->assertSame('order', $after->flow);
        $this->assertSame('confirm', $after->state);
    }
}
