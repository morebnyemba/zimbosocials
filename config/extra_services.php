<?php

return [
    /*
    |--------------------------------------------------------------------------
    | One-off account & advertising support services
    |--------------------------------------------------------------------------
    | Flat-fee, human-fulfilled services that are neither a page-growth order
    | (Service model, dispatched upstream) nor a managed ad campaign
    | (config/adverts.php, AdvertiseFlow) — helping someone run their OWN ads,
    | or recovering a broken/banned social account.
    |
    | No automated booking flow yet: the assistant quotes the price and points
    | the customer to *support* to arrange payment and get started, same as any
    | other custom request. Build a dedicated flow (mirroring AdvertiseFlow) if
    | volume ever justifies it.
    |
    | Keep the "Account & advertising support" knowledge-base entry in step with
    | these prices — the assistant quotes the KB when it explains them.
    */
    'items' => [
        'ad_setup_training' => [
            'label' => 'Advertising setup & training',
            'price' => 20.00,
            'blurb' => 'For businesses who want to run their OWN Facebook/Instagram ads — we set up your ad account and show you how, rather than running it for you.',
        ],
        'ads_account_fix' => [
            'label' => 'Ads account fix',
            'price' => 5.00,
            'blurb' => "Your ads account is misconfigured, restricted or won't run ads properly — we diagnose and fix it.",
        ],
        'account_recovery' => [
            'label' => 'Account recovery & unbanning',
            'price' => 10.00,
            'blurb' => 'Facebook, WhatsApp or Instagram account hacked, disabled or banned — we help you recover access.',
        ],
    ],
];
