<?php

namespace App\WhatsApp\Routing;

/**
 * Recognises universal typed commands and data shortcuts. Returns a canonical
 * command id, or null when the text isn't a command (so it can fall through to
 * flows / intent / AI).
 */
class CommandRegistry
{
    /**
     * Conversation-control keywords that must act instantly and deterministically
     * — never routed through the AI (opt-out compliance, escape hatches, UI).
     */
    private const CONTROL = [
        'menu', 'home', 'start', 'help', '?', 'back', 'cancel', 'exit', 'quit', 'stop', 'unsubscribe',
    ];

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
        'advertise' => 'advertise', 'advert' => 'advertise', 'adverts' => 'advertise', 'ads' => 'advertise',
        'accounthelp' => 'account_help', 'unban' => 'account_help', 'unbanning' => 'account_help',
        'track' => 'track',
        'profile' => 'profile', 'account' => 'profile',
        'settings' => 'settings',
        'history' => 'history', 'transactions' => 'history',
        'faq' => 'faq',
        'ai' => 'ask_ai', 'ask' => 'ask_ai',
        'referral' => 'referral', 'referrals' => 'referral', 'refer' => 'referral', 'invite' => 'referral', 'earn' => 'referral',
    ];

    public function match(?string $text): ?string
    {
        $key = $this->normalize($text);

        return $key !== null ? ($this->map[$key] ?? null) : null;
    }

    /**
     * Loose phrase matching for "balance" and "track", so a natural phrasing
     * ("show me my balance", "where is my order 1231?") is recognised the
     * same as the bare keyword — the AI still gets first crack at these (see
     * MessageRouter), but recognising the phrase means there's a correct
     * deterministic fallback if the AI is unavailable, and order_id is
     * extracted either way so the flow can jump straight to the named order
     * instead of asking again. Returns null when nothing matches.
     *
     * @return array{command:string, order_id:?int}|null
     */
    public function matchPhrase(string $text): ?array
    {
        $t = mb_strtolower(trim($text));
        if ($t === '') {
            return null;
        }

        if (preg_match('/\border\b.{0,15}?(\d+)/u', $t, $m)
            || preg_match('/(\d+).{0,15}?\border\b/u', $t, $m)
        ) {
            return ['command' => 'track', 'order_id' => (int) $m[1]];
        }

        if (preg_match('/\btrack\b|where.{0,12}(is|are).{0,12}order|order.{0,12}status/u', $t)) {
            return ['command' => 'track', 'order_id' => null];
        }

        if (preg_match('/\b(balance|wallet)\b/u', $t)) {
            return ['command' => 'balance', 'order_id' => null];
        }

        return null;
    }

    /**
     * Whether the typed keyword is a conversation control (instant, no AI).
     * Data shortcuts like "balance" or greetings like "hi" return false — the
     * router gives the AI first crack at those, falling back to the mapped
     * command when AI is unavailable.
     */
    public function isControl(?string $text): bool
    {
        $key = $this->normalize($text);

        return $key !== null && in_array($key, self::CONTROL, true);
    }

    private function normalize(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }
        $key = preg_replace('/[^a-z?]/', '', mb_strtolower(trim($text)));

        return $key === '' ? null : $key;
    }
}
