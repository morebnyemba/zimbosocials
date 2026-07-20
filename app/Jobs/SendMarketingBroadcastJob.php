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
            // LINKED WhatsApp accounts follow the campaign's role/type segment;
            // an unfiltered campaign reaches all of them.
            $unfiltered = in_array('all', $roles, true) && in_array('all', $accountTypes, true);
            // GUEST contacts (a number that messaged the bot but never made an
            // account) have no role/type to match, so they're governed by their
            // own switch — default ON so campaigns reach every WhatsApp contact,
            // but an admin can turn it off to keep a targeted campaign tight.
            $includeGuests = (bool) ($filters['include_guests'] ?? true);

            // Which WhatsApp template to send with. Marketing sends outside the
            // 24h window MUST use a Meta-approved template — an admin can point
            // the campaign at an already-approved one instead of marketing_broadcast.
            $waTemplate = $filters['whatsapp_template'] ?? 'marketing_broadcast';
            $waTemplateDef = config("whatsapp-templates.templates.{$waTemplate}");

            // Pre-flight: a campaign MUST go out as an approved template so it
            // reaches contacts outside the 24-hour window. If the template isn't
            // even available, abort the whole run rather than quietly blasting
            // free-form messages that most recipients can never receive.
            if ($whatsappOn && ! is_array($waTemplateDef)) {
                throw new \RuntimeException(
                    "WhatsApp template '{$waTemplate}' is not available (missing or deactivated in Admin → WhatsApp → Templates). "
                    .'Campaign aborted — without it, messages could not reach contacts outside the 24-hour window.'
                );
            }

            $waParamLabels = $waTemplateDef['params'] ?? ['user_name', 'subject', 'body'];

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
                        $waTemplate,
                        (string) $subject,
                        (string) $body,
                        $this->templateParams($waParamLabels, (string) $user->name, (string) $subject, (string) $body),
                        $locale,
                        requireTemplate: true,
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
                        $unfiltered, $includeGuests, $subjects, $bodies, $defaultSubject, $defaultBody, $campaign,
                        $waTemplate, $waParamLabels
                    ) {
                        foreach ($accounts as $account) {
                            $key = $this->phoneKey((string) $account->wa_phone);
                            if ($key === '' || isset($sentPhones[$key])) {
                                continue; // already messaged via a user profile
                            }

                            $linked = $account->user;

                            if ($linked) {
                                // Linked account → honour the role/type segment
                                // and the user's own WhatsApp opt-out.
                                if (! $unfiltered && ! isset($matchedUserIds[(int) $linked->id])) {
                                    continue;
                                }
                                if (($linked->notification_prefs['whatsapp'] ?? true) === false) {
                                    continue;
                                }
                            } elseif (! $includeGuests) {
                                // Contact with no account — only when opted into.
                                continue;
                            }

                            $locale = $linked && in_array($linked->locale, ['sn', 'nd', 'en'], true) ? $linked->locale : 'en';
                            $subject = $subjects[$locale] ?? $defaultSubject;
                            $body = $bodies[$locale] ?? $defaultBody;
                            $name = $linked->name ?? $account->display_name ?? 'there';

                            SendWhatsAppNotification::dispatch(
                                (string) $account->wa_phone,
                                $waTemplate,
                                (string) $subject,
                                (string) $body,
                                $this->templateParams($waParamLabels, (string) $name, (string) $subject, (string) $body),
                                $locale,
                                requireTemplate: true,
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

    /**
     * Fill a template's ordered params from the campaign's name/subject/body by
     * matching each param LABEL, so an admin can point a campaign at any approved
     * template whose variables are some arrangement of those three. Unmatched
     * labels fall back to the body text (never empty — Meta rejects blank params).
     *
     * @param  array<int,string>  $labels
     * @return array<int,string>
     */
    private function templateParams(array $labels, string $name, string $subject, string $body): array
    {
        return array_map(function ($label) use ($name, $subject, $body): string {
            $l = mb_strtolower((string) $label);

            return match (true) {
                str_contains($l, 'name') => $name,
                str_contains($l, 'subject') || str_contains($l, 'title') => $subject,
                str_contains($l, 'body') || str_contains($l, 'message') || str_contains($l, 'content') => $body,
                default => $body,
            };
        }, array_values($labels));
    }
}
