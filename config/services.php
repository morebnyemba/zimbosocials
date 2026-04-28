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
        'provider'        => env('WHATSAPP_PROVIDER', 'meta'),
        'api_token'       => env('WHATSAPP_API_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'waba_id'         => env('WHATSAPP_WABA_ID'),              // WhatsApp Business Account ID (for templates)
        'twilio_sid'      => env('TWILIO_SID'),
        'twilio_from'     => env('TWILIO_WHATSAPP_FROM', 'whatsapp:+14155238886'),
    ],

    'paynow' => [
        'integration_id'  => env('PAYNOW_INTEGRATION_ID'),
        'integration_key' => env('PAYNOW_INTEGRATION_KEY'),
    ],

];
