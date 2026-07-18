<?php

namespace Tests\Feature;

use App\Jobs\SendEmailNotification;
use App\Jobs\SendMarketingBroadcastJob;
use App\Jobs\SendWhatsAppNotification;
use App\Models\MarketingCampaign;
use App\Models\User;
use App\Models\WhatsAppAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MarketingBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private function campaign(array $channels, array $filters = ['roles' => ['all'], 'account_types' => ['all']]): MarketingCampaign
    {
        return $this->makeCampaign($channels, $filters);
    }

    /** Run the broadcast synchronously (Queue::fake also fakes dispatchSync). */
    private function runBroadcast(int $campaignId): void
    {
        (new SendMarketingBroadcastJob($campaignId))->handle();
    }

    private function makeCampaign(array $channels, array $filters): MarketingCampaign
    {
        return MarketingCampaign::create([
            // Inactive so the creator isn't itself a campaign recipient.
            'created_by' => User::factory()->create(['role' => 'admin', 'is_active' => false])->id,
            'name' => 'Test blast',
            'subjects' => ['en' => 'Big sale'],
            'bodies' => ['en' => 'Grow your socials today!'],
            'channels' => $channels,
            'filters' => $filters,
            'status' => 'draft',
        ]);
    }

    public function test_campaign_sends_via_the_chosen_approved_template(): void
    {
        Queue::fake();

        User::factory()->create(['whatsapp_number' => '263772222222', 'is_active' => true, 'name' => 'Jane']);

        // Point the campaign at an existing template other than the default —
        // its params [user_name, ticket_subject] map from name/subject.
        $campaign = $this->makeCampaign(['whatsapp'], [
            'roles' => ['all'], 'account_types' => ['all'], 'whatsapp_template' => 'ticket_reply',
        ]);
        $this->runBroadcast($campaign->id);

        Queue::assertPushed(SendWhatsAppNotification::class, function (SendWhatsAppNotification $job) {
            return $job->templateName === 'ticket_reply'
                && ($job->templateParams[0] ?? null) === 'Jane'
                && ($job->templateParams[1] ?? null) === 'Big sale'; // subject → ticket_subject
        });
    }

    public function test_whatsapp_broadcast_reaches_contacts_without_a_user_account(): void
    {
        Queue::fake();

        // A guest contact who only ever messaged the bot — no user account.
        WhatsAppAccount::create([
            'wa_phone' => '263771111111', 'user_id' => null,
            'link_status' => 'guest', 'opted_in' => true, 'display_name' => 'Guest',
        ]);
        // A registered user reachable via their profile number.
        User::factory()->create(['whatsapp_number' => '263772222222', 'is_active' => true]);

        $this->runBroadcast($this->campaign(['whatsapp'])->id);

        // Both the user AND the account-only contact get the template.
        Queue::assertPushed(SendWhatsAppNotification::class, 2);
        Queue::assertPushed(SendWhatsAppNotification::class,
            fn (SendWhatsAppNotification $job) => $job->to === '263771111111');
    }

    public function test_a_contact_reachable_two_ways_is_only_texted_once(): void
    {
        Queue::fake();

        $user = User::factory()->create(['whatsapp_number' => '0771234567', 'is_active' => true]);
        // Same person, international format, also has a bot account.
        WhatsAppAccount::create([
            'wa_phone' => '263771234567', 'user_id' => $user->id,
            'link_status' => 'linked', 'opted_in' => true,
        ]);

        $this->runBroadcast($this->campaign(['whatsapp'])->id);

        Queue::assertPushed(SendWhatsAppNotification::class, 1);
    }

    public function test_opted_out_contacts_are_skipped(): void
    {
        Queue::fake();

        WhatsAppAccount::create([
            'wa_phone' => '263773333333', 'user_id' => null,
            'link_status' => 'guest', 'opted_in' => false,
        ]);

        $this->runBroadcast($this->campaign(['whatsapp'])->id);

        Queue::assertNotPushed(SendWhatsAppNotification::class);
    }

    public function test_auto_registration_emails_are_skipped(): void
    {
        Queue::fake();

        // Synthetic mailbox from silent WhatsApp registration.
        User::factory()->create(['email' => '263771234567@zimbosocials.co.zw', 'is_active' => true]);
        // A real customer email.
        User::factory()->create(['email' => 'real@example.com', 'is_active' => true]);

        $this->runBroadcast($this->campaign(['email'])->id);

        // Only the real address gets an email.
        Queue::assertPushed(SendEmailNotification::class, 1);
        Queue::assertPushed(SendEmailNotification::class,
            fn (SendEmailNotification $job) => $job->email === 'real@example.com');
    }

    public function test_filtered_campaign_does_not_sweep_in_guest_contacts(): void
    {
        Queue::fake();

        User::factory()->create(['role' => 'marketer', 'whatsapp_number' => '263772222222', 'is_active' => true]);
        WhatsAppAccount::create([
            'wa_phone' => '263771111111', 'user_id' => null,
            'link_status' => 'guest', 'opted_in' => true,
        ]);

        $this->runBroadcast(
            $this->campaign(['whatsapp'], ['roles' => ['marketer'], 'account_types' => ['all']])->id
        );

        // Only the matching marketer — the guest contact is not swept in.
        Queue::assertPushed(SendWhatsAppNotification::class, 1);
    }
}
