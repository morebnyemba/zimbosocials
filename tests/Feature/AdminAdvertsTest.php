<?php

namespace Tests\Feature;

use App\Models\AdvertBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminAdvertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Status changes message the customer on WhatsApp — keep it off the wire.
        config(['services.whatsapp.api_token' => 't', 'services.whatsapp.phone_number_id' => '1']);
        // Media uploads must be stubbed BEFORE the catch-all — the first
        // matching stub wins, so a leading '*' would swallow the /media call.
        Http::fake([
            '*/media' => Http::response(['id' => 'media-99']),
            '*' => Http::response(['messages' => [['id' => 'wamid.x']]]),
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function booking(User $customer, string $status = 'pending_setup'): AdvertBooking
    {
        return AdvertBooking::create([
            'user_id' => $customer->id, 'wa_phone' => '263771234567',
            'package' => 'week2', 'days' => 14, 'total' => 35.00,
            'promoting' => 'my salon', 'target_link' => 'https://facebook.com/salon',
            'target_audience' => 'Ruwa, Eastview', 'status' => $status,
        ]);
    }

    public function test_admin_sees_bookings_with_stats(): void
    {
        $this->booking(User::factory()->create());

        $this->actingAs($this->admin())
            ->get(route('admin.adverts.index'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Admin/Adverts/Index')
                ->has('bookings.data', 1)
                ->where('stats.pending_setup', 1)
                ->where('stats.revenue', fn ($v) => (float) $v === 35.0)
            );
    }

    public function test_marking_live_updates_status(): void
    {
        $booking = $this->booking(User::factory()->create());

        $this->actingAs($this->admin())
            ->post(route('admin.adverts.status', $booking->id), ['status' => 'active'])
            ->assertRedirect();

        $this->assertSame('active', $booking->fresh()->status);
    }

    public function test_refund_returns_the_money_and_cancels(): void
    {
        $customer = User::factory()->create(['balance' => 0]);
        $booking = $this->booking($customer);

        $this->actingAs($this->admin())
            ->post(route('admin.adverts.refund', $booking->id))
            ->assertRedirect();

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame(35.0, (float) $customer->fresh()->balance);
        $this->assertDatabaseHas('transactions', ['user_id' => $customer->id, 'type' => 'refund', 'amount' => 35.0]);
    }

    public function test_a_booking_is_not_refunded_twice(): void
    {
        $customer = User::factory()->create(['balance' => 0]);
        $booking = $this->booking($customer, status: 'cancelled');

        $this->actingAs($this->admin())->post(route('admin.adverts.refund', $booking->id));

        $this->assertSame(0.0, (float) $customer->fresh()->balance);
    }

    /** Open the 24h service window for the booking's number. */
    private function openWindow(string $phone = '263771234567'): void
    {
        \App\Models\WhatsAppAccount::create([
            'wa_phone' => $phone, 'link_status' => 'guest', 'opted_in' => true,
        ])->forceFill(['last_seen_at' => now()->subHour()])->save();
    }

    /**
     * A fake upload that actually carries bytes — fake()->create() only REPORTS
     * a size, it writes an empty file, and an empty part is not uploadable.
     */
    private function videoFile(): \Illuminate\Http\UploadedFile
    {
        $file = \Illuminate\Http\UploadedFile::fake()->create('advert.mp4', 200, 'video/mp4');
        file_put_contents($file->getRealPath(), 'FAKE-MP4-BYTES');

        return $file;
    }

    public function test_admin_sends_the_finished_advert_video_to_the_customer(): void
    {
        $this->openWindow();
        $booking = $this->booking(User::factory()->create());

        $this->actingAs($this->admin())
            ->post(route('admin.adverts.media', $booking->id), [
                'file' => $this->videoFile(),
                'caption' => 'Here is your advert! 🎬',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $out = \App\Models\WhatsAppMessage::where('wa_phone', '263771234567')->latest('id')->first();
        $this->assertSame('video', $out->msg_type);
        $this->assertSame('Here is your advert! 🎬', $out->body);
        $this->assertSame('advert_media', $out->intent);
    }

    public function test_sending_media_is_refused_when_the_reply_window_has_closed(): void
    {
        // Known contact, but last heard from days ago — free-form can't land.
        \App\Models\WhatsAppAccount::create([
            'wa_phone' => '263771234567', 'link_status' => 'guest', 'opted_in' => true,
        ])->forceFill(['last_seen_at' => now()->subDays(3)])->save();

        $booking = $this->booking(User::factory()->create());

        $this->actingAs($this->admin())
            ->post(route('admin.adverts.media', $booking->id), [
                'file' => $this->videoFile(),
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        // Nothing was recorded as sent — the admin is told to ask for a reply.
        $this->assertDatabaseCount('whatsapp_messages', 0);
    }

    public function test_an_unsupported_file_type_is_rejected(): void
    {
        $this->openWindow();
        $booking = $this->booking(User::factory()->create());

        $this->actingAs($this->admin())
            ->post(route('admin.adverts.media', $booking->id), [
                'file' => \Illuminate\Http\UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload'),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_non_admins_are_blocked(): void
    {
        $booking = $this->booking(User::factory()->create());

        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->get(route('admin.adverts.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->post(route('admin.adverts.refund', $booking->id))
            ->assertForbidden();
    }
}
