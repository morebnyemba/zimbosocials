<?php

namespace App\Jobs;

use App\Models\MarketingCampaign;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendMarketingBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(public readonly int $campaignId) {}

    public function handle(): void
    {
        $campaign = MarketingCampaign::query()->find($this->campaignId);
        if (! $campaign) {
            return;
        }

        $campaign->update([
            'status' => 'running',
            'started_at' => now(),
            'error_message' => null,
        ]);

        try {
            $channels = $campaign->channels ?? [];
            $filters = $campaign->filters ?? [];
            $subjects = $campaign->subjects ?? [];
            $bodies = $campaign->bodies ?? [];

            $query = User::query()->where('is_active', true);

            $roles = $filters['roles'] ?? ['all'];
            if (! in_array('all', $roles, true)) {
                $query->whereIn('role', $roles);
            }

            $accountTypes = $filters['account_types'] ?? ['all'];
            if (! in_array('all', $accountTypes, true)) {
                $query->whereIn('account_type', $accountTypes);
            }

            $users = $query->get(['id', 'name', 'email', 'locale', 'whatsapp_number', 'notification_prefs']);

            $sentEmail = 0;
            $sentWhatsApp = 0;
            $sentInApp = 0;

            foreach ($users as $user) {
                $locale = in_array($user->locale, ['sn', 'nd', 'en'], true) ? $user->locale : 'en';
                $subject = $subjects[$locale] ?? $subjects['en'] ?? 'Campaign update';
                $body = $bodies[$locale] ?? $bodies['en'] ?? '';

                if (in_array('in_app', $channels, true)) {
                    Notification::send(
                        (int) $user->id,
                        'marketing_broadcast',
                        (string) $subject,
                        (string) $body,
                        ['campaign_id' => $campaign->id]
                    );
                    $sentInApp++;
                }

                $prefs = $user->notification_prefs ?? ['email' => true, 'whatsapp' => true];

                if (in_array('email', $channels, true) && ($prefs['email'] ?? true) && ! empty($user->email)) {
                    SendEmailNotification::dispatch(
                        (string) $user->email,
                        (string) $user->name,
                        (string) $subject,
                        (string) $body,
                        'marketing_broadcast',
                        ['subject' => (string) $subject, 'body' => (string) $body],
                        $locale,
                    )->onQueue('notifications');
                    $sentEmail++;
                }

                if (in_array('whatsapp', $channels, true) && ($prefs['whatsapp'] ?? true) && ! empty($user->whatsapp_number)) {
                    SendWhatsAppNotification::dispatch(
                        (string) $user->whatsapp_number,
                        'marketing_broadcast',
                        (string) $subject,
                        (string) $body,
                        [(string) $user->name, (string) $subject, (string) $body],
                        $locale,
                    )->onQueue('notifications');
                    $sentWhatsApp++;
                }
            }

            $campaign->update([
                'status' => 'completed',
                'recipients_total' => $users->count(),
                'sent_email' => $sentEmail,
                'sent_whatsapp' => $sentWhatsApp,
                'sent_in_app' => $sentInApp,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $campaign->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            throw $e;
        }
    }
}
