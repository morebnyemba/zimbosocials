<?php

namespace Tests\Feature;

use App\Console\Commands\FlagIdleLeads;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\Services\AI\GeminiClient;
use App\WhatsApp\AdminNudge;
use App\WhatsApp\Routing\MessageRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * Telling the team about a conversation that needs a person, without silencing
 * the assistant. Every alert carries a wa.me link, because the useful action is
 * always "open a chat with this customer" and retyping a phone number off a
 * screen is what stops anyone doing it.
 */
class AdminNudgeTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '263771234567';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.whatsapp.api_token' => 't', 'services.whatsapp.phone_number_id' => '1', 'services.gemini.api_key' => 'k']);
        Http::fake(fn () => Http::response(['messages' => [['id' => 'wamid.'.uniqid('', true)]]]));
        User::factory()->create(['role' => 'admin', 'is_active' => true, 'whatsapp_number' => '263770000001']);
    }

    private function lastAlert(): ?Notification
    {
        return Notification::whereIn('type', ['admin_whatsapp_nudge', 'admin_whatsapp_handoff'])->latest('id')->first();
    }

    public function test_an_alert_carries_a_tappable_wa_me_link(): void
    {
        AdminNudge::raise(self::PHONE, 'confused about delivery time');

        $alert = $this->lastAlert();
        $this->assertNotNull($alert);
        $this->assertStringContainsString('https://wa.me/263771234567', $alert->body);
        $this->assertStringContainsString('confused about delivery time', $alert->body);
    }

    public function test_the_same_problem_is_not_reported_over_and_over(): void
    {
        $this->assertTrue(AdminNudge::raise(self::PHONE, 'confused', 'ai'));
        // A customer who stays confused for ten messages must not generate ten
        // alerts, or the team stops reading them.
        $this->assertFalse(AdminNudge::raise(self::PHONE, 'still confused', 'ai'));

        $this->assertSame(1, Notification::where('type', 'admin_whatsapp_nudge')->count());
    }

    public function test_a_different_kind_of_problem_still_gets_through(): void
    {
        AdminNudge::raise(self::PHONE, 'confused', 'ai');
        $this->assertTrue(AdminNudge::raise(self::PHONE, 'went quiet', 'idle-lead'));
    }

    public function test_the_ai_can_flag_a_human_without_silencing_itself(): void
    {
        WhatsAppAccount::create(['wa_phone' => self::PHONE, 'link_status' => 'guest', 'opted_in' => true]);

        $mock = Mockery::mock(GeminiClient::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('generateJson')->andReturn([
            'reply' => "Sorry shaa, let me explain that again 🙏",
            'follow_up' => null,
            'flow' => 'none',
            'flow_data' => ['notify_admin' => 'does not understand how followers arrive'],
        ]);
        $this->app->instance(GeminiClient::class, $mock);

        app(MessageRouter::class)->handle([
            'from' => self::PHONE, 'wa_message_id' => 'wamid.'.uniqid('', true), 'type' => 'text',
            'text' => 'i dont get it', 'interactive_id' => null, 'name' => 'Tendai',
            'timestamp' => time(), 'raw' => [],
        ]);

        // The team is told...
        $alert = $this->lastAlert();
        $this->assertNotNull($alert);
        $this->assertStringContainsString('wa.me/263771234567', $alert->body);

        // ...and the bot carried on talking, unlike a handoff.
        $account = WhatsAppAccount::where('wa_phone', self::PHONE)->first();
        $this->assertFalse($account->inAgentHandoff());
        // Scoped to the customer: the admin alert is itself an outbound row.
        $out = WhatsAppMessage::where('direction', 'out')->where('wa_phone', self::PHONE)->latest('id')->first();
        $this->assertStringContainsString('explain that again', (string) $out->body);
    }

    public function test_a_new_lead_who_goes_quiet_is_flagged(): void
    {
        $account = WhatsAppAccount::create(['wa_phone' => self::PHONE, 'display_name' => 'Tendai', 'link_status' => 'guest', 'opted_in' => true]);
        $account->forceFill(['last_seen_at' => now()->subMinutes(30)])->save();
        WhatsAppMessage::create(['wa_phone' => self::PHONE, 'direction' => 'in', 'msg_type' => 'text', 'body' => 'hi']);

        $this->artisan('whatsapp:flag-idle-leads')->assertSuccessful();

        $alert = $this->lastAlert();
        $this->assertNotNull($alert, 'a dropped lead should be surfaced');
        $this->assertStringContainsString('wa.me/263771234567', $alert->body);
    }

    public function test_a_lead_is_reported_once_and_never_again(): void
    {
        $account = WhatsAppAccount::create(['wa_phone' => self::PHONE, 'link_status' => 'guest', 'opted_in' => true]);
        $account->forceFill(['last_seen_at' => now()->subMinutes(30)])->save();
        WhatsAppMessage::create(['wa_phone' => self::PHONE, 'direction' => 'in', 'msg_type' => 'text', 'body' => 'hi']);

        $this->artisan('whatsapp:flag-idle-leads')->assertSuccessful();
        $this->assertSame(1, Notification::where('type', 'admin_whatsapp_nudge')->count());

        // The scheduler runs this every ten minutes. Re-reporting the same lead
        // each time is what made the alerts unreadable.
        Cache::flush();            // as a deploy's optimize:clear would
        $this->travel(3)->hours(); // and well past any cooldown
        $this->artisan('whatsapp:flag-idle-leads')->assertSuccessful();

        $this->assertSame(1, Notification::where('type', 'admin_whatsapp_nudge')->count());
    }

    public function test_a_contact_we_decide_not_to_alert_on_is_not_reconsidered(): void
    {
        // Long conversation — deliberately not a "dropped lead".
        $account = WhatsAppAccount::create(['wa_phone' => self::PHONE, 'link_status' => 'guest', 'opted_in' => true]);
        $account->forceFill(['last_seen_at' => now()->subMinutes(30)])->save();
        foreach (range(1, 6) as $i) {
            WhatsAppMessage::create(['wa_phone' => self::PHONE, 'direction' => 'in', 'msg_type' => 'text', 'body' => "m{$i}"]);
        }

        $this->artisan('whatsapp:flag-idle-leads')->assertSuccessful();

        // Settled, so it is not re-examined on every run for the next day.
        $this->assertNotNull($account->fresh()->lead_flagged_at);
        $this->assertNull($this->lastAlert());
    }

    public function test_a_backlog_cannot_flood_the_team_in_one_run(): void
    {
        foreach (range(1, 12) as $i) {
            $phone = '26377000'.str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            $account = WhatsAppAccount::create(['wa_phone' => $phone, 'link_status' => 'guest', 'opted_in' => true]);
            $account->forceFill(['last_seen_at' => now()->subMinutes(30 + $i)])->save();
            WhatsAppMessage::create(['wa_phone' => $phone, 'direction' => 'in', 'msg_type' => 'text', 'body' => 'hi']);
        }

        $this->artisan('whatsapp:flag-idle-leads')->assertSuccessful();

        // Capped — past a handful, alerts stop being read at all.
        $this->assertLessThanOrEqual(5, Notification::where('type', 'admin_whatsapp_nudge')->count());
    }

    public function test_someone_still_talking_is_not_flagged(): void
    {
        $account = WhatsAppAccount::create(['wa_phone' => self::PHONE, 'link_status' => 'guest', 'opted_in' => true]);
        $account->forceFill(['last_seen_at' => now()->subMinutes(2)])->save();
        WhatsAppMessage::create(['wa_phone' => self::PHONE, 'direction' => 'in', 'msg_type' => 'text', 'body' => 'hi']);

        $this->artisan('whatsapp:flag-idle-leads')->assertSuccessful();

        $this->assertNull($this->lastAlert());
    }

    public function test_a_customer_who_ordered_is_not_a_dropped_lead(): void
    {
        $user = User::factory()->create();
        $service = Service::create([
            'name' => 'IG Followers', 'name_sn' => 'x', 'description' => '', 'description_sn' => '',
            'category' => 'Instagram', 'type' => 'followers', 'rate' => 1.0,
            'min_qty' => 100, 'max_qty' => 100000, 'is_active' => true,
        ]);
        Order::create([
            'user_id' => $user->id, 'service_id' => $service->id, 'link' => 'https://x/y',
            'quantity' => 100, 'charge' => 1.0, 'rate_at_order' => 1.0, 'status' => 'pending',
        ]);

        $account = WhatsAppAccount::create([
            'wa_phone' => self::PHONE, 'user_id' => $user->id, 'link_status' => 'linked', 'opted_in' => true,
        ]);
        $account->forceFill(['last_seen_at' => now()->subMinutes(30)])->save();
        WhatsAppMessage::create(['wa_phone' => self::PHONE, 'direction' => 'in', 'msg_type' => 'text', 'body' => 'hi']);

        $this->artisan('whatsapp:flag-idle-leads')->assertSuccessful();

        $this->assertNull($this->lastAlert());
    }

    public function test_a_long_conversation_that_paused_is_not_treated_as_a_dropped_lead(): void
    {
        $account = WhatsAppAccount::create(['wa_phone' => self::PHONE, 'link_status' => 'guest', 'opted_in' => true]);
        $account->forceFill(['last_seen_at' => now()->subMinutes(30)])->save();
        foreach (range(1, 6) as $i) {
            WhatsAppMessage::create(['wa_phone' => self::PHONE, 'direction' => 'in', 'msg_type' => 'text', 'body' => "m{$i}"]);
        }

        $this->artisan('whatsapp:flag-idle-leads')->assertSuccessful();

        // Nagging about these would bury the alerts that matter.
        $this->assertNull($this->lastAlert());
    }
}
