<?php

namespace App\WhatsApp\Routing;

/**
 * Recognises universal typed commands and data shortcuts. Returns a canonical
 * command id, or null when the text isn't a command (so it can fall through to
 * flows / intent / AI).
 */
class CommandRegistry
{
    /** Exact-word triggers → command id. */
    private array $map = [
        'menu' => 'menu', 'home' => 'menu', 'start' => 'menu', 'hi' => 'menu', 'hello' => 'menu',
        'help' => 'help', '?' => 'help',
        'back' => 'back',
        'cancel' => 'cancel', 'exit' => 'cancel', 'quit' => 'cancel',
        'stop' => 'stop', 'unsubscribe' => 'stop',
        'register' => 'register', 'signup' => 'register',
        'login' => 'login', 'signin' => 'login',
        'link' => 'link',
        'forgot' => 'forgot',
        // Data shortcuts (routed to their menu action / flow):
        'order' => 'order', 'neworder' => 'order', 'buy' => 'order',
        'balance' => 'balance', 'wallet' => 'balance',
        'orders' => 'orders', 'myorders' => 'orders',
        'services' => 'services', 'browse' => 'services',
        'search' => 'search',
        'support' => 'support', 'ticket' => 'support',
        'tickets' => 'tickets',
        'deposit' => 'deposit', 'topup' => 'deposit', 'fund' => 'deposit',
        'track' => 'track',
        'profile' => 'profile', 'account' => 'profile',
        'settings' => 'settings',
        'history' => 'history', 'transactions' => 'history',
        'faq' => 'faq',
        'ai' => 'ask_ai', 'ask' => 'ask_ai',
    ];

    public function match(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }
        $key = preg_replace('/[^a-z?]/', '', mb_strtolower(trim($text)));
        if ($key === '') {
            return null;
        }

        return $this->map[$key] ?? null;
    }
}
