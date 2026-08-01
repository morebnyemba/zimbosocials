<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sponsored advert packages
    |--------------------------------------------------------------------------
    | Managed ad campaigns run by the team on Facebook / Instagram. Each package
    | is a FLAT price for a FIXED duration (in days) — so the menu spans a cheap
    | one-day test right up to a full month, and there's no "how many weeks?"
    | maths for the customer.
    |
    | 'includes_video' => the package price includes our team PRODUCING a short
    | video advert for them. Cheap test runs are boost-only (we run whatever they
    | already have); the longer packages get a made-for-you video. Flip the flag
    | on any tier to change what's promised.
    |
    | Keep the "Sponsored adverts" knowledge-base entry in step with these
    | prices/inclusions — the assistant quotes the KB when it explains packages.
    | 'recommended' marks the default the AI should nudge people toward.
    */
    'packages' => [
        // Repriced 2026-08-01 off a $30/week anchor (previously $25). Per-day
        // rate still falls as the duration grows, so the "better value at
        // longer durations" story in the blurbs below still holds:
        // $7/day, ~$5.67/day, ~$4.29/day, ~$3.57/day, $2.50/day.
        'day1' => [
            'label' => '1 day',
            'days' => 1,
            'price' => 7.00,
            'includes_video' => false,
            'blurb' => 'A quick test run — we boost a post you already have.',
        ],
        'day3' => [
            'label' => '3 days',
            'days' => 3,
            'price' => 17.00,
            'includes_video' => false,
            'blurb' => 'Boost-only, long enough to see real enquiries — most people start here.',
            'recommended' => true,
        ],
        'week1' => [
            'label' => '1 week',
            'days' => 7,
            'price' => 30.00,
            'includes_video' => true,
            'blurb' => 'A full week of reach — includes a custom video advert we make for you.',
        ],
        'week2' => [
            'label' => '2 weeks',
            'days' => 14,
            'price' => 50.00,
            'includes_video' => true,
            'blurb' => 'Sustained presence + a custom video advert — better value per day.',
        ],
        'month1' => [
            'label' => '1 month',
            'days' => 30,
            'price' => 75.00,
            'includes_video' => true,
            'blurb' => 'Maximum reach + a custom video advert — best for launches and busy seasons.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Price-change grandfathering
    |--------------------------------------------------------------------------
    | A contact who already existed before 'repriced_at' keeps seeing the price
    | they'd have been quoted before, for 'reprice_grace_days' afterwards — a
    | price change should never be a surprise to someone mid-conversation. A
    | brand new contact created after 'repriced_at' always sees the current
    | price above. See AdvertBooking::priceFor().
    |
    | These are the ORIGINAL prices that were actually live in production
    | ($20/week) — not the $25/week intermediate, which was only ever a local
    | commit and never reached a real customer, so there's nothing to
    | grandfather from it.
    */
    'previous_packages' => [
        'day1' => ['price' => 5.00],
        'day3' => ['price' => 10.00],
        'week1' => ['price' => 20.00],
        'week2' => ['price' => 35.00],
        'month1' => ['price' => 60.00],
    ],
    'repriced_at' => '2026-08-01 20:00:00',
    'reprice_grace_days' => 7,
];
