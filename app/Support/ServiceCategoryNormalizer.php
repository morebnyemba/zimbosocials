<?php

namespace App\Support;

/**
 * Canonicalizes upstream-provider category strings into a small, stable set
 * of platform names.
 *
 * Upstream SMM panels send free-text categories that vary per provider and
 * often per import ("Instagram Followers", "Instagram - Likes [Real]",
 * "🔥 INSTAGRAM Views") — imported verbatim, these fragment what should be
 * one "Instagram" grouping into dozens of near-duplicate category tabs on
 * every page that lists services by category (customer catalog, order
 * creation, marketing landing page). Normalizing at import time keeps the
 * stored data itself clean, so every reader benefits without duplicating
 * this logic.
 */
class ServiceCategoryNormalizer
{
    /**
     * Canonical platform name => keywords that identify it. Checked in
     * order, first match wins, so more specific platforms should be listed
     * before broader/ambiguous ones.
     */
    private const PLATFORMS = [
        'Instagram' => ['instagram', 'insta '],
        'YouTube' => ['youtube', 'you tube'],
        'TikTok' => ['tiktok', 'tik tok'],
        'Facebook' => ['facebook', 'fb page', 'fb group', 'fb profile'],
        'Twitter / X' => ['twitter', 'tweet', ' x.com', 'x (twitter)', 'x posts', 'x followers', 'x likes', 'x views'],
        'Telegram' => ['telegram'],
        'WhatsApp' => ['whatsapp'],
        'Spotify' => ['spotify'],
        'LinkedIn' => ['linkedin'],
        'Discord' => ['discord'],
        'Threads' => ['threads'],
        'Snapchat' => ['snapchat', 'snap chat'],
        'Twitch' => ['twitch'],
        'SoundCloud' => ['soundcloud'],
        'Pinterest' => ['pinterest'],
        'Reddit' => ['reddit'],
    ];

    /**
     * Normalize a raw upstream category string to its canonical platform
     * name. Falls back to the trimmed original (title-cased) if no known
     * platform keyword matches, rather than dumping everything into an
     * "Other" bucket that would itself become a dumping-ground fragment.
     */
    public static function normalize(string $raw): string
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return 'Other';
        }

        // Strip emoji/symbols and collapse whitespace before matching, so
        // "🔥 INSTAGRAM Views 🔥" and "Instagram Views" match identically.
        $clean = preg_replace('/[^\p{L}\p{N}\s.\/-]/u', ' ', $trimmed) ?? $trimmed;
        $lower = ' '.strtolower(preg_replace('/\s+/', ' ', $clean) ?? $clean).' ';

        foreach (self::PLATFORMS as $canonical => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    return $canonical;
                }
            }
        }

        return $trimmed;
    }
}
