<?php

namespace Tests\Feature;

use App\Models\AdvertBooking;
use App\Models\User;
use App\WhatsApp\Flow\FlowEngine;
use App\WhatsApp\Session\SessionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Booking a sponsored advert takes real money, so it follows the same rules as
 * an order: nothing is charged until an explicit confirmation, the wallet is
 * checked again under a lock at that moment, and a short balance routes to a
 * top-up instead of failing.
 */
class AdvertiseFlowTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '263771234567';

    private function start(float $balance): array
    {
        $user = User::factory()->create(['balance' => $balance]);
        $ctx = new SessionContext(self::PHONE);
        $ctx->set('_user_id', $user->id);
        $engine = app(FlowEngine::class);
        $engine->start($ctx, 'advertise');

        return [$engine, $ctx, $user];
    }

    /** Drive to the confirm step: standard package ($30/wk), 2 weeks = $60. */
    private function toConfirm(FlowEngine $engine, SessionContext $ctx): void
    {
        $engine->advance($ctx, '2');                       // Standard
        $engine->advance($ctx, 'my salon in Chitungwiza'); // promoting
        $engine->advance($ctx, 'https://facebook.com/salon');
        $engine->advance($ctx, '2');                       // weeks
    }

    public function test_a_confirmed_advert_charges_the_wallet_and_books_it(): void
    {
        [$engine, $ctx, $user] = $this->start(balance: 100);
        $this->toConfirm($engine, $ctx);

        $this->assertSame('confirm', $ctx->state);
        // Nothing charged yet — confirm is the money gate.
        $this->assertSame(100.0, (float) $user->fresh()->balance);

        $res = $engine->advance($ctx, 'yes');

        $this->assertStringContainsString('booked', (string) $res->reply);
        $this->assertDatabaseHas('advert_bookings', [
            'user_id' => $user->id,
            'package' => 'standard',
            'weeks' => 2,
            'total' => 60.00,
            'status' => 'pending_setup',
            'promoting' => 'my salon in Chitungwiza',
        ]);
        $this->assertSame(40.0, (float) $user->fresh()->balance); // 100 - 60
        // The charge is on the ledger.
        $this->assertDatabaseHas('transactions', ['user_id' => $user->id, 'type' => 'order_charge', 'amount' => -60.0]);
    }

    public function test_an_unconfirmed_advert_never_charges(): void
    {
        [$engine, $ctx, $user] = $this->start(balance: 100);
        $this->toConfirm($engine, $ctx);

        $engine->advance($ctx, 'no');

        $this->assertSame(100.0, (float) $user->fresh()->balance);
        $this->assertDatabaseCount('advert_bookings', 0);
    }

    public function test_a_short_balance_offers_a_top_up_with_the_exact_shortfall(): void
    {
        [$engine, $ctx, $user] = $this->start(balance: 10); // needs 60
        $this->toConfirm($engine, $ctx);

        $res = $engine->resume($ctx);

        $this->assertStringContainsString('short', (string) $res->reply);
        $this->assertSame('fl_deposit', $res->buttons[0]['id']);
        // The deposit flow is handed the exact amount still needed.
        $this->assertSame(50.0, (float) $ctx->get('_prefill_amount'));
        $this->assertDatabaseCount('advert_bookings', 0);
    }

    public function test_the_ai_can_open_the_flow_already_filled_in(): void
    {
        $user = User::factory()->create(['balance' => 100]);
        $ctx = new SessionContext(self::PHONE);
        $ctx->set('_user_id', $user->id);
        $ctx->set('_prefill_package', 'max');
        $ctx->set('_prefill_promoting', 'a launch on Saturday');
        $ctx->set('_prefill_link', 'https://facebook.com/launch');
        $ctx->set('_prefill_weeks', 1);

        $res = app(FlowEngine::class)->start($ctx, 'advertise');

        // Everything gathered → straight to the money gate.
        $this->assertSame('confirm', $ctx->state);
        $this->assertStringContainsString('50.00', (string) $res->reply); // max = $50/wk
    }
}
