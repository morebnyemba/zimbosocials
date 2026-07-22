<?php

namespace App\Services\Upstream;

/**
 * Converts the link a customer pasted into the format a specific upstream
 * expects. Customers always give a URL (easy for them); some providers want a
 * bare username instead, and sending the wrong shape fails the order upstream.
 *
 * Keep the value set in step with the link_type column on service_upstreams.
 */
class LinkFormatter
{
    public const TYPES = ['url', 'username'];

    /** Transform a link for the given upstream link_type. */
    public static function forUpstream(string $link, ?string $linkType): string
    {
        $link = trim($link);

        return match ($linkType) {
            'username' => self::toUsername($link),
            default => $link, // 'url' or anything unknown — send as-is (safe default)
        };
    }

    /**
     * Pull the bare handle out of a profile link.
     *   https://tiktok.com/@jane   -> jane
     *   https://instagram.com/jane -> jane
     *   youtube.com/channel/UC123  -> UC123
     *   @jane / jane               -> jane
     */
    public static function toUsername(string $link): string
    {
        $link = trim($link);

        if (! preg_match('#^https?://#i', $link)) {
            // Already a handle (or a bare word) — just drop a leading @.
            return ltrim($link, '@');
        }

        $path = (string) (parse_url($link, PHP_URL_PATH) ?? '');
        $segments = array_values(array_filter(explode('/', $path), fn ($s) => $s !== ''));
        if ($segments === []) {
            return $link; // nothing to extract — don't mangle it
        }

        $first = ltrim($segments[0], '@');

        // YouTube-style prefixes put the handle in the SECOND segment.
        if (in_array(strtolower($first), ['channel', 'c', 'user'], true) && isset($segments[1])) {
            return ltrim($segments[1], '@');
        }

        return $first !== '' ? $first : $link;
    }

    /**
     * Best-effort guess of the link_type from the service name + description at
     * import time. Conservative on purpose: only flags 'username' on a clear
     * signal, otherwise 'url' (the safe default). An admin can always override.
     */
    public static function infer(string $name, string $description = ''): string
    {
        $t = mb_strtolower($name.' '.$description);

        if (preg_match('/\busername\b|\buser\s?name\b|username only|no\s*@|without\s*(http|link)|just\s*username|handle only|@\s*only/u', $t)) {
            return 'username';
        }

        return 'url';
    }
}
