<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppNotification;
use App\Models\Order;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Admin operational alerts and WhatsApp: high-volume per-event pings (every
 * sign-up, every order) must NOT go out over WhatsApp — that flood of
 * near-identical utility messages to our own number is what WhatsApp flags as
 * spam. They stay in-app; a once-daily digest rolls them up. Deposit proof is
 * the exception (real money waiting), and it goes template-only with a "reply
 * RECEIVED" prompt so the admin's reply is a genuine engagement signal.
 */
class AdminAlertRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.whatsapp.api_token' => 't',
            'services.whatsapp.phone_number_id' => '1',
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin', 'is_active' => true, 'whatsapp_number' => '263770000001',
        ]);
    }

    public function test_new_registration_alert_does_not_go_out_over_whatsapp(): void
    {
        $admin = $this->admin();
        Queue::fake();

        NotificationService::notifyAdmins(
            'admin_new_registration',
            'New sign-up',
            'Someone signed up.',
            ['user_name' => 'Tino', 'user_email' => 't@x.com', 'role' => 'user'],
        );

        // In-app: yes. WhatsApp: no.
        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'type' => 'admin_new_registration']);
        Queue::assertNotPushed(SendWhatsAppNotification::class);
    }

    public function test_new_order_alert_does_not_go_out_over_whatsapp(): void
    {
        $this->admin();
        Queue::fake();

        NotificationService::notifyAdmins(
            'admin_new_order',
            'New order',
            'An order was placed.',
            ['order_id' => 5, 'user_name' => 'Tino', 'service_name' => 'IG Followers', 'amount' => '$3'],
        );

        Queue::assertNotPushed(SendWhatsAppNotification::class);
    }

    public function test_deposit_proof_alert_goes_out_free_form_not_as_a_template(): void
    {
        $this->admin();
        Queue::fake();

        NotificationService::notifyAdmins(
            'admin_deposit_proof',
            'Deposit proof submitted',
            'Verify it.',
            ['transaction_id' => 42, 'user_name' => 'Tino'],
        );

        Queue::assertPushed(SendWhatsAppNotification::class, fn (SendWhatsAppNotification $job) => $job->freeForm === true          // plain text in the 24h window
            && $job->requireTemplate === false // no template involved at all
            && $job->marketing === false);
    }

    public function test_admin_alerts_need_no_meta_template(): void
    {
        // Nothing to submit for approval — these ride the service window.
        $templates = config('whatsapp-templates.templates', []);
        $this->assertArrayNotHasKey('admin_deposit_proof', $templates);
        $this->assertArrayNotHasKey('admin_daily_digest', $templates);
    }

    public function test_a_free_form_alert_is_sent_when_the_window_is_open(): void
    {
        WhatsAppAccount::create([
            'wa_phone' => '263770000001', 'link_status' => 'guest', 'opted_in' => true,
        ])->forceFill(['last_seen_at' => now()->subHours(2)])->save();

        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.ok']]])]);

        (new SendWhatsAppNotification('263770000001', 'admin_deposit_proof', 'Proof', 'Verify it.', [], 'en', freeForm: true))
            ->handle(app(\App\Services\WhatsAppService::class));

        $out = \App\Models\WhatsAppMessage::where('wa_phone', '263770000001')->latest('id')->first();
        $this->assertSame('text', $out->msg_type);
        $this->assertNull($out->delivery_status, 'an accepted send is not marked failed');
        $this->assertStringContainsString('Verify it.', (string) $out->body);
    }

    public function test_a_closed_window_is_recorded_as_failed_instead_of_sent(): void
    {
        // Last heard from them three days ago — the window has lapsed.
        WhatsAppAccount::create([
            'wa_phone' => '263770000001', 'link_status' => 'guest', 'opted_in' => true,
        ])->forceFill(['last_seen_at' => now()->subDays(3)])->save();

        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.x']]])]);

        (new SendWhatsAppNotification('263770000001', 'admin_deposit_proof', 'Proof', 'Verify it.', [], 'en', freeForm: true))
            ->handle(app(\App\Services\WhatsAppService::class));

        // Nothing was sent to Meta, and the attempt is visible as failed.
        Http::assertNothingSent();
        $out = \App\Models\WhatsAppMessage::where('wa_phone', '263770000001')->latest('id')->first();
        $this->assertSame('failed', $out->delivery_status);
        $this->assertSame(131047, $out->payload['error_code']);
    }

    public function test_the_window_check_tolerates_different_number_formats(): void
    {
        WhatsAppAccount::create([
            'wa_phone' => '263771234567', 'link_status' => 'guest', 'opted_in' => true,
        ])->forceFill(['last_seen_at' => now()->subHour()])->save();

        // Same person, stored three different ways.
        $this->assertTrue(WhatsAppAccount::serviceWindowOpen('263771234567'));
        $this->assertTrue(WhatsAppAccount::serviceWindowOpen('0771234567'));
        $this->assertTrue(WhatsAppAccount::serviceWindowOpen('+263 77 123 4567'));

        $this->assertFalse(WhatsAppAccount::serviceWindowOpen('263779999999'));
    }

    public function test_daily_digest_sends_one_rolled_up_whatsapp_summary(): void
    {
        $admin = $this->admin();

        // Two brand-new sign-ups and one order in the window.
        User::factory()->count(2)->create();
        $svc = Service::create([
            'name' => 'IG Followers', 'name_sn' => 'x', 'description' => '', 'description_sn' => '',
            'category' => 'Instagram', 'type' => 'followers', 'rate' => 1.0,
            'min_qty' => 100, 'max_qty' => 100000, 'is_active' => true,
        ]);
        Order::create([
            'user_id' => $admin->id, 'service_id' => $svc->id, 'link' => 'https://x/y',
            'quantity' => 100, 'charge' => 1.0, 'rate_at_order' => 1.0, 'status' => 'pending',
        ]);

        Queue::fake();
        $this->artisan('whatsapp:admin-digest')->assertSuccessful();

        // Exactly one digest message per admin, template-only.
        Queue::assertPushed(SendWhatsAppNotification::class, fn (SendWhatsAppNotification $job) => $job->templateName === 'admin_daily_digest'
            && $job->to === '263770000001'
            && $job->freeForm === true);
        Queue::assertPushed(SendWhatsAppNotification::class, 1);
    }

    public function test_daily_digest_sends_nothing_on_a_quiet_day(): void
    {
        // Backdate the admin so it isn't itself counted as a fresh sign-up.
        $this->admin()->forceFill(['created_at' => now()->subDays(3)])->save();
        Queue::fake();

        $this->artisan('whatsapp:admin-digest')->assertSuccessful();

        Queue::assertNotPushed(SendWhatsAppNotification::class);
    }
}
