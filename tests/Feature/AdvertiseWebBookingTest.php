<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Web counterpart to AdvertiseFlowTest: same money-safe booking, reached from
 * the website instead of WhatsApp.
 */
class AdvertiseWebBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.x']]])]);
    }

    public function test_the_page_lists_packages_with_prices(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('advertise.index'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Advertise/Index')
                ->has('packages', 5)
                ->where('packages.2.key', 'week1')
                ->where('packages.2.price', fn ($v) => (float) $v === 25.0)
            );
    }

    public function test_booking_a_package_charges_the_wallet_and_creates_it(): void
    {
        $user = User::factory()->create(['balance' => 100]);

        $this->actingAs($user)
            ->post(route('advertise.store'), ['package' => 'week1'])
            ->assertRedirect(route('advertise.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('advert_bookings', [
            'user_id' => $user->id,
            'package' => 'week1',
            'days' => 7,
            'total' => 25.00,
            'status' => 'pending_setup',
        ]);
        $this->assertSame(75.0, (float) $user->fresh()->balance);
        $this->assertDatabaseHas('transactions', ['user_id' => $user->id, 'type' => 'order_charge', 'amount' => -25.0]);
    }

    public function test_insufficient_balance_blocks_the_booking(): void
    {
        $user = User::factory()->create(['balance' => 5]);

        $this->actingAs($user)
            ->post(route('advertise.store'), ['package' => 'week1'])
            ->assertSessionHasErrors(['balance']);

        $this->assertDatabaseCount('advert_bookings', 0);
        $this->assertSame(5.0, (float) $user->fresh()->balance);
    }

    public function test_an_unknown_package_is_rejected(): void
    {
        $user = User::factory()->create(['balance' => 100]);

        $this->actingAs($user)
            ->post(route('advertise.store'), ['package' => 'not-a-real-package'])
            ->assertSessionHasErrors(['package']);

        $this->assertDatabaseCount('advert_bookings', 0);
    }

    public function test_an_existing_customer_is_grandfathered_into_the_old_price(): void
    {
        config(['adverts.repriced_at' => now()->subDay()->toDateTimeString(), 'adverts.reprice_grace_days' => 7]);

        $user = User::factory()->create(['balance' => 100]);
        $user->forceFill(['created_at' => now()->subDays(3)])->save();

        $this->actingAs($user)
            ->post(route('advertise.store'), ['package' => 'week1'])
            ->assertSessionHas('success');

        // Grandfathered to the previous $20 price, not the current $25.
        $this->assertDatabaseHas('advert_bookings', ['user_id' => $user->id, 'total' => 20.00]);
        $this->assertSame(80.0, (float) $user->fresh()->balance);
    }
}
