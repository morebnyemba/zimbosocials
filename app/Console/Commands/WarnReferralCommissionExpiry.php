<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\NotificationService;
use App\Services\ReferralService;
use Illuminate\Console\Command;

/**
 * Warns referrers whose ongoing-commission window is about to lapse (they
 * haven't referred anyone new recently), so they can refer again before their
 * commissions pause. Warns once per window via `referral_commission_warned_at`.
 */
class WarnReferralCommissionExpiry extends Command
{
    protected $signature = 'referral:warn-commission-expiry';

    protected $description = 'Notify referrers before their ongoing commission window lapses.';

    public function handle(ReferralService $referrals): int
    {
        $activeDays = $referrals->commissionActiveDays();
        if ($activeDays <= 0) {
            $this->info('Commission expiry disabled (active_days = 0). Nothing to do.');

            return self::SUCCESS;
        }

        $warnDays = $referrals->commissionWarnDays();
        $warned = 0;

        // Only users who have referred someone can have a commission window.
        User::query()
            ->whereIn('id', fn ($q) => $q->select('referred_by')->from('users')->whereNotNull('referred_by'))
            ->chunkById(200, function ($referrers) use ($referrals, $warnDays, &$warned) {
                foreach ($referrers as $referrer) {
                    $until = $referrals->commissionActiveUntil((int) $referrer->id);
                    if (! $until || $until->isPast()) {
                        continue; // already lapsed (or none) — nothing to warn about
                    }

                    $daysLeft = (int) ceil(now()->diffInDays($until, false));
                    if ($daysLeft < 0 || $daysLeft > $warnDays) {
                        continue; // outside the warning window
                    }

                    // Warn once per window: skip if already warned since their last referral.
                    $lastReferralAt = User::where('referred_by', $referrer->id)->max('created_at');
                    $warnedAt = $referrer->referral_commission_warned_at;
                    if ($warnedAt && $lastReferralAt && $warnedAt >= $lastReferralAt) {
                        continue;
                    }

                    NotificationService::notify(
                        (int) $referrer->id,
                        'referral_commission_expiring',
                        'Keep your referral commissions active',
                        "Refer at least one new person in the next {$daysLeft} day(s) to keep earning commissions on your referrals' orders.",
                        ['days_left' => $daysLeft, 'active_until' => $until->toDateString()],
                    );

                    $referrer->forceFill(['referral_commission_warned_at' => now()])->saveQuietly();
                    $warned++;
                }
            });

        $this->info("Referral commission expiry warnings sent: {$warned}");

        return self::SUCCESS;
    }
}
