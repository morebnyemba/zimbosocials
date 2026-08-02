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
    | video advert for them. Only the 1-day test stays boost-only (we run
    | whatever they already have); everything from 3 days up gets a made-for-you
    | video — moved down from "1 week+" when prices rose, so the jump from the
    | cheapest test tier buys real, visible extra value. Flip the flag on any
    | tier to change what's promised.
    |
    | Keep the "Sponsored adverts" knowledge-base entry in step with these
    | prices/inclusions — the assistant quotes the KB when it explains packages.
    | 'recommended' marks the default the AI should nudge people toward.
    */
    'packages' => [
        // Repriced 2026-08-01 back down to a $25/week anchor (was briefly $30).
        // Per-day rate still falls as the duration grows: $6/day, ~$4.67/day,
        // $3.57/day, $3/day, ~$2.17/day. Benefits still escalate with price,
        // not just reach — see each blurb.
        'day1' => [
            'label' => '1 day',
            'days' => 1,
            'price' => 6.00,
            'includes_video' => false,
            'blurb' => 'A quick test run — we boost a post you already have.',
        ],
        'day3' => [
            'label' => '3 days',
            'days' => 3,
            'price' => 14.00,
            'includes_video' => true,
            'blurb' => 'Long enough to see real enquiries — includes a custom AI video advert too. Most people start here.',
            'recommended' => true,
        ],
        'week1' => [
            'label' => '1 week',
            'days' => 7,
            'price' => 25.00,
            'includes_video' => true,
            'blurb' => 'A full week of reach — custom video advert, plus a progress update from our team partway through.',
        ],
        'week2' => [
            'label' => '2 weeks',
            'days' => 14,
            'price' => 42.00,
            'includes_video' => true,
            'blurb' => 'Sustained presence — custom video advert (pick from 2 concepts), a progress update partway through, and better value per day.',
        ],
        'month1' => [
            'label' => '1 month',
            'days' => 30,
            'price' => 65.00,
            'includes_video' => true,
            'blurb' => 'Maximum reach — custom video advert (pick from 2 concepts), priority setup, a progress update partway through, and a wrap-up performance summary. Best for launches and busy seasons.',
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
