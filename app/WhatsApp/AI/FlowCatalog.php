<?php

namespace App\WhatsApp\AI;

/**
 * Describes the flows the AI orchestrator may invoke, and the entities each can
 * consume to fast-forward through its steps. This is the "tool schema" handed
 * to Gemini so it can call any part of any flow with the data it extracted.
 *
 * Editing a flow's capabilities is a one-line change here — flows read the
 * matching _prefill_* keys the router injects.
 */
class FlowCatalog
{
    /** @return array<string, array{desc:string, params:array<string>}> */
    public static function all(): array
    {
        return [
            'order' => ['desc' => 'Place a new order (buy followers/likes/views/etc.)', 'params' => ['platform', 'service', 'quantity', 'link']],
            'balance' => ['desc' => 'Show the wallet balance', 'params' => []],
            'deposit' => ['desc' => 'Add funds to the wallet (method: ecocash|onemoney|innbucks|omari; phone: the mobile-money number)', 'params' => ['amount', 'method', 'phone']],
            'history' => ['desc' => 'Show recent wallet transactions', 'params' => []],
            'my_orders' => ['desc' => 'List the user\'s recent orders', 'params' => []],
            'track' => ['desc' => 'Track a specific order by number', 'params' => ['order_id']],
            'browse' => ['desc' => 'Browse available services', 'params' => ['platform']],
            'advertise' => ['desc' => 'Book and PAY for a sponsored advert campaign we run on Facebook/Instagram (weekly packages; package: starter|standard|max; weeks: how long it runs; promoting: what the advert is for)', 'params' => ['package', 'weeks', 'promoting', 'link']],
            'ticket' => ['desc' => 'Open a support ticket', 'params' => ['subject', 'message']],
            'tickets' => ['desc' => 'View support tickets, read the team\'s responses, and reply to a ticket', 'params' => ['ticket_id']],
            'profile' => ['desc' => 'Show account/profile details', 'params' => []],
            'settings' => ['desc' => 'View or change notification settings', 'params' => []],
            'register' => ['desc' => 'Create a new account (guests only)', 'params' => ['name', 'email']],
            'link' => ['desc' => 'Link this number to an existing account via email code', 'params' => ['email']],
            'forgot' => ['desc' => 'Send a password reset email', 'params' => ['email']],
            'faq' => ['desc' => 'Show frequently asked questions', 'params' => []],
            'referral' => ['desc' => 'Show the user\'s referral link and the invite-a-friend rewards (earn money by inviting friends)', 'params' => []],
        ];
    }

    /** Flows any user (including guests) may start. */
    public static function guestFlows(): array
    {
        return ['register', 'link', 'forgot', 'faq'];
    }

    /** Compact one-line-per-flow description for the planner prompt. */
    public static function prompt(): string
    {
        $lines = [];
        foreach (self::all() as $id => $meta) {
            $params = $meta['params'] ? ' (params: '.implode(', ', $meta['params']).')' : '';
            $lines[] = "- {$id}: {$meta['desc']}{$params}";
        }

        return implode("\n", $lines);
    }
}
