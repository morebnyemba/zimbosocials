<?php

namespace Tests\Feature;

use App\Models\ExtraServiceBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminExtraServicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.whatsapp.api_token' => 't', 'services.whatsapp.phone_number_id' => '1']);
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.x']]])]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function booking(User $customer, string $status = 'pending_setup'): ExtraServiceBooking
    {
        return ExtraServiceBooking::create([
            'user_id' => $customer->id, 'wa_phone' => '263771234567',
            'item' => 'account_recovery', 'total' => 10.00, 'status' => $status,
        ]);
    }

    public function test_admin_sees_bookings_with_stats(): void
    {
        $this->booking(User::factory()->create());

        $this->actingAs($this->admin())
            ->get(route('admin.extra-services.index'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Admin/ExtraServices/Index')
                ->has('bookings.data', 1)
                ->where('stats.pending_setup', 1)
                ->where('stats.revenue', fn ($v) => (float) $v === 10.0)
            );
    }

    public function test_marking_in_progress_updates_status(): void
    {
        $booking = $this->booking(User::factory()->create());

        $this->actingAs($this->admin())
            ->post(route('admin.extra-services.status', $booking->id), ['status' => 'in_progress'])
            ->assertRedirect();

        $this->assertSame('in_progress', $booking->fresh()->status);
    }

    public function test_refund_returns_the_money_and_cancels(): void
    {
        $customer = User::factory()->create(['balance' => 0]);
        $booking = $this->booking($customer);

        $this->actingAs($this->admin())
            ->post(route('admin.extra-services.refund', $booking->id))
            ->assertRedirect();

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame(10.0, (float) $customer->fresh()->balance);
        $this->assertDatabaseHas('transactions', ['user_id' => $customer->id, 'type' => 'refund', 'amount' => 10.0]);
    }

    public function test_a_booking_is_not_refunded_twice(): void
    {
        $customer = User::factory()->create(['balance' => 0]);
        $booking = $this->booking($customer, status: 'cancelled');

        $this->actingAs($this->admin())->post(route('admin.extra-services.refund', $booking->id));

        $this->assertSame(0.0, (float) $customer->fresh()->balance);
    }

    public function test_non_admins_are_blocked(): void
    {
        $booking = $this->booking(User::factory()->create());

        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->get(route('admin.extra-services.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->post(route('admin.extra-services.refund', $booking->id))
            ->assertForbidden();
    }
}
