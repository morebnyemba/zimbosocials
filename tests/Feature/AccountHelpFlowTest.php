<?php

namespace Tests\Feature;

use App\Models\ExtraServiceBooking;
use App\Models\User;
use App\WhatsApp\Flow\FlowEngine;
use App\WhatsApp\Session\SessionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Booking a one-off account/advertising service takes real money, so it
 * follows the same rules as an advert booking: nothing is charged until an
 * explicit confirmation, the wallet is checked again under a lock at that
 * moment, and a short balance routes to a top-up instead of failing.
 */
class AccountHelpFlowTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '263771234567';

    private function start(float $balance): array
    {
        $user = User::factory()->create(['balance' => $balance]);
        $ctx = new SessionContext(self::PHONE);
        $ctx->set('_user_id', $user->id);
        $engine = app(FlowEngine::class);
        $engine->start($ctx, 'account_help');

        return [$engine, $ctx, $user];
    }

    /** Pick option 3 = "Account recovery & unbanning" ($10). */
    private function toConfirm(FlowEngine $engine, SessionContext $ctx): void
    {
        $engine->advance($ctx, '3');
    }

    public function test_a_confirmed_request_charges_the_wallet_and_books_it(): void
    {
        [$engine, $ctx, $user] = $this->start(balance: 100);
        $this->toConfirm($engine, $ctx);

        $this->assertSame('confirm', $ctx->state);
        $this->assertSame(100.0, (float) $user->fresh()->balance);

        $res = $engine->advance($ctx, 'yes');

        $this->assertStringContainsString('booked', mb_strtolower((string) $res->reply));
        $this->assertDatabaseHas('extra_service_bookings', [
            'user_id' => $user->id,
            'item' => 'account_recovery',
            'total' => 10.00,
            'status' => 'pending_setup',
        ]);
        $this->assertSame(90.0, (float) $user->fresh()->balance); // 100 - 10
        $this->assertDatabaseHas('transactions', ['user_id' => $user->id, 'type' => 'order_charge', 'amount' => -10.0]);
    }

    public function test_an_unconfirmed_request_never_charges(): void
    {
        [$engine, $ctx, $user] = $this->start(balance: 100);
        $this->toConfirm($engine, $ctx);

        $engine->advance($ctx, 'no');

        $this->assertSame(100.0, (float) $user->fresh()->balance);
        $this->assertDatabaseCount('extra_service_bookings', 0);
    }

    public function test_a_short_balance_offers_a_top_up_with_the_exact_shortfall(): void
    {
        [$engine, $ctx, $user] = $this->start(balance: 2); // account_recovery needs 10
        $this->toConfirm($engine, $ctx);

        $res = $engine->resume($ctx);

        $this->assertStringContainsString('short', (string) $res->reply);
        $this->assertSame('fl_deposit', $res->buttons[0]['id']);
        $this->assertSame(8.0, (float) $ctx->get('_prefill_amount'));
        $this->assertDatabaseCount('extra_service_bookings', 0);
    }

    public function test_the_ai_can_open_the_flow_already_filled_in(): void
    {
        $user = User::factory()->create(['balance' => 100]);
        $ctx = new SessionContext(self::PHONE);
        $ctx->set('_user_id', $user->id);
        $ctx->set('_prefill_item', 'ad_setup_training');

        $res = app(FlowEngine::class)->start($ctx, 'account_help');

        $this->assertSame('confirm', $ctx->state);
        $this->assertStringContainsString('20.00', (string) $res->reply);
    }

    public function test_all_three_items_are_offered_with_current_prices(): void
    {
        [$engine, $ctx] = $this->start(balance: 0);

        $res = $engine->resume($ctx); // the item menu
        $titles = collect($res->list['sections'][0]['rows'])->pluck('title')->implode(' | ');

        $this->assertStringContainsString('Advertising setup & training — $20.00', $titles);
        $this->assertStringContainsString('Ads account fix — $5.00', $titles);
        $this->assertStringContainsString('Account recovery & unbanning — $10.00', $titles);
    }

    public function test_typed_keyword_starts_the_flow(): void
    {
        config(['services.whatsapp.api_token' => 't', 'services.whatsapp.phone_number_id' => '1']);
        $user = User::factory()->create(['balance' => 0]);
        \App\Models\WhatsAppAccount::create([
            'wa_phone' => self::PHONE, 'user_id' => $user->id,
            'link_status' => 'linked', 'opted_in' => true,
        ]);
        // AI unavailable — the deterministic keyword must still work.
        $mock = \Mockery::mock(\App\Services\AI\GeminiClient::class);
        $mock->shouldReceive('isConfigured')->andReturn(false);
        $this->app->instance(\App\Services\AI\GeminiClient::class, $mock);

        app(\App\WhatsApp\Routing\MessageRouter::class)->handle([
            'from' => self::PHONE, 'wa_message_id' => 'wamid.'.uniqid('', true), 'type' => 'text',
            'text' => 'accounthelp', 'interactive_id' => null, 'media' => null,
            'name' => 'Tester', 'timestamp' => time(), 'raw' => [],
        ]);

        $out = \App\Models\WhatsAppMessage::where('direction', 'out')->latest('id')->first();
        $this->assertStringContainsString('Account & advertising support', (string) $out->body);
    }
}
