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

    /** Pick option 3 = the flat "1 week" package ($20) — payment is the only step. */
    private function toConfirm(FlowEngine $engine, SessionContext $ctx): void
    {
        $engine->advance($ctx, '3'); // 1 week — $20 → straight to confirm
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
            'package' => 'week1',
            'days' => 7,
            'total' => 20.00,
            'status' => 'pending_setup',
        ]);
        // Details are collected by the team afterwards — not at booking.
        $this->assertNull(\App\Models\AdvertBooking::first()->promoting);
        $this->assertSame(80.0, (float) $user->fresh()->balance); // 100 - 20
        $this->assertDatabaseHas('transactions', ['user_id' => $user->id, 'type' => 'order_charge', 'amount' => -20.0]);
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
        [$engine, $ctx, $user] = $this->start(balance: 5); // week1 needs 20
        $this->toConfirm($engine, $ctx);

        $res = $engine->resume($ctx);

        $this->assertStringContainsString('short', (string) $res->reply);
        $this->assertSame('fl_deposit', $res->buttons[0]['id']);
        // The deposit flow is handed the exact amount still needed.
        $this->assertSame(15.0, (float) $ctx->get('_prefill_amount'));
        $this->assertDatabaseCount('advert_bookings', 0);
    }

    public function test_the_ai_can_open_the_flow_already_filled_in(): void
    {
        $user = User::factory()->create(['balance' => 100]);
        $ctx = new SessionContext(self::PHONE);
        $ctx->set('_user_id', $user->id);
        $ctx->set('_prefill_package', 'month1');

        $res = app(FlowEngine::class)->start($ctx, 'advertise');

        // Everything gathered → straight to the money gate.
        $this->assertSame('confirm', $ctx->state);
        $this->assertStringContainsString('60.00', (string) $res->reply); // 1 month = $60 flat
    }

    public function test_video_packages_are_flagged_and_boost_only_ones_are_not(): void
    {
        // week1 ($20) includes a video; day3 ($10) is boost-only.
        $videoBooking = \App\Models\AdvertBooking::create([
            'user_id' => User::factory()->create()->id, 'wa_phone' => self::PHONE,
            'package' => 'week1', 'days' => 7, 'total' => 20.0, 'promoting' => 'x', 'status' => 'pending_setup',
        ]);
        $boostBooking = \App\Models\AdvertBooking::create([
            'user_id' => User::factory()->create()->id, 'wa_phone' => self::PHONE,
            'package' => 'day3', 'days' => 3, 'total' => 10.0, 'promoting' => 'x', 'status' => 'pending_setup',
        ]);

        $this->assertTrue($videoBooking->includesVideo());
        $this->assertFalse($boostBooking->includesVideo());
    }

    public function test_both_short_day_runs_and_longer_packages_are_offered(): void
    {
        [$engine, $ctx] = $this->start(balance: 0);

        $res = $engine->resume($ctx); // the package menu
        $titles = collect($res->list['sections'][0]['rows'])->pluck('title')->implode(' | ');

        // A cheap day test AND longer week/month options, all flat-priced.
        $this->assertStringContainsString('1 day — $5.00', $titles);
        $this->assertStringContainsString('3 days — $10.00', $titles);
        $this->assertStringContainsString('1 week — $20.00', $titles);
        $this->assertStringContainsString('1 month — $60.00', $titles);
    }
}
