<?php

return [
    'brand_name' => 'Zimbo Socials',
    'footer' => [
        'manage_preferences' => 'You received this message from Zimbo Socials. Manage notification preferences in your account settings.',
    ],
    'templates' => [
        'generic' => [
            'subject' => 'Account Notification',
            'body' => 'There is an update on your account.',
        ],
        'welcome' => [
            'subject' => 'Welcome to Zimbo Socials',
            'body' => 'Hi :name, your account is ready. Start growing your social media presence today.',
            'helper' => 'Need help? Visit the Help Center or contact support any time.',
        ],
        'reset_password' => [
            'subject' => 'Reset Your Password',
            'body' => 'Hi :name, we received a request to reset your password. Use the button below to continue.',
            'cta' => 'Reset Password',
        ],
        'verify_email' => [
            'subject' => 'Verify Your Email Address',
            'body' => 'Hi :name, please verify your email to secure your account and unlock all features.',
            'cta' => 'Verify Email',
        ],
    ],
];
