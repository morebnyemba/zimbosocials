<?php

namespace Tests\Feature;

use App\Models\ManualPaymentDetail;
use App\Models\Transaction;
use App\Models\User;
use App\WhatsApp\Flow\Definitions\DepositFundsFlow;
use App\WhatsApp\Session\SessionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deposits are taken by manual transfer only and verified by a human. The
 * automatic gateway is switched off rather than deleted, so it can come back
 * without a code change — and while it is off, nothing may offer it.
 */
class ManualOnlyDepositsTest extends TestCase
{
    use RefreshDatabase;

    private function detail(): ManualPaymentDetail
    {
        return ManualPaymentDetail::create([
            'method_key' => 'manual_ecocash', 'label' => 'EcoCash',
            'account_name' => 'M NYEMBA', 'account_number' => '0787211325',
            'instructions' => 'Use your name as reference', 'is_active' => true, 'sort_order' => 1,
        ]);
    }

    private function context(User $user): SessionContext
    {
        $ctx = new SessionContext('263771234567');
        $ctx->set('_user_id', $user->id);
        $ctx->set('deposit_amount', 15.0);

        return $ctx;
    }

    public function test_no_instant_or_card_option_is_offered(): void
    {
        config(['services.deposits.whatsapp_gateway_enabled' => false]);
        $this->detail();
        $user = User::factory()->create(['balance' => 0]);

        $res = app(DepositFundsFlow::class)->handle('ask_amount', '15', $this->context($user));
        $rendered = json_encode($res->list ?? []).' '.$res->reply;

        $this->assertStringNotContainsStringIgnoringCase('instant', $rendered);
        $this->assertStringNotContainsStringIgnoringCase('card', $rendered);
        $this->assertStringContainsString('EcoCash', $rendered);
    }

    public function test_a_stale_instant_tap_cannot_start_a_gateway_payment(): void
    {
        config(['services.deposits.whatsapp_gateway_enabled' => false]);
        $this->detail();
        $user = User::factory()->create(['balance' => 0]);
        $ctx = $this->context($user);

        // A row from a list sent before the gateway was switched off.
        $ctx->set('deposit_method_map', [1 => 'g:ecocash']);
        $res = app(DepositFundsFlow::class)->handle('choose_method', '1', $ctx);

        // Back to the (manual-only) menu, never into the phone/OTP path.
        $this->assertNotSame('ask_phone', $res->nextState);
    }

    public function test_paying_without_a_screenshot_captures_the_sender_name(): void
    {
        config(['services.deposits.whatsapp_gateway_enabled' => false]);
        $detail = $this->detail();
        $user = User::factory()->create(['balance' => 0]);
        $ctx = $this->context($user);
        $ctx->set('deposit_method_map', [1 => 'm:'.$detail->id]);

        // Pick the manual method — details shown, pending deposit opened.
        $res = app(DepositFundsFlow::class)->handle('choose_method', '1', $ctx);
        $this->assertSame('awaiting_payment', $res->nextState);
        $this->assertStringContainsString('0787211325', $res->reply);

        // "done" → we ask who paid.
        $res = app(DepositFundsFlow::class)->handle('awaiting_payment', 'done', $ctx);
        $this->assertSame('awaiting_sender_name', $res->nextState);

        // The name lands on the deposit for an admin to match.
        $res = app(DepositFundsFlow::class)->handle('awaiting_sender_name', 'Tanaka Banda', $ctx);
        $tx = Transaction::where('user_id', $user->id)->latest('id')->first();
        $this->assertStringContainsString('Tanaka Banda', (string) $tx->notes);

        // Still pending, and nothing credited — a name is a claim, not proof.
        $this->assertSame('pending', $tx->status);
        $this->assertSame(0.0, (float) $user->fresh()->balance);
    }

    public function test_chatter_does_not_count_as_having_paid(): void
    {
        config(['services.deposits.whatsapp_gateway_enabled' => false]);
        $this->detail();
        $user = User::factory()->create(['balance' => 0]);
        $ctx = $this->context($user);

        $res = app(DepositFundsFlow::class)->handle('awaiting_payment', 'how long does it take?', $ctx);

        $this->assertSame('awaiting_payment', $res->nextState);
        $this->assertStringContainsStringIgnoringCase('screenshot', $res->reply);
    }

    public function test_a_meaningless_sender_name_is_refused(): void
    {
        config(['services.deposits.whatsapp_gateway_enabled' => false]);
        $this->detail();
        $user = User::factory()->create(['balance' => 0]);
        $ctx = $this->context($user);

        $res = app(DepositFundsFlow::class)->handle('awaiting_sender_name', '??', $ctx);

        $this->assertSame('awaiting_sender_name', $res->nextState);
    }

    public function test_the_gateway_can_be_switched_back_on(): void
    {
        config(['services.deposits.whatsapp_gateway_enabled' => true]);
        $this->detail();
        $user = User::factory()->create(['balance' => 0]);

        $res = app(DepositFundsFlow::class)->handle('ask_amount', '15', $this->context($user));

        // Nothing was deleted — it is a config switch, not a rewrite.
        $this->assertStringContainsStringIgnoringCase('instant', json_encode($res->list ?? []));
    }
}
