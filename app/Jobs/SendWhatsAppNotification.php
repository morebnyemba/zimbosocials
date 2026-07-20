<?php

namespace App\Jobs;

use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30; // seconds between retries

    /**
     * @param  string  $to  E.164 number
     * @param  string  $templateName  Template name (from config/whatsapp-templates.php)
     * @param  string  $title  Notification title (used in fallback text)
     * @param  string  $body  Notification body (used in fallback text)
     * @param  array  $templateParams  Ordered parameter values for the template
     */
    public function __construct(
        public readonly string $to,
        public readonly string $templateName,
        public readonly string $title,
        public readonly string $body,
        public readonly array $templateParams = [],
        public readonly ?string $locale = null,
        /**
         * Marketing / out-of-window sends: deliver ONLY as an approved template.
         * A free-form message cannot reach anyone outside the 24-hour service
         * window, so falling back to one would just manufacture a fake success
         * for most of a campaign. Fail loudly instead.
         */
        public readonly bool $requireTemplate = false,
    ) {}

    public function handle(WhatsAppService $whatsapp): void
    {
        $templates = config('whatsapp-templates.templates', []);

        // MUST match the language the template was REGISTERED under, not the
        // user's locale: Shona/Ndebele templates live on Meta as English (Meta
        // has no sn/nd), so asking for "sn" fails and we'd silently degrade to
        // free-form — undeliverable outside the 24h window. The message text
        // itself stays localised; it rides in the template PARAMETERS.
        $language = \App\Models\WhatsAppTemplate::metaLanguage($this->locale);

        // If template exists in config, try sending as a template message
        if (isset($templates[$this->templateName])) {
            $result = $whatsapp->sendTemplate(
                $this->to,
                $this->templateName,
                $language,
                $this->templateParams,
            );

            // If template send succeeded, we're done
            if ($result['ok']) {
                $this->recordOutbound($result['message_id'] ?? null, null, null);

                return;
            }

            // Record the failure so it is visible per-recipient in the admin
            // console instead of vanishing into the log. Meta code 131049 means
            // the person has hit their daily marketing cap ACROSS ALL
            // businesses — nothing we can change; utility messages still land.
            $this->recordOutbound(null, $result['error'] ?? 'unknown', $result['error_code'] ?? null);

            // Template unusable (not approved, wrong name, params mismatch).
            // The plain-text fallback below ONLY reaches contacts inside the
            // 24-hour service window, so make the reason loud — a broadcast
            // that quietly reaches a fraction of the audience looks like a
            // delivery mystery otherwise.
            Log::warning('WhatsApp template send failed — falling back to free-form text (delivers only inside the 24h window)', [
                'template' => $this->templateName,
                'language' => $language,
                'to' => $this->to,
                'error' => $result['error'] ?? null,
            ]);
        } elseif ($this->requireTemplate) {
            Log::error('WhatsApp template is not available at all — nothing sent', [
                'template' => $this->templateName,
                'to' => $this->to,
                'hint' => 'Template missing from config/whatsapp_templates, or deactivated.',
            ]);
        }

        // Template-only send: do NOT fall back. Outside the 24-hour window a
        // free-form message is undeliverable, so sending one would report
        // success while the customer receives nothing.
        if ($this->requireTemplate) {
            return;
        }

        // Fallback: plain text message
        $message = "🔔 *{$this->title}*\n\n{$this->body}\n\n— Zimbo Socials";
        $res = $whatsapp->sendMessage($this->to, $message);
        $this->recordOutbound($res['message_id'] ?? null, empty($res['ok']) ? ($res['error'] ?? 'send failed') : null, null, $message);
    }

    /**
     * Put every template send in the transcript. Without this a campaign is
     * invisible: no admin can see who was messaged, and with no wa_message_id
     * Meta's delivery receipts (sent/delivered/read/failed) can never be matched
     * back — which is exactly how a broadcast looks like "nothing happened".
     */
    private function recordOutbound(?string $messageId, ?string $error, ?int $errorCode, ?string $body = null): void
    {
        try {
            \App\Models\WhatsAppMessage::create([
                'wa_phone' => $this->to,
                'direction' => 'out',
                'wa_message_id' => $messageId,
                'msg_type' => 'template',
                'body' => $body ?? "*{$this->title}*\n\n{$this->body}",
                'handled_by' => 'system',
                'intent' => $this->templateName,
                // Meta never reports a receipt for a send it rejected, so mark
                // it failed here; successful ones are updated by the webhook.
                'delivery_status' => $error !== null ? 'failed' : null,
                'payload' => $error !== null
                    ? array_filter(['error' => $error, 'error_code' => $errorCode, 'template' => $this->templateName])
                    : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Could not record outbound template message', ['message' => $e->getMessage()]);
        }
    }
}
