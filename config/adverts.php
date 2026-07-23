<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sponsored advert packages
    |--------------------------------------------------------------------------
    | Weekly managed ad campaigns run by the team on Facebook / Instagram.
    | Each package is a FLAT price for a FIXED duration (in days) — so the menu
    | spans a cheap one-day test right up to a full month, and there's no
    | "how many weeks?" maths for the customer.
    |
    | Keep the "Sponsored adverts" knowledge-base entry in step with these
    | prices — the assistant quotes the KB when it explains the packages.
    | 'recommended' marks the default the AI should nudge people toward.
    */
    'packages' => [
        'day1' => [
            'label' => '1 day',
            'days' => 1,
            'price' => 5.00,
            'blurb' => 'A quick test run to gauge the response.',
        ],
        'day3' => [
            'label' => '3 days',
            'days' => 3,
            'price' => 10.00,
            'blurb' => 'Long enough to see real enquiries — most people start here.',
            'recommended' => true,
        ],
        'week1' => [
            'label' => '1 week',
            'days' => 7,
            'price' => 20.00,
            'blurb' => 'A full week of steady reach.',
        ],
        'week2' => [
            'label' => '2 weeks',
            'days' => 14,
            'price' => 35.00,
            'blurb' => 'Sustained presence — better value per day.',
        ],
        'month1' => [
            'label' => '1 month',
            'days' => 30,
            'price' => 60.00,
            'blurb' => 'Maximum reach — best for launches and busy seasons.',
        ],
    ],
];
