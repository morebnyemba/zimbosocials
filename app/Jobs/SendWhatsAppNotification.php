<?php

namespace App\Jobs;

use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
        $language = in_array($this->locale, ['en', 'sn', 'nd'], true)
            ? $this->locale
            : config('whatsapp-templates.language', 'en');

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

            // If template isn't approved yet, fall back to plain text
            // (Meta returns error 132001 for unapproved templates)
        }

        // Fallback: plain text message
        $message = "🔔 *{$this->title}*\n\n{$this->body}\n\n— Zimbo Socials";
        $whatsapp->sendMessage($this->to, $message);
    }
}
