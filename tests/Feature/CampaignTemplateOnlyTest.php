<?php

namespace Tests\Feature;

use App\Jobs\SendMarketingBroadcastJob;
use App\Jobs\SendWhatsAppNotification;
use App\Models\MarketingCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * A campaign has to reach contacts OUTSIDE the 24-hour service window, and Meta
 * only allows that via an approved template. A free-form fallback would be
 * undeliverable for most of the audience while still looking like a success —
 * so campaign sends are template-only, and a missing template aborts the run.
 */
class CampaignTemplateOnlyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.whatsapp.api_token' => 't', 'services.whatsapp.phone_number_id' => '1']);
    }

    private function campaign(array $filters = []): MarketingCampaign
    {
        return MarketingCampaign::create([
            'created_by' => User::factory()->create(['role' => 'admin', 'is_active' => false])->id,
            'name' => 'Blast',
            'subjects' => ['en' => 'Big sale'],
            'bodies' => ['en' => 'Grow today'],
            'channels' => ['whatsapp'],
            'filters' => array_merge(['roles' => ['all'], 'account_types' => ['all']], $filters),
            'status' => 'draft',
        ]);
    }

    public function test_campaign_sends_are_marked_template_only(): void
    {
        Queue::fake();
        User::factory()->create(['whatsapp_number' => '263772222222', 'is_active' => true]);

        (new SendMarketingBroadcastJob($this->campaign()->id))->handle();

        Queue::assertPushed(SendWhatsAppNotification::class,
            fn (SendWhatsAppNotification $job) => $job->requireTemplate === true);
    }

    public function test_a_campaign_can_never_be_sent_twice(): void
    {
        Queue::fake();
        User::factory()->create(['whatsapp_number' => '263772222222', 'is_active' => true]);
        $campaign = $this->campaign();

        // First run sends; a second run (retry, double dispatch, double-clicked
        // form) must find the campaign already claimed and send nothing.
        (new SendMarketingBroadcastJob($campaign->id))->handle();
        Queue::assertPushed(SendWhatsAppNotification::class, 1);

        (new SendMarketingBroadcastJob($campaign->id))->handle();

        Queue::assertPushed(SendWhatsAppNotification::class, 1);
    }

    public function test_a_missing_template_aborts_the_campaign_instead_of_free_forming(): void
    {
        Queue::fake();
        User::factory()->create(['whatsapp_number' => '263772222222', 'is_active' => true]);

        $campaign = $this->campaign(['whatsapp_template' => 'does_not_exist']);

        try {
            (new SendMarketingBroadcastJob($campaign->id))->handle();
        } catch (\Throwable $e) {
            // The job rethrows after recording the failure.
        }

        $campaign->refresh();
        $this->assertSame('failed', $campaign->status);
        $this->assertStringContainsString('not available', (string) $campaign->error_message);
        // Crucially: nobody was messaged free-form.
        Queue::assertNotPushed(SendWhatsAppNotification::class);
    }

    public function test_a_template_only_send_does_not_fall_back_to_free_form(): void
    {
        $calls = [];
        Http::fake(function ($request) use (&$calls) {
            $calls[] = data_get($request->data(), 'type');

            // Simulate Meta rejecting the template (e.g. not approved).
            return Http::response(['error' => ['message' => 'template does not exist']], 400);
        });

        (new SendWhatsAppNotification('263771234567', 'marketing_broadcast', 'S', 'B', ['A', 'S', 'B'], 'en', requireTemplate: true))
            ->handle(app(\App\Services\WhatsAppService::class));

        $this->assertSame(['template'], $calls, 'a failed template must NOT be followed by a free-form send');
    }

    public function test_ordinary_notifications_still_fall_back_to_free_form(): void
    {
        $calls = [];
        Http::fake(function ($request) use (&$calls) {
            $type = data_get($request->data(), 'type');
            $calls[] = $type;

            return $type === 'template'
                ? Http::response(['error' => ['message' => 'nope']], 400)
                : Http::response(['messages' => [['id' => 'wamid.x']]]);
        });

        // No requireTemplate → in-window utility messages keep the fallback.
        (new SendWhatsAppNotification('263771234567', 'marketing_broadcast', 'S', 'B', ['A', 'S', 'B'], 'en'))
            ->handle(app(\App\Services\WhatsAppService::class));

        $this->assertContains('template', $calls);
        $this->assertGreaterThan(1, count($calls), 'a normal notification should still try plain text');
    }
}
