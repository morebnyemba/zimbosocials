<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppSavedOrder;
use App\Models\WhatsAppSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppDropoffRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Responder → WhatsAppService may reach the Graph API; keep it off the wire.
        config(['services.whatsapp.api_token' => 't', 'services.whatsapp.phone_number_id' => '1']);
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.x']]])]);
    }

    private function account(string $phone = '263771234567', bool $opted = true, ?string $handoffUntil = null): WhatsAppAccount
    {
        return WhatsAppAccount::create([
            'wa_phone' => $phone, 'display_name' => 'Tino Moyo', 'link_status' => 'linked',
            'opted_in' => $opted, 'agent_handoff_until' => $handoffUntil,
        ]);
    }

    private function out(string $phone): ?WhatsAppMessage
    {
        return WhatsAppMessage::where('wa_phone', $phone)->where('direction', 'out')->latest('id')->first();
    }

    public function test_a_saved_order_is_reminded_once_to_top_up(): void
    {
        $this->account();
        $service = Service::create([
            'name' => 'Facebook Followers', 'name_sn' => 'x', 'description' => '', 'description_sn' => '',
            'category' => 'Facebook', 'type' => 'followers', 'rate' => 5.0,
            'min_qty' => 100, 'max_qty' => 100000, 'is_active' => true,
        ]);
        $user = User::factory()->create(['balance' => 0]);
        $saved = WhatsAppSavedOrder::create([
            'user_id' => $user->id, 'wa_phone' => '263771234567', 'service_id' => $service->id,
            'link' => 'https://facebook.com/x', 'quantity' => 1000,
        ]);
        // Age it past the min so the reminder is due.
        $saved->forceFill(['created_at' => now()->subHours(3)])->save();

        $this->artisan('whatsapp:remind-saved-orders')->assertSuccessful();

        $body = (string) $this->out('263771234567')?->body;
        $this->assertStringContainsString('saved', $body);
        $this->assertStringContainsString('Facebook Followers', $body);
        $this->assertNotNull($saved->fresh()->reminded_at);

        // Not reminded twice.
        WhatsAppMessage::query()->delete();
        $this->artisan('whatsapp:remind-saved-orders')->assertSuccessful();
        $this->assertNull($this->out('263771234567'));
    }

    public function test_a_saved_order_the_user_can_now_afford_is_cleared_not_reminded(): void
    {
        $this->account();
        $service = Service::create([
            'name' => 'Facebook Followers', 'name_sn' => 'x', 'description' => '', 'description_sn' => '',
            'category' => 'Facebook', 'type' => 'followers', 'rate' => 5.0,
            'min_qty' => 100, 'max_qty' => 100000, 'is_active' => true,
        ]);
        $user = User::factory()->create(['balance' => 100]); // can afford now
        $saved = WhatsAppSavedOrder::create([
            'user_id' => $user->id, 'wa_phone' => '263771234567', 'service_id' => $service->id,
            'link' => 'https://facebook.com/x', 'quantity' => 1000,
        ]);
        $saved->forceFill(['created_at' => now()->subHours(3)])->save();

        $this->artisan('whatsapp:remind-saved-orders')->assertSuccessful();

        $this->assertNull($this->out('263771234567'));
        $this->assertDatabaseMissing('whatsapp_saved_orders', ['user_id' => $user->id]);
    }
}
