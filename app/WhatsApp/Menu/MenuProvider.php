<?php

namespace App\WhatsApp\Menu;

/**
 * Builds the assistant's menus and holds the static maps that the router uses
 * to tell global navigation IDs apart from flow-internal selections.
 *
 * Selection id conventions:
 *   fl_*   → a leaf action that starts a flow (see $actionFlow)
 *   cat_*  → a tier-1 category that opens a submenu (see $categories)
 *   guest_* / menu_home → global navigation
 */
class MenuProvider
{
    /** Tier-1 category ids that open a submenu. */
    public static array $categories = ['cat_orders', 'cat_wallet', 'cat_services', 'cat_support', 'cat_account'];

    /** Actions a guest may trigger without being authenticated. */
    public static array $guestActions = ['fl_register', 'fl_login', 'fl_link', 'fl_forgot', 'fl_faq'];

    /** Leaf action id → flow id. */
    public static array $actionFlow = [
        'fl_order' => 'order',
        'fl_orders' => 'my_orders',
        'fl_track' => 'track',
        'fl_balance' => 'balance',
        'fl_deposit' => 'deposit',
        'fl_history' => 'history',
        'fl_browse' => 'browse',
        'fl_search' => 'search',
        'fl_ticket' => 'ticket',
        'fl_tickets' => 'tickets',
        'fl_profile' => 'profile',
        'fl_settings' => 'settings',
        'fl_faq' => 'faq',
        'fl_ai' => 'ask_ai',
        'fl_register' => 'register',
        'fl_login' => 'link',
        'fl_link' => 'link',
        'fl_forgot' => 'forgot',
    ];

    /**
     * Main menu for an authenticated user, returned as an interactive list spec:
     *   ['body'=>, 'button'=>, 'header'=>, 'sections'=>[...]]
     */
    public function mainMenu(?string $displayName = null, ?string $balanceText = null): array
    {
        $greeting = $displayName ? "Hi {$displayName}! 👋" : 'Hi there! 👋';
        $body = $greeting."\n\nWhat would you like to do?";
        if ($balanceText) {
            $body .= "\n\n💰 Wallet: *{$balanceText}*";
        }

        return [
            'header' => 'Main Menu',
            'body' => $body,
            'button' => 'Open menu',
            'footer' => 'Type *menu* anytime',
            'sections' => [
                ['title' => 'Orders', 'rows' => [
                    ['id' => 'fl_order', 'title' => '🚀 New order', 'description' => 'Place a new order'],
                    ['id' => 'fl_orders', 'title' => '📦 My orders', 'description' => 'Recent orders & status'],
                    ['id' => 'fl_track', 'title' => '🔎 Track order', 'description' => 'Track by order ID'],
                ]],
                ['title' => 'Wallet', 'rows' => [
                    ['id' => 'fl_balance', 'title' => '💰 Balance', 'description' => 'Your wallet balance'],
                    ['id' => 'fl_deposit', 'title' => '➕ Deposit', 'description' => 'Add funds'],
                    ['id' => 'fl_history', 'title' => '🧾 History', 'description' => 'Transactions'],
                ]],
                ['title' => 'Services', 'rows' => [
                    ['id' => 'fl_browse', 'title' => '📂 Browse', 'description' => 'Browse services'],
                    ['id' => 'fl_search', 'title' => '🔍 Search', 'description' => 'Find a service'],
                ]],
                ['title' => 'Help', 'rows' => [
                    ['id' => 'fl_ticket', 'title' => '🆘 Support', 'description' => 'Open a ticket'],
                    ['id' => 'fl_faq', 'title' => '❓ FAQ', 'description' => 'Common questions'],
                    ['id' => 'fl_ai', 'title' => '🤖 Ask AI', 'description' => 'Ask a question'],
                ]],
            ],
        ];
    }

    /** Menu shown to a not-yet-authenticated phone. */
    public function guestMenu(): array
    {
        return [
            'header' => 'Welcome',
            'body' => "👋 Welcome! I'm your social media growth assistant.\n\nRegister or link your account to browse services, place orders and track delivery right here on WhatsApp.",
            'button' => 'Get started',
            'sections' => [
                ['title' => 'Get started', 'rows' => [
                    ['id' => 'fl_register', 'title' => '📝 Register', 'description' => 'Create an account'],
                    ['id' => 'fl_login', 'title' => '🔑 Login', 'description' => 'Existing account'],
                    ['id' => 'fl_link', 'title' => '🔗 Link account', 'description' => 'Link this number'],
                ]],
                ['title' => 'Learn', 'rows' => [
                    ['id' => 'fl_faq', 'title' => '❓ FAQ', 'description' => 'Common questions'],
                    ['id' => 'guest_learn', 'title' => 'ℹ️ Learn more', 'description' => 'What we offer'],
                ]],
            ],
        ];
    }
}
