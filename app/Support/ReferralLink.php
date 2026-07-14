<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;

/**
 * Builds a user's referral link — single source of truth shared by the web
 * referrals page and the WhatsApp assistant. The landing page is configurable
 * via the `referral_landing_path` setting; CaptureReferral middleware records
 * `?ref=` on any page.
 */
class ReferralLink
{
    public static function for(User $user): string
    {
        if (! $user->getAttribute('referral_code')) {
            $user->update(['referral_code' => User::generateReferralCode()]);
            $user->refresh();
        }

        return self::build((string) $user->getAttribute('referral_code'));
    }

    public static function build(string $code): string
    {
        $path = trim((string) Setting::get('referral_landing_path', '/'));

        if ($path === '') {
            $path = '/';
        }
        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        $separator = str_contains($path, '?') ? '&' : '?';

        return url($path.$separator.'ref='.urlencode($code));
    }
}
