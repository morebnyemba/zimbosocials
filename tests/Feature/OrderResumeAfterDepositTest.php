<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceUpstream;
use App\Models\Transaction;
use App\Models\UpstreamProvider;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\DepositService;
use App\WhatsApp\Flow\FlowEngine;
use App\WhatsApp\Order\OrderResumeService;
use App\WhatsApp\Session\SessionContext;
use App\WhatsApp\Session\SessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A WhatsApp order that stalls at confirm for lack of funds auto-resumes to
 * the confirm step the moment the top-up is credited — from the webhook/poll
 * request, with no live chat session — and still only places on explicit yes.
 */
class OrderResumeAfterDepositTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '263771234567';

    private function makeService(): Service
    {
        $provider = UpstreamProvider::create([
            'name' => 'P', 'url' => 'https://example.com/api', 'api_key' => 'k', 'is_active' => true, 'balance' => 0,
        ]);
        $service = Service::create([
            'name' => 'Instagram Followers', 'name_sn' => 'x', 'description' => '', 'description_sn' => '',
            'category' => 'Instagram', 'type' => 'followers', 'rate' => 1.0,
            'min_qty' => 100, 'max_qty' => 100000, 'is_active' => true,
        ]);
        ServiceUpstream::create([
            'service_id' => $service->id, 'upstream_provider_id' => $provider->id,
            'external_service_id' => 'EXT-1', 'external_rate' => 0.5,
            'markup_type' => 'percentage', 'markup_value' => 100, 'priority' => 1, 'is_active' => true,
        ]);

        return $service;
    }

    private function linkedUser(float $balance): User
    {
        $user = User::factory()->create(['balance' => $balance]);
        WhatsAppAccount::create([
            'wa_phone' => self::PHONE, 'user_id' => $user->id, 'link_status' => 'linked', 'opted_in' => true,
        ]);

        return $user;
    }

    /** Drive the order flow to the insufficient-funds confirm (which stashes it). */
    private function stallOrderAtConfirm(User $user): void
    {
        $ctx = new SessionContext(self::PHONE);
        $ctx->set('_user_id', $user->id);
        $engine = app(FlowEngine::class);
        $engine->start($ctx, 'order');
        $engine->advance($ctx, '1');                                   // category
        $engine->advance($ctx, '1');                                   // service
        $engine->advance($ctx, 'https://instagram.com/jane');          // link
        $engine->advance($ctx, '1000');                                // quantity → confirm (short)
        app(SessionManager::class)->save($ctx);
    }

    private function pendingDeposit(User $user, float $amount): Transaction
    {
        return Transaction::create([
            'user_id' => $user->id, 'type' => 'deposit', 'amount' => $amount,
            'balance_before' => $user->balance, 'balance_after' => $user->balance,
            'method' => 'ecocash', 'status' => 'pending', 'notes' => 'test',
        ]);
    }

    public function test_order_resumes_to_confirm_when_the_deposit_credits(): void
    {
        $this->makeService();
        $user = $this->linkedUser(0.0);
        $this->stallOrderAtConfirm($user);

        // Stash was set at the short-confirm step.
        $this->assertNotNull(\Illuminate\Support\Facades\Cache::get('wa:resume_order:'.$user->id));

        // Credit a $1 deposit — the resume fires from here.
        app(DepositService::class)->credit($this->pendingDeposit($user, 1.0), 'test');

        // Session re-opened at order/confirm, funds now sufficient.
        $ctx = app(SessionManager::class)->load(self::PHONE);
        $this->assertSame('order', $ctx->flow);
        $this->assertSame('confirm', $ctx->state);

        // A proactive resume message with the Place-order button went out.
        $msg = \App\Models\WhatsAppMessage::where('direction', 'out')->latest('id')->first();
        $this->assertStringContainsString('topped up', $msg->body);
        $this->assertStringContainsString('Instagram Followers', $msg->body);
        $this->assertStringContainsString('1000', $msg->body);

        // Stash consumed.
        $this->assertNull(\Illuminate\Support\Facades\Cache::get('wa:resume_order:'.$user->id));
    }

    public function test_resumed_order_places_on_yes(): void
    {
        $this->makeService();
        $user = $this->linkedUser(0.0);
        $this->stallOrderAtConfirm($user);
        app(DepositService::class)->credit($this->pendingDeposit($user, 5.0), 'test');

        // The user taps "Place order" (fs:yes) at the resumed confirm step.
        $ctx = app(SessionManager::class)->load(self::PHONE);
        $res = app(FlowEngine::class)->advance($ctx, 'yes');

        $this->assertStringContainsString('Order placed', (string) $res->reply);
        // Order created + charged from the resumed confirm (dispatch status
        // depends on the upstream call, which isn't mocked here).
        $this->assertDatabaseHas('orders', ['user_id' => $user->id, 'quantity' => 1000, 'link' => 'https://instagram.com/jane']);
        $this->assertLessThan(5.0, (float) $user->fresh()->balance); // charge debited
    }

    public function test_partial_topup_keeps_the_stash_for_later(): void
    {
        $service = $this->makeService();
        $service->update(['rate' => 10.0]); // order will cost $10
        $user = $this->linkedUser(0.0);
        $this->stallOrderAtConfirm($user);

        // Only $3 credited — still short of $10.
        app(DepositService::class)->credit($this->pendingDeposit($user, 3.0), 'test');

        // Stash preserved so the next top-up can finish it; no resume yet.
        $this->assertNotNull(\Illuminate\Support\Facades\Cache::get('wa:resume_order:'.$user->id));
    }

    public function test_deposit_without_a_stashed_order_sends_plain_confirmation(): void
    {
        $user = $this->linkedUser(0.0);

        app(DepositService::class)->credit($this->pendingDeposit($user, 5.0), 'test');

        // No order session created, but the user IS told their deposit landed.
        $ctx = app(SessionManager::class)->load(self::PHONE);
        $this->assertNull($ctx->flow);

        $out = \App\Models\WhatsAppMessage::where('direction', 'out')->latest('id')->first();
        $this->assertStringContainsString('Deposit confirmed', (string) $out->body);
        $this->assertStringContainsString('5.00', (string) $out->body);
    }

    public function test_failed_deposit_tells_the_user_conversationally(): void
    {
        $user = $this->linkedUser(0.0);
        $tx = $this->pendingDeposit($user, 10.0);

        app(DepositService::class)->reject($tx, 'gateway_poller');

        // Immediate, clear, actionable — not silence + menu.
        $out = \App\Models\WhatsAppMessage::where('direction', 'out')->latest('id')->first();
        $this->assertStringContainsString("didn't go through", (string) $out->body);
        $this->assertStringContainsString('No money was taken', (string) $out->body);
        $this->assertStringContainsString('10.00', (string) $out->body);
        $this->assertStringContainsString('deposit', (string) $out->body); // retry hint
    }

    public function test_failed_deposit_reassures_when_an_order_is_waiting(): void
    {
        $this->makeService();
        $user = $this->linkedUser(0.0);
        $this->stallOrderAtConfirm($user); // stashes an order

        app(DepositService::class)->reject($this->pendingDeposit($user, 10.0), 'gateway_poller');

        $out = \App\Models\WhatsAppMessage::where('direction', 'out')->latest('id')->first();
        $this->assertStringContainsString('order is still saved', (string) $out->body);
        // Stash preserved so a later top-up still finishes it.
        $this->assertNotNull(\Illuminate\Support\Facades\Cache::get('wa:resume_order:'.$user->id));
    }
}
