<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sponsored advert packages
    |--------------------------------------------------------------------------
    | Weekly managed ad campaigns run by the team on Facebook / Instagram.
    | Unlike catalogue services these are priced FLAT PER WEEK (not per 1,000)
    | and fulfilled by a human, so they live here rather than in `services`.
    |
    | Keep the "Sponsored adverts" knowledge-base entry in step with these
    | prices — the assistant quotes the KB when it explains the packages.
    */
    'packages' => [
        'starter' => [
            'label' => 'Starter',
            'weekly_price' => 15.00,
            'blurb' => 'A steady local push — great for testing what your audience responds to.',
        ],
        'standard' => [
            'label' => 'Standard',
            'weekly_price' => 30.00,
            'blurb' => 'Our most popular — noticeably wider reach and more enquiries.',
        ],
        'max' => [
            'label' => 'Maximum reach',
            'weekly_price' => 50.00,
            'blurb' => 'Widest reach — best for launches, events and busy trading periods.',
        ],
    ],

    // Guard rails for how long a single booking can run.
    'min_weeks' => 1,
    'max_weeks' => 12,
];
