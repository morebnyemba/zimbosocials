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
        'day1' => [
            'label' => '1 day',
            'days' => 1,
            'price' => 5.00,
            'includes_video' => false,
            'blurb' => 'A quick test run — we boost a post you already have.',
        ],
        'day3' => [
            'label' => '3 days',
            'days' => 3,
            'price' => 10.00,
            'includes_video' => false,
            'blurb' => 'Boost-only, long enough to see real enquiries — most people start here.',
            'recommended' => true,
        ],
        'week1' => [
            'label' => '1 week',
            'days' => 7,
            'price' => 20.00,
            'includes_video' => true,
            'blurb' => 'A full week of reach — includes a custom video advert we make for you.',
        ],
        'week2' => [
            'label' => '2 weeks',
            'days' => 14,
            'price' => 35.00,
            'includes_video' => true,
            'blurb' => 'Sustained presence + a custom video advert — better value per day.',
        ],
        'month1' => [
            'label' => '1 month',
            'days' => 30,
            'price' => 60.00,
            'includes_video' => true,
            'blurb' => 'Maximum reach + a custom video advert — best for launches and busy seasons.',
        ],
    ],
];
