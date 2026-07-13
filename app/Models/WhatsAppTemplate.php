<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Admin-editable WhatsApp message template. Rows override the defaults in
 * config/whatsapp-templates.php at boot (see AppServiceProvider), so the send
 * job, plain-text fallback interpolation, and the Meta sync command all read
 * the edited versions without knowing about this model.
 */
class WhatsAppTemplate extends Model
{
    public const CATEGORIES = ['UTILITY', 'MARKETING', 'AUTHENTICATION'];

    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'name', 'category', 'body', 'params', 'header', 'footer', 'buttons', 'is_active',
    ];

    protected $casts = [
        'params' => 'array',
        'buttons' => 'array',
        'is_active' => 'boolean',
    ];

    /** The shape the rest of the app expects (mirrors the config file entries). */
    public function toConfigShape(): array
    {
        return [
            'category' => $this->category,
            'body' => $this->body,
            'params' => $this->params ?? [],
            'header' => $this->header,
            'footer' => $this->footer,
            'buttons' => $this->buttons ?? [],
        ];
    }

    /**
     * Build the Meta "create template" API payload for a config-shaped entry.
     * Shared by the sync command and the admin per-template push action.
     */
    public static function metaPayload(string $name, array $tpl, string $language): array
    {
        // Meta only accepts its supported language codes — Shona/Ndebele (this
        // app's locales) aren't among them and cause "Invalid parameter" on
        // every submission. Register under English; sends still work.
        if (! preg_match('/^(en(_(US|GB))?|af|zu|pt_(BR|PT)|fr|es(_(AR|ES|MX))?|sw)$/', $language)) {
            $language = 'en';
        }

        $components = [];

        if (! empty($tpl['header'])) {
            $header = ['type' => 'HEADER', 'format' => 'TEXT', 'text' => $tpl['header']];
            // Meta requires sample text for any variable in the header.
            if (preg_match('/\{\{1\}\}/', (string) $tpl['header'])) {
                $header['example'] = ['header_text' => [self::sampleFor(($tpl['params'] ?? [])[0] ?? '', 1)]];
            }
            $components[] = $header;
        }

        $body = ['type' => 'BODY', 'text' => $tpl['body']];

        // Meta REJECTS templates whose body has {{n}} variables but no sample
        // values ("Template variables without sample text") — provide one
        // sample per placeholder, derived from the param labels.
        preg_match_all('/\{\{(\d+)\}\}/', (string) $tpl['body'], $m);
        $placeholderCount = $m[1] === [] ? 0 : max(array_map('intval', $m[1]));
        if ($placeholderCount > 0) {
            $labels = array_values($tpl['params'] ?? []);
            $samples = [];
            for ($i = 0; $i < $placeholderCount; $i++) {
                $samples[] = self::sampleFor($labels[$i] ?? '', $i + 1);
            }
            $body['example'] = ['body_text' => [$samples]];
        }

        $components[] = $body;

        if (! empty($tpl['footer'])) {
            $components[] = ['type' => 'FOOTER', 'text' => $tpl['footer']];
        }

        if (! empty($tpl['buttons'])) {
            $buttons = [];
            foreach ($tpl['buttons'] as $btn) {
                $buttons[] = match ($btn['type'] ?? 'QUICK_REPLY') {
                    'URL' => ['type' => 'URL', 'text' => $btn['text'], 'url' => $btn['url']],
                    'PHONE_NUMBER' => ['type' => 'PHONE_NUMBER', 'text' => $btn['text'], 'phone_number' => $btn['phone']],
                    default => ['type' => 'QUICK_REPLY', 'text' => $btn['text']],
                };
            }
            $components[] = ['type' => 'BUTTONS', 'buttons' => $buttons];
        }

        return [
            'name' => $name,
            'language' => $language,
            'category' => $tpl['category'] ?? 'UTILITY',
            'components' => $components,
        ];
    }

    /** A realistic sample value for a param label — shown to Meta's reviewers. */
    private static function sampleFor(string $label, int $position): string
    {
        $label = mb_strtolower($label);

        return match (true) {
            str_contains($label, 'email') => 'tendai@example.com',
            str_contains($label, 'name') => 'Tendai Moyo',
            str_contains($label, 'amount') || str_contains($label, 'balance') || str_contains($label, 'total') || str_contains($label, 'charge') || str_contains($label, 'refund') => '10.00 USD',
            str_contains($label, 'date') || str_contains($label, 'time') => '13 Jul 2026',
            str_contains($label, 'order') => '1234',
            str_contains($label, 'ticket') => '56',
            str_contains($label, 'quantity') || str_contains($label, 'qty') => '1000',
            str_contains($label, 'status') => 'Completed',
            str_contains($label, 'service') => 'Instagram Followers',
            str_contains($label, 'code') || str_contains($label, 'otp') || str_contains($label, 'pin') => '123456',
            str_contains($label, 'link') || str_contains($label, 'url') => 'https://example.com',
            str_contains($label, 'reply') || str_contains($label, 'message') => 'We have escalated this to our team.',
            str_contains($label, 'subject') => 'Order delivery question',
            default => 'Sample '.$position,
        };
    }
}
