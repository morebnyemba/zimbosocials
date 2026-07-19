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
                return;
            }

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
        }

        // Fallback: plain text message
        $message = "🔔 *{$this->title}*\n\n{$this->body}\n\n— Zimbo Socials";
        $whatsapp->sendMessage($this->to, $message);
    }
}
