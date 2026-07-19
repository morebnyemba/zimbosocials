<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Meta reports sent → delivered → read for every message we send. Those
 * receipts were already being stored; these pin that they survive the webhook
 * and reach the admin inbox so an agent can see whether a customer read them.
 */
class WhatsAppDeliveryReceiptsTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '263771234567';

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function account(): WhatsAppAccount
    {
        return WhatsAppAccount::create([
            'wa_phone' => self::PHONE, 'display_name' => 'Tino',
            'link_status' => 'guest', 'opted_in' => true,
        ]);
    }

    private function outbound(string $waId): WhatsAppMessage
    {
        return WhatsAppMessage::create([
            'wa_phone' => self::PHONE, 'direction' => 'out', 'wa_message_id' => $waId,
            'msg_type' => 'text', 'body' => 'Hi there', 'handled_by' => 'system',
        ]);
    }

    private function statusWebhook(string $waId, string $status): array
    {
        return ['entry' => [['changes' => [['value' => [
            'statuses' => [[
                'id' => $waId, 'status' => $status, 'recipient_id' => self::PHONE,
                'timestamp' => (string) time(),
            ]],
        ]]]]]];
    }

    public function test_status_callbacks_move_a_message_through_to_read(): void
    {
        $msg = $this->outbound('wamid.abc');

        foreach (['sent', 'delivered', 'read'] as $status) {
            $this->postJson('/webhooks/whatsapp', $this->statusWebhook('wamid.abc', $status))
                ->assertSuccessful();
            $this->assertSame($status, $msg->fresh()->delivery_status);
        }
    }

    public function test_a_failed_send_is_recorded(): void
    {
        $msg = $this->outbound('wamid.fail');

        $this->postJson('/webhooks/whatsapp', $this->statusWebhook('wamid.fail', 'failed'));

        $this->assertSame('failed', $msg->fresh()->delivery_status);
    }

    public function test_the_transcript_exposes_the_receipt_to_the_admin(): void
    {
        $account = $this->account();
        $this->outbound('wamid.abc')->update(['delivery_status' => 'read']);

        $this->actingAs($this->admin())
            ->get(route('admin.whatsapp.conversation', $account->id))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Admin/WhatsApp/Conversation')
                ->where('messages.0.delivery_status', 'read')
            );
    }

    public function test_the_conversation_list_shows_the_last_receipt(): void
    {
        $this->account();
        $this->outbound('wamid.abc')->update(['delivery_status' => 'delivered']);

        $this->actingAs($this->admin())
            ->get(route('admin.whatsapp.conversations'))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('conversations.data.0.last_delivery_status', 'delivered')
            );
    }
}
