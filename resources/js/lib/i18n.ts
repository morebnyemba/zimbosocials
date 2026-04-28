import { usePage } from '@inertiajs/react';

/** Replace :placeholder tokens in a translation string */
function replacePlaceholders(str: string, replace?: Record<string, string | number>): string {
    if (!replace) return str;
    return Object.entries(replace).reduce(
        (acc, [key, val]) => acc.replace(new RegExp(`:${key}`, 'g'), String(val)),
        str
    );
}

/**
 * Translate a key from the shared messages translations.
 * Falls back to the key itself if not found.
 */
export function useTranslation() {
    const translations = (usePage().props as any).translations as Record<string, string> ?? {};
    const locale = (usePage().props as any).locale as string ?? 'en';

    function t(key: string, replace?: Record<string, string | number>): string {
        const val = translations[key];
        if (!val) return key;
        return replacePlaceholders(val, replace);
    }

    return { t, locale };
}
