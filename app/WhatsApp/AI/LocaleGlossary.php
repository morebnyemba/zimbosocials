<?php

namespace App\WhatsApp\AI;

/**
 * Builds a domain glossary from the site's own i18n (the messages.php lang
 * files) so the AI uses the panel's approved Shona/Ndebele terms instead of
 * guessing. Injected into the AI context for non-English locales.
 */
class LocaleGlossary
{
    public const LANGUAGES = ['en' => 'English', 'sn' => 'Shona', 'nd' => 'Ndebele'];

    /** Curated domain keys that exist in messages.php across all locales. */
    private const KEYS = [
        'balance', 'current_balance', 'wallet_status', 'services', 'service',
        'select_service', 'order_quantity', 'order_summary', 'place_order',
        'confirm_payment', 'charge', 'link', 'search_services', 'service_catalog',
        'instant', 'refill', 'avg_speed', 'deposit_funds', 'add_funds',
    ];

    public static function languageName(string $locale): string
    {
        return self::LANGUAGES[$locale] ?? 'English';
    }

    /**
     * English term → localized term, for terms that resolve in this locale.
     *
     * @return array<string,string>
     */
    public static function forLocale(string $locale): array
    {
        if (! isset(self::LANGUAGES[$locale]) || $locale === 'en') {
            return [];
        }

        $glossary = [];
        foreach (self::KEYS as $key) {
            $path = 'messages.'.$key;
            $en = trans($path, [], 'en');
            $loc = trans($path, [], $locale);

            // Skip unresolved keys (Laravel returns the path itself) and no-ops.
            if ($en === $path || $loc === $path || $en === $loc) {
                continue;
            }
            $glossary[$en] = $loc;
        }

        return $glossary;
    }

    /** A prompt-ready block for the given locale, or '' for English/none. */
    public static function promptBlock(string $locale): string
    {
        $glossary = self::forLocale($locale);
        if (! $glossary) {
            return '';
        }

        $name = self::languageName($locale);
        $lines = ["=== {$name} GLOSSARY (use these approved terms; do not invent {$name} words) ==="];
        foreach ($glossary as $en => $loc) {
            $lines[] = "{$en} = {$loc}";
        }
        $lines[] = '===';

        return implode("\n", $lines);
    }
}
