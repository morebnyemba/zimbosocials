<?php

/**
 * WhatsApp Message Templates — these definitions mirror what's registered
 * in the Meta WhatsApp Business Account.
 *
 * Run `php artisan whatsapp:sync-templates` to push these to Meta for approval.
 *
 * Template rules (Meta):
 *  - Names: lowercase, underscores only
 *  - Variables: {{1}}, {{2}}, etc. (positional, 1-indexed)
 *  - Must be approved before sending business-initiated messages
 *  - Categories: UTILITY, MARKETING, AUTHENTICATION
 */
return [

    'language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'en'),

    'templates' => [

        // ─── Account ─────────────────────────────────────────────────────────
        'welcome_message' => [
            'category' => 'UTILITY',
            'body' => "Welcome to Zimbo Socials, {{1}}! 🎉\n\nYour account has been created successfully. Start growing your social media presence today.\n\nNeed help? Reply to this message or visit our Help Center.",
            'params' => ['user_name'],
            'header' => null,
            'footer' => 'Zimbo Socials',
            'buttons' => [],
        ],

        // ─── Deposits ────────────────────────────────────────────────────────
        'deposit_confirmed' => [
            'category' => 'UTILITY',
            'body' => "✅ *Deposit Confirmed*\n\nHi {{1}}, your deposit of *{{2}}* has been credited to your wallet.\n\n💰 New Balance: {{3}}\n📅 Date: {{4}}\n\nThank you for topping up!",
            'params' => ['user_name', 'amount', 'new_balance', 'date'],
            'header' => null,
            'footer' => 'Zimbo Socials',
            'buttons' => [],
        ],

        'deposit_rejected' => [
            'category' => 'UTILITY',
            'body' => "❌ *Deposit Rejected*\n\nHi {{1}}, your deposit request of {{2}} could not be verified.\n\nPlease ensure you sent the correct amount and reference. Contact support if you need assistance.",
            'params' => ['user_name', 'amount'],
            'header' => null,
            'footer' => 'Zimbo Socials',
            'buttons' => [],
        ],

        // ─── Withdrawals ─────────────────────────────────────────────────────
        'withdrawal_processed' => [
            'category' => 'UTILITY',
            'body' => "🏧 *Withdrawal Processed*\n\nHi {{1}}, your withdrawal of *{{2}}* has been sent to your account.\n\nPlease allow 24–48 hours for the funds to reflect.\n\n📅 Processed: {{3}}\n\nThank you for your patience.",
            'params' => ['user_name', 'amount', 'date'],
            'header' => null,
            'footer' => 'Zimbo Socials',
            'buttons' => [],
        ],

        'withdrawal_rejected' => [
            'category' => 'UTILITY',
            'body' => "❌ *Withdrawal Rejected*\n\nHi {{1}}, your withdrawal request of *{{2}}* was rejected.\n\n💰 The funds have been returned to your wallet balance. Contact support if you need assistance.",
            'params' => ['user_name', 'amount'],
            'header' => null,
            'footer' => 'Zimbo Socials',
            'buttons' => [],
        ],

        // ─── Orders ──────────────────────────────────────────────────────────
        'order_status_changed' => [
            'category' => 'UTILITY',
            'body' => "📦 Hi {{1}}, an update on your order *#{{2}}* — the status is now *{{3}}*. This is for the service {{4}} with a quantity of {{5}}. Thank you for choosing us, we'll keep you posted!",
            'params' => ['user_name', 'order_id', 'status', 'service_name', 'quantity'],
            'header' => null,
            'footer' => 'Zimbo Socials',
            'buttons' => [],
        ],

        'order_refunded' => [
            'category' => 'UTILITY',
            'body' => "💸 *Order Refunded*\n\nHi {{1}}, your order *#{{2}}* has been refunded.\n\nRefund Amount: *{{3}}*\nYour updated balance: {{4}}\n\nSorry for the inconvenience.",
            'params' => ['user_name', 'order_id', 'refund_amount', 'new_balance'],
            'header' => null,
            'footer' => 'Zimbo Socials',
            'buttons' => [],
        ],

        // ─── Admin Alerts ────────────────────────────────────────────────────
        'admin_new_registration' => [
            'category' => 'UTILITY',
            'body' => "🆕 New sign-up on Zimbo Socials! {{1}} ({{2}}) just created an account as a *{{3}}*. Open the admin panel to view their details.",
            'params' => ['user_name', 'user_email', 'role'],
            'header' => null,
            'footer' => 'Zimbo Socials Admin',
            'buttons' => [],
        ],

        'admin_new_order' => [
            'category' => 'UTILITY',
            'body' => "🛒 A new order was just placed on Zimbo Socials! Order *#{{1}}* from {{2}} for the service {{3}}, charged {{4}}. Open the admin panel to review it.",
            'params' => ['order_id', 'user_name', 'service_name', 'amount'],
            'header' => null,
            'footer' => 'Zimbo Socials Admin',
            'buttons' => [],
        ],

        // ─── Balance ─────────────────────────────────────────────────────────
        'balance_adjusted' => [
            'category' => 'UTILITY',
            'body' => "💰 *Balance Adjustment*\n\nHi {{1}}, your balance has been adjusted by *{{2}}*.\n\nReason: {{3}}\nNew Balance: {{4}}\n\nQuestions? Just reply here.",
            'params' => ['user_name', 'adjustment', 'reason', 'new_balance'],
            'header' => null,
            'footer' => 'Zimbo Socials',
            'buttons' => [],
        ],

        // ─── Account Changes ─────────────────────────────────────────────────
        'role_changed' => [
            'category' => 'UTILITY',
            'body' => "👤 *Account Role Updated*\n\nHi {{1}}, your account role has been changed to *{{2}}*.\n\nThis may affect your dashboard access and available features.",
            'params' => ['user_name', 'new_role'],
            'header' => null,
            'footer' => 'Zimbo Socials',
            'buttons' => [],
        ],

        // ─── Contracts ───────────────────────────────────────────────────────
        'contract_application' => [
            'category' => 'UTILITY',
            'body' => "📋 *New Contract Application*\n\nHi {{1}}, you've received a new application for your contract \"{{2}}\".\n\nApplicant: {{3}}\n\nLog in to review and accept or decline.",
            'params' => ['business_name', 'contract_title', 'applicant_name'],
            'header' => null,
            'footer' => 'Zimbo Socials',
            'buttons' => [],
        ],

        // ─── Marketing Broadcast ────────────────────────────────────────────
        'marketing_broadcast' => [
            'category' => 'MARKETING',
            // Leads and ends with fixed text (Meta forbids a variable at the
            // start/end) and keeps plenty of literal words so the variable-to-
            // words ratio stays within limits.
            'body' => "📣 Hi {{1}}, we've got an update from the Zimbo Socials team about *{{2}}*:\n\n{{3}}\n\nReply STOP to opt out of promotional messages anytime.",
            'params' => ['user_name', 'subject', 'body'],
            'header' => null,
            'footer' => 'Zimbo Socials',
            'buttons' => [],
        ],

        // ─── Tickets ─────────────────────────────────────────────────────────
        'ticket_reply' => [
            'category' => 'UTILITY',
            'body' => "🎫 *Ticket Reply*\n\nHi {{1}}, there's a new reply on your support ticket: \"{{2}}\".\n\nLog in to view the response and continue the conversation.",
            'params' => ['user_name', 'ticket_subject'],
            'header' => null,
            'footer' => 'SlykerTech SMM',
            'buttons' => [],
        ],
    ],
];
