<?php

namespace App\Services;

use App\Models\TranslationOverride;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;

class TranslationService
{
    /** Locales that can be edited/contributed to. */
    public const LOCALES = ['en', 'sn', 'nd'];

    private const CACHE_TTL = 3600; // 1 hour; also invalidated on approval

    /**
     * The flat messages array for a locale, with approved DB overrides merged on top
     * of the file-based resources/lang/{locale}/messages.php.
     *
     * @return array<string, string>
     */
    public function messages(string $locale): array
    {
        return Cache::remember("translations:merged:{$locale}", self::CACHE_TTL, function () use ($locale) {
            return array_merge($this->baseMessages($locale), $this->overrides($locale));
        });
    }

    /** Raw file-based messages for a locale (no overrides). */
    public function baseMessages(string $locale): array
    {
        $messages = Lang::get('messages', [], $locale);

        return is_array($messages) ? $messages : [];
    }

    /**
     * Approved overrides for a locale as a flat key => value map.
     *
     * @return array<string, string>
     */
    public function overrides(string $locale): array
    {
        return TranslationOverride::where('locale', $locale)
            ->pluck('value', 'key')
            ->all();
    }

    /**
     * Every editable key, using English as the source of truth, with the English
     * reference text. Sorted alphabetically.
     *
     * @return array<string, string>
     */
    public function editableKeys(): array
    {
        $base = $this->baseMessages('en');
        ksort($base);

        return $base;
    }

    /** Drop the merged cache for a locale (call after an override changes). */
    public function flush(string $locale): void
    {
        Cache::forget("translations:merged:{$locale}");
    }

    public function isEditableLocale(string $locale): bool
    {
        return in_array($locale, self::LOCALES, true);
    }
}
