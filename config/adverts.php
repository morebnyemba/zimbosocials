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
    |
    | 'image' => the branded plans-card graphic for that package, relative to
    | public/ (served as a plain static file, then sent as a WhatsApp image
    | message from AdvertiseFlow::confirmPrompt() once the customer has
    | picked). Converted to JPEG from the WebP they were designed in — Meta's
    | Cloud API only accepts image/jpeg and image/png for image messages, and
    | WebP isn't one of them.
    */
    'packages' => [
        // Repriced 2026-08-01 back down to a $25/week anchor (was briefly $30).
        // month1 repriced again 2026-09-15 ($65 → $80) to match the plans-card
        // graphics. Per-day rate falls as the duration grows: $6/day, ~$4.67/day,
        // $3.57/day, ~$2.67/day. Benefits still escalate with price, not just
        // reach — see each blurb.
        //
        // 'week2' (2 weeks / $42) was removed 2026-09 — no plans-card image
        // was made for it. Re-add here (and in previous_packages below, and
        // an image) if a two-week tier comes back.
        'day1' => [
            'label' => '1 day',
            'days' => 1,
            'price' => 6.00,
            'includes_video' => false,
            'blurb' => 'A quick test run — we boost a post you already have.',
            'image' => 'images/adverts/day1.jpg',
        ],
        'day3' => [
            'label' => '3 days',
            'days' => 3,
            'price' => 14.00,
            'includes_video' => true,
            'blurb' => 'Long enough to see real enquiries — includes a custom AI video advert too. Most people start here.',
            'recommended' => true,
            'image' => 'images/adverts/day3.jpg',
        ],
        'week1' => [
            'label' => '1 week',
            'days' => 7,
            'price' => 25.00,
            'includes_video' => true,
            'blurb' => 'A full week of reach — custom video advert, plus a progress update from our team partway through.',
            'image' => 'images/adverts/week1.jpg',
        ],
        'month1' => [
            'label' => '1 month',
            'days' => 30,
            'price' => 80.00,
            'includes_video' => true,
            'blurb' => 'Maximum reach — custom video advert (pick from 2 concepts), priority setup, a progress update partway through, and a wrap-up performance summary. Best for launches and busy seasons.',
            'image' => 'images/adverts/month1.jpg',
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
    | These are the prices in effect immediately before the CURRENT
    | 'repriced_at' — day1/day3/week1 are unchanged (grandfathering to the
    | same price is a harmless no-op) since only month1 moved this round.
    */
    'previous_packages' => [
        'day1' => ['price' => 6.00],
        'day3' => ['price' => 14.00],
        'week1' => ['price' => 25.00],
        'month1' => ['price' => 65.00],
    ],
    'repriced_at' => '2026-09-15 21:45:00',
    'reprice_grace_days' => 7,

    /*
    |--------------------------------------------------------------------------
    | Plans overview image
    |--------------------------------------------------------------------------
    | The combined graphic showing all 4 packages side by side — sent by
    | AdvertiseFlow::packageMenu() BEFORE the picker, so the customer sees the
    | full lineup before choosing. Distinct from each package's own 'image'
    | above, which is sent AFTER they pick, for that package specifically.
    */
    'overview_image' => 'images/adverts/plans-overview.png',
];
