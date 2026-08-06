<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Web counterpart to AccountHelpFlowTest: same money-safe booking, reached
 * from the website instead of WhatsApp.
 */
class AccountHelpWebBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.x']]])]);
    }

    public function test_the_page_lists_items_with_prices(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('account-help.index'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('AccountHelp/Index')
                ->has('items', 3)
                ->where('items.0.key', 'ad_setup_training')
                ->where('items.0.price', fn ($v) => (float) $v === 20.0)
            );
    }

    public function test_booking_an_item_charges_the_wallet_and_creates_it(): void
    {
        $user = User::factory()->create(['balance' => 50]);

        $this->actingAs($user)
            ->post(route('account-help.store'), ['item' => 'account_recovery'])
            ->assertRedirect(route('account-help.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('extra_service_bookings', [
            'user_id' => $user->id,
            'item' => 'account_recovery',
            'total' => 10.00,
            'status' => 'pending_setup',
        ]);
        $this->assertSame(40.0, (float) $user->fresh()->balance);
        $this->assertDatabaseHas('transactions', ['user_id' => $user->id, 'type' => 'order_charge', 'amount' => -10.0]);
    }

    public function test_insufficient_balance_blocks_the_booking(): void
    {
        $user = User::factory()->create(['balance' => 1]);

        $this->actingAs($user)
            ->post(route('account-help.store'), ['item' => 'ad_setup_training'])
            ->assertSessionHasErrors(['balance']);

        $this->assertDatabaseCount('extra_service_bookings', 0);
        $this->assertSame(1.0, (float) $user->fresh()->balance);
    }

    public function test_an_unknown_item_is_rejected(): void
    {
        $user = User::factory()->create(['balance' => 100]);

        $this->actingAs($user)
            ->post(route('account-help.store'), ['item' => 'not-a-real-item'])
            ->assertSessionHasErrors(['item']);

        $this->assertDatabaseCount('extra_service_bookings', 0);
    }
}
