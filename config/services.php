<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'tawk' => [
        'property_id' => env('TAWK_PROPERTY_ID'),
        'widget_id' => env('TAWK_WIDGET_ID'),
    ],

    'whatsapp' => [
        'provider' => env('WHATSAPP_PROVIDER', 'meta'),
        'api_token' => env('WHATSAPP_API_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'waba_id' => env('WHATSAPP_WABA_ID'),              // WhatsApp Business Account ID (for templates)
        'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v21.0'),
        // Inbound webhook (conversational assistant):
        'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),  // GET handshake token you set in Meta
        'app_secret' => env('WHATSAPP_APP_SECRET'),                       // signs X-Hub-Signature-256 on POSTs
        'assistant_enabled' => env('WHATSAPP_ASSISTANT_ENABLED', true),
        'ai_max_services' => (int) env('WHATSAPP_AI_MAX_SERVICES', 0),  // 0 = give the AI every active service
        'twilio_sid' => env('TWILIO_SID'),
        'twilio_from' => env('TWILIO_WHATSAPP_FROM', 'whatsapp:+14155238886'),
    ],

    'paynow' => [
        'integration_id' => env('PAYNOW_INTEGRATION_ID'),
        'integration_key' => env('PAYNOW_INTEGRATION_KEY'),
        // While the integration is in TEST MODE, Paynow only accepts the
        // merchant's own registered email. Set this to that email to test the
        // full payment flow. Leave blank in production (live mode).
        'test_email' => env('PAYNOW_TEST_EMAIL'),
    ],

    'google_analytics' => [
        // GA4 Measurement ID, e.g. G-XXXXXXXXXX. Leave empty to disable tracking.
        'id' => env('GOOGLE_ANALYTICS_ID'),
    ],

    /*
     * Shared secret used to authenticate the generic payment webhook
     * (POST /webhooks/payment). The caller must send an
     * X-Webhook-Signature: sha256=<hex> header where the hex is
     * HMAC-SHA256 of the raw request body keyed with this secret.
     * If unset, the endpoint rejects every request (fail closed).
     */
    'payment_webhook' => [
        'secret' => env('PAYMENT_WEBHOOK_SECRET'),
    ],

    'referral' => [
        'first_deposit_reward' => (float) env('REFERRAL_FIRST_DEPOSIT_REWARD', 1.00),
        'order_commission_percent' => (float) env('REFERRAL_ORDER_COMMISSION_PERCENT', 2.00),
        'order_commission_min_total' => (float) env('REFERRAL_ORDER_COMMISSION_MIN_TOTAL', 20.00),
        'referred_first_deposit_bonus_percent' => (float) env('REFERRED_FIRST_DEPOSIT_BONUS_PERCENT', 10.00),
        'min_qualifying_deposit' => (float) env('REFERRAL_MIN_QUALIFYING_DEPOSIT', 5.00),
        // Ongoing order commissions pause after this many days with no new referral
        // (0 = never expire). Referrers are warned this many days before.
        'commission_active_days' => (int) env('REFERRAL_COMMISSION_ACTIVE_DAYS', 60),
        'commission_warn_days' => (int) env('REFERRAL_COMMISSION_WARN_DAYS', 7),
        // A referred user permanently stops generating commissions this many
        // months after they joined (0 = never permanently expires).
        'lifetime_months' => (int) env('REFERRAL_LIFETIME_MONTHS', 36),
    ],

    'monetizer' => [
        'threshold_usd' => (float) env('MONETIZER_THRESHOLD_USD', 100.00),
        'lookback_days' => (int) env('MONETIZER_LOOKBACK_DAYS', 90),

        /**
         * Platform monetization goals.
         * Each requirement maps to active services by category + optional name keywords.
         * Progress is calculated from the user's completed/processing/pending orders.
         */
        'platforms' => [
            'youtube' => [
                'label' => 'YouTube Partner Program',
                'requirements' => [
                    'subscribers' => [
                        'label' => '1,000 subscribers',
                        'target' => 1000,
                        'metric' => 'subscribers',
                        'match' => ['category' => 'youtube', 'name_contains' => ['subscriber']],
                    ],
                    'watch_hours' => [
                        'label' => '4,000 watch hours',
                        'target' => 4000,
                        'metric' => 'watch_hours',
                        'match' => ['category' => 'youtube', 'name_contains' => ['watch hour']],
                    ],
                ],
            ],
            'facebook' => [
                'label' => 'Facebook Monetization',
                'requirements' => [
                    'page_followers' => [
                        'label' => '5,000 page followers',
                        'target' => 5000,
                        'metric' => 'page_followers',
                        'match' => ['category' => 'facebook', 'name_contains' => ['page follower']],
                    ],
                    'video_views' => [
                        'label' => '60,000 video view minutes',
                        'target' => 60000,
                        'metric' => 'video_views',
                        'match' => ['category' => 'facebook', 'name_contains' => ['video view']],
                    ],
                ],
            ],
            'tiktok' => [
                'label' => 'TikTok Creator Rewards',
                'requirements' => [
                    'followers' => [
                        'label' => '10,000 followers',
                        'target' => 10000,
                        'metric' => 'followers',
                        'match' => ['category' => 'tiktok', 'name_contains' => ['follower']],
                    ],
                    'views' => [
                        'label' => '100,000 video views (last 30 days)',
                        'target' => 100000,
                        'metric' => 'views',
                        'match' => ['category' => 'tiktok', 'name_contains' => ['video view']],
                    ],
                ],
            ],
            'instagram' => [
                'label' => 'Instagram Monetization',
                'requirements' => [
                    'followers' => [
                        'label' => '10,000 followers',
                        'target' => 10000,
                        'metric' => 'followers',
                        'match' => ['category' => 'instagram', 'name_contains' => ['follower']],
                    ],
                    'engagement' => [
                        'label' => 'Engagement signals',
                        'target' => null,
                        'match' => ['category' => 'instagram', 'name_contains' => ['like', 'view', 'comment', 'save']],
                    ],
                ],
            ],
            'x' => [
                'label' => 'X / Twitter Monetization',
                'requirements' => [
                    'followers' => [
                        'label' => '500 followers',
                        'target' => 500,
                        'metric' => 'followers',
                        'match' => ['category' => 'twitter', 'name_contains' => ['follower']],
                    ],
                ],
            ],
        ],
    ],

    // Google Gemini — optional. When the API key is set, admins can opt in to
    // AI cleanup + Shona/Ndebele translation when importing upstream services.
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 30),
    ],

];
