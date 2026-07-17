<?php

namespace App\Jobs;

use App\Models\MarketingCampaign;
use App\Models\Notification;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\WhatsApp\Auth\WhatsAppRegistrar;
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

            $whatsappOn = in_array('whatsapp', $channels, true);
            // A campaign that targets specific roles/account-types is a user-
            // segment blast — guest WhatsApp contacts (no user, no role) are only
            // swept in when the campaign is unfiltered ("all").
            $unfiltered = in_array('all', $roles, true) && in_array('all', $accountTypes, true);

            $sentEmail = 0;
            $sentWhatsApp = 0;
            $sentInApp = 0;

            // Phone keys already messaged, so a user reachable via both their
            // profile number and a whatsapp_accounts row isn't texted twice.
            $sentPhones = [];
            // Linked users we did reach, so filtered campaigns can still cover a
            // matching user whose WA number lives only in whatsapp_accounts.
            $matchedUserIds = [];

            foreach ($users as $user) {
                $matchedUserIds[(int) $user->id] = true;
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

                // Skip synthetic auto-registration mailboxes ({phone}@auto-domain,
                // e.g. @zimbosocials.co.zw) — they don't exist and only bounce.
                if (in_array('email', $channels, true) && ($prefs['email'] ?? true)
                    && ! empty($user->email) && ! WhatsAppRegistrar::isAutoEmail($user->email)) {
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

                if ($whatsappOn && ($prefs['whatsapp'] ?? true) && ! empty($user->whatsapp_number)) {
                    SendWhatsAppNotification::dispatch(
                        (string) $user->whatsapp_number,
                        'marketing_broadcast',
                        (string) $subject,
                        (string) $body,
                        [(string) $user->name, (string) $subject, (string) $body],
                        $locale,
                    )->onQueue('notifications');
                    $sentWhatsApp++;
                    $sentPhones[$this->phoneKey((string) $user->whatsapp_number)] = true;
                }
            }

            // Reach every WhatsApp contact in the system — including numbers that
            // only ever messaged the bot and never created/linked a web account.
            $extraWhatsApp = 0;
            if ($whatsappOn) {
                $defaultSubject = $subjects['en'] ?? 'Campaign update';
                $defaultBody = $bodies['en'] ?? '';

                WhatsAppAccount::query()
                    ->where('opted_in', true)
                    ->whereNotNull('wa_phone')
                    ->with('user:id,name,locale,notification_prefs')
                    ->chunkById(500, function ($accounts) use (
                        &$sentWhatsApp, &$extraWhatsApp, &$sentPhones, $matchedUserIds,
                        $unfiltered, $subjects, $bodies, $defaultSubject, $defaultBody, $campaign
                    ) {
                        foreach ($accounts as $account) {
                            $key = $this->phoneKey((string) $account->wa_phone);
                            if ($key === '' || isset($sentPhones[$key])) {
                                continue; // already messaged via a user profile
                            }

                            $linked = $account->user;

                            // Filtered campaign: only linked users who matched the
                            // segment (guests have no role/type to match).
                            if (! $unfiltered && (! $linked || ! isset($matchedUserIds[(int) $linked->id]))) {
                                continue;
                            }

                            // Respect an explicit WhatsApp opt-out on the linked user.
                            if ($linked && ($linked->notification_prefs['whatsapp'] ?? true) === false) {
                                continue;
                            }

                            $locale = $linked && in_array($linked->locale, ['sn', 'nd', 'en'], true) ? $linked->locale : 'en';
                            $subject = $subjects[$locale] ?? $defaultSubject;
                            $body = $bodies[$locale] ?? $defaultBody;
                            $name = $linked->name ?? $account->display_name ?? 'there';

                            SendWhatsAppNotification::dispatch(
                                (string) $account->wa_phone,
                                'marketing_broadcast',
                                (string) $subject,
                                (string) $body,
                                [(string) $name, (string) $subject, (string) $body],
                                $locale,
                            )->onQueue('notifications');

                            $sentPhones[$key] = true;
                            $sentWhatsApp++;
                            $extraWhatsApp++;
                        }
                    });
            }

            $campaign->update([
                'status' => 'completed',
                'recipients_total' => $users->count() + $extraWhatsApp,
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

    /**
     * A dedupe key for a phone number that survives local vs international
     * formatting: the last 9 digits (the ZW national significant number), so
     * "0771234567" and "263771234567" collapse to the same person.
     */
    private function phoneKey(string $phone): string
    {
        return substr(preg_replace('/\D+/', '', $phone) ?? '', -9);
    }
}
