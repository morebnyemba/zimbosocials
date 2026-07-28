<?php

namespace Tests\Feature;

use App\Models\AdvertBooking;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppSession;
use App\WhatsApp\Persistence\ContactEraser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Erasing a WhatsApp contact removes the identity, the transcript, the flow
 * state and their archived media — but never quietly destroys financial
 * history, which belongs to the app user and to our own books.
 */
class WhatsAppContactErasureTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '263771234567';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function contact(?User $user = null): WhatsAppAccount
    {
        $account = WhatsAppAccount::create([
            'wa_phone' => self::PHONE,
            'user_id' => $user?->id,
            'link_status' => $user ? 'linked' : 'guest',
            'opted_in' => true,
        ]);

        WhatsAppMessage::create(['wa_phone' => self::PHONE, 'direction' => 'in', 'msg_type' => 'text', 'body' => 'hi']);
        WhatsAppMessage::create(['wa_phone' => self::PHONE, 'direction' => 'out', 'msg_type' => 'text', 'body' => 'hello']);
        WhatsAppSession::create(['wa_phone' => self::PHONE, 'flow' => 'order', 'state' => 'enter_link']);
        Storage::disk('public')->put('wa-media/'.self::PHONE.'/a.jpg', 'BYTES');

        return $account;
    }

    public function test_erasing_removes_the_contact_transcript_session_and_media(): void
    {
        $account = $this->contact();

        $res = app(ContactEraser::class)->erase($account);

        $this->assertTrue($res['ok']);
        $this->assertDatabaseCount('whatsapp_accounts', 0);
        $this->assertDatabaseCount('whatsapp_messages', 0);
        $this->assertDatabaseCount('whatsapp_sessions', 0);
        Storage::disk('public')->assertMissing('wa-media/'.self::PHONE.'/a.jpg');
        $this->assertSame(2, $res['deleted']['messages']);
        $this->assertSame(1, $res['deleted']['media_files']);
    }

    public function test_the_linked_user_is_kept_by_default(): void
    {
        $user = User::factory()->create();
        $account = $this->contact($user);

        app(ContactEraser::class)->erase($account);

        // Chat data gone, the person's account untouched.
        $this->assertDatabaseCount('whatsapp_accounts', 0);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_a_customer_with_orders_is_never_deleted(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $service = Service::create([
            'name' => 'IG Followers', 'name_sn' => 'x', 'description' => '', 'description_sn' => '',
            'category' => 'Instagram', 'type' => 'followers', 'rate' => 1.0,
            'min_qty' => 100, 'max_qty' => 100000, 'is_active' => true,
        ]);
        Order::create([
            'user_id' => $user->id, 'service_id' => $service->id, 'link' => 'https://x/y',
            'quantity' => 100, 'charge' => 1.0, 'rate_at_order' => 1.0, 'status' => 'completed',
        ]);
        $account = $this->contact($user);

        $res = app(ContactEraser::class)->erase($account, alsoUser: true);

        // Refused outright — and nothing at all was removed.
        $this->assertFalse($res['ok']);
        $this->assertSame('has_financial_history', $res['reason']);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseCount('whatsapp_accounts', 1);
        $this->assertDatabaseCount('whatsapp_messages', 2);
    }

    public function test_a_user_with_a_balance_is_never_deleted(): void
    {
        $user = User::factory()->create(['balance' => 12.50]);
        $account = $this->contact($user);

        $res = app(ContactEraser::class)->erase($account, alsoUser: true);

        $this->assertFalse($res['ok']);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_a_clean_user_can_be_removed_with_the_contact(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $account = $this->contact($user);

        $res = app(ContactEraser::class)->erase($account, alsoUser: true);

        $this->assertTrue($res['ok']);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_advert_bookings_are_detached_not_destroyed(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $account = $this->contact($user);
        $booking = AdvertBooking::create([
            'user_id' => $user->id, 'wa_phone' => self::PHONE, 'package' => 'day1',
            'days' => 1, 'total' => 5.00, 'status' => 'pending_setup',
        ]);

        app(ContactEraser::class)->erase($account);

        // What was sold survives; the phone number no longer identifies them.
        $this->assertDatabaseHas('advert_bookings', ['id' => $booking->id, 'wa_phone' => null]);
    }

    public function test_the_admin_endpoint_requires_the_number_typed_back(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $account = $this->contact();

        $this->actingAs($admin)
            ->delete(route('admin.whatsapp.conversation.destroy', $account->id), ['confirm_phone' => '263770000000'])
            ->assertSessionHas('error');

        // Typo-protected: nothing removed.
        $this->assertDatabaseCount('whatsapp_accounts', 1);
        $this->assertDatabaseCount('whatsapp_messages', 2);
    }

    public function test_the_admin_endpoint_erases_on_a_matching_number(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $account = $this->contact();

        $this->actingAs($admin)
            ->delete(route('admin.whatsapp.conversation.destroy', $account->id), ['confirm_phone' => '+263 77 123 4567'])
            ->assertSessionHas('success');

        $this->assertDatabaseCount('whatsapp_accounts', 0);
    }

    public function test_non_admins_cannot_erase_a_contact(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $account = $this->contact();

        $this->actingAs($user)
            ->delete(route('admin.whatsapp.conversation.destroy', $account->id), ['confirm_phone' => self::PHONE])
            ->assertForbidden();

        $this->assertDatabaseCount('whatsapp_accounts', 1);
    }
}
