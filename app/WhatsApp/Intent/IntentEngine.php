<?php

namespace App\WhatsApp\Intent;

use App\WhatsApp\AI\AIGuard;
use App\WhatsApp\AI\GeminiProvider;

/**
 * Resolves free text that wasn't a command or active-flow input, in cost order:
 *   1. Knowledge base (deterministic, free)
 *   2. Gemini classification → a flow (with entities) or an AI answer
 *
 * Returns a normalized decision the router acts on:
 *   ['kind' => 'flow'|'kb'|'ai'|'none', 'reply' => ?string, 'flow' => ?string,
 *    'flow_data' => array]
 */
class IntentEngine
{
    /** Classifier intent → flow id. */
    private const INTENT_FLOW = [
        'order' => 'order', 'balance' => 'balance', 'my_orders' => 'my_orders',
        'track' => 'track', 'browse' => 'browse', 'deposit' => 'deposit',
        'history' => 'history', 'ticket' => 'ticket', 'tickets' => 'tickets',
        'profile' => 'profile',
    ];

    public function __construct(
        private readonly KnowledgeBase $kb,
        private readonly GeminiProvider $ai,
        private readonly AIGuard $guard,
    ) {}

    public function resolve(string $text, string $phone): array
    {
        // 1. Knowledge base.
        if ($hit = $this->kb->lookup($text)) {
            return ['kind' => 'kb', 'reply' => "💡 *{$hit['title']}*\n\n{$hit['answer']}", 'flow' => null, 'flow_data' => []];
        }

        // 2. AI (guarded).
        if (! $this->ai->isConfigured() || ! $this->guard->allow($phone)) {
            return ['kind' => 'none', 'reply' => null, 'flow' => null, 'flow_data' => []];
        }

        $this->guard->record($phone);
        $c = $this->ai->classify($text);

        if (! $c) {
            $answer = $this->ai->answer($text);

            return ['kind' => $answer ? 'ai' : 'none', 'reply' => $answer, 'flow' => null, 'flow_data' => []];
        }

        $flow = self::INTENT_FLOW[$c['intent']] ?? null;
        if ($flow !== null) {
            return [
                'kind' => 'flow',
                'flow' => $flow,
                'reply' => $c['reply'] ?: null,
                'flow_data' => $this->cleanEntities($c['entities']),
            ];
        }

        // question / faq / none → conversational answer.
        $reply = $c['reply'] ?: $this->ai->answer($text);

        return ['kind' => $reply ? 'ai' : 'none', 'reply' => $reply, 'flow' => null, 'flow_data' => []];
    }

    private function cleanEntities(array $entities): array
    {
        return array_filter([
            'order_id' => $entities['order_id'] ?? null,
            'quantity' => $entities['quantity'] ?? null,
            'service' => $entities['service'] ?? null,
            'amount' => $entities['amount'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
