<?php

namespace App\WhatsApp;

use Illuminate\Support\Facades\Cache;

/**
 * Frequency cap for referral-program mentions in chat. "Occasionally" means
 * at most once per phone per cooldown window, shared across every surface
 * that can bring it up (the AI's organic nudge, the order-complete footer,
 * the referral flow itself) — so a user never gets it twice in one day no
 * matter which path fires first.
 */
class ReferralNudge
{
    private const COOLDOWN_HOURS = 24;

    public static function allowed(string $phone): bool
    {
        return ! Cache::has(self::key($phone));
    }

    public static function mark(string $phone): void
    {
        Cache::put(self::key($phone), 1, now()->addHours(self::COOLDOWN_HOURS));
    }

    private static function key(string $phone): string
    {
        return 'wa:refnudge:'.$phone;
    }
}
