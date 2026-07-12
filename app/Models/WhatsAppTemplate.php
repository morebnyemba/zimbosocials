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
        $components = [];

        if (! empty($tpl['header'])) {
            $components[] = ['type' => 'HEADER', 'format' => 'TEXT', 'text' => $tpl['header']];
        }

        $components[] = ['type' => 'BODY', 'text' => $tpl['body']];

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
}
