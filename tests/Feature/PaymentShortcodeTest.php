<?php

namespace Tests\Feature;

use App\Models\ManualPaymentDetail;
use App\Models\User;
use App\WhatsApp\Flow\Definitions\DepositFundsFlow;
use App\WhatsApp\Session\SessionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dial code has to carry the amount the customer actually owes.
 *
 * It used to be typed into the free-text instructions, which meant it froze
 * whatever amount the admin had in mind when they wrote it — one live account
 * was telling every customer to send exactly 10 USD regardless of what they
 * were depositing.
 */
class PaymentShortcodeTest extends TestCase
{
    use RefreshDatabase;

    private function ecocash(?string $template = '*151*1*1*{number}*{amount}#'): ManualPaymentDetail
    {
        return ManualPaymentDetail::create([
            'method_key' => 'manual_ecocash', 'label' => 'EcoCash',
            'account_name' => 'M NYEMBA', 'account_number' => '0787211325',
            'ussd_template' => $template, 'is_active' => true, 'sort_order' => 1,
        ]);
    }

    public function test_the_code_carries_the_amount_being_paid(): void
    {
        $detail = $this->ecocash();

        $this->assertSame('*151*1*1*0787211325*10#', $detail->ussdFor(10));
        $this->assertSame('*151*1*1*0787211325*7.5#', $detail->ussdFor(7.50));
        $this->assertSame('*151*1*1*0787211325*15#', $detail->ussdFor(15.00));
    }

    public function test_a_method_without_a_code_shows_none(): void
    {
        // Better nothing than a code that dials the wrong thing.
        $this->assertNull($this->ecocash(null)->ussdFor(10));
        $this->assertNull($this->ecocash('')->ussdFor(10));
    }

    public function test_a_zero_amount_produces_no_code(): void
    {
        $this->assertNull($this->ecocash()->ussdFor(0));
    }

    public function test_spaces_in_a_stored_number_do_not_break_the_code(): void
    {
        $detail = $this->ecocash();
        $detail->account_number = '078 721 1325';

        $this->assertSame('*151*1*1*0787211325*10#', $detail->ussdFor(10));
    }

    public function test_the_deposit_message_offers_a_tappable_code(): void
    {
        config(['services.deposits.whatsapp_gateway_enabled' => false]);
        $detail = $this->ecocash();
        $user = User::factory()->create(['balance' => 0]);

        $ctx = new SessionContext('263771234567');
        $ctx->set('_user_id', $user->id);
        $ctx->set('deposit_amount', 15.0);
        $ctx->set('deposit_method_map', [1 => 'm:'.$detail->id]);

        $res = app(DepositFundsFlow::class)->handle('choose_method', '1', $ctx);

        // The amount they owe, not one baked in months ago.
        $this->assertStringContainsString('*151*1*1*0787211325*15#', $res->reply);
        $this->assertStringContainsString('0787211325', $res->reply);
    }
}
