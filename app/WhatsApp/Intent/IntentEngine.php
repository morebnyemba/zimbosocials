<?php

namespace App\WhatsApp\Intent;

use App\WhatsApp\AI\AIGuard;
use App\WhatsApp\AI\GeminiProvider;

/**
 * AI-primary orchestration for free text. Gemini plans the action (start any
 * flow with extracted params, answer, or run a command). The knowledge base is
 * a deterministic fallback when AI is unavailable or over its daily budget.
 *
 * Returns a normalized decision the router acts on:
 *   ['kind' => 'flow'|'command'|'kb'|'ai'|'none',
 *    'reply' => ?string, 'flow' => ?string, 'command' => ?string, 'flow_data' => array]
 */
class IntentEngine
{
    public function __construct(
        private readonly KnowledgeBase $kb,
        private readonly GeminiProvider $ai,
        private readonly AIGuard $guard,
    ) {}

    /**
     * @param  array{authenticated:bool, current_flow:?string}  $context
     */
    public function resolve(string $text, string $phone, array $context): array
    {
        // 1. AI is the primary brain.
        if ($this->ai->isConfigured() && $this->guard->allow($phone)) {
            $this->guard->record($phone);
            $plan = $this->ai->plan($text, $context);
            if ($plan) {
                return $this->fromPlan($plan, $text);
            }
        }

        // 2. Deterministic fallback: knowledge base.
        if ($hit = $this->kb->lookup($text)) {
            return $this->decision('kb', reply: "💡 *{$hit['title']}*\n\n{$hit['answer']}");
        }

        // 3. Last resort: a plain AI answer if we still have budget.
        if ($this->ai->isConfigured() && $this->guard->allow($phone)) {
            $answer = $this->ai->answer($text);
            if ($answer) {
                return $this->decision('ai', reply: $answer);
            }
        }

        return $this->decision('none');
    }

    private function fromPlan(array $plan, string $text): array
    {
        return match ($plan['action']) {
            'command' => $this->decision('command', reply: $plan['reply'], command: $plan['command'] ?: 'menu'),
            'flow' => $plan['flow']
                ? $this->decision('flow', reply: $plan['reply'], flow: $plan['flow'], data: $this->cleanEntities($plan['entities']))
                : $this->answerFallback($plan, $text),
            default => $this->answerFallback($plan, $text),
        };
    }

    private function answerFallback(array $plan, string $text): array
    {
        $reply = $plan['reply'] ?: $this->ai->answer($text);

        return $this->decision($reply ? 'ai' : 'none', reply: $reply);
    }

    private function decision(string $kind, ?string $reply = null, ?string $flow = null, ?string $command = null, array $data = []): array
    {
        return ['kind' => $kind, 'reply' => $reply, 'flow' => $flow, 'command' => $command, 'flow_data' => $data];
    }

    private function cleanEntities(array $entities): array
    {
        $allowed = ['order_id', 'quantity', 'service', 'platform', 'link', 'amount', 'email', 'name', 'subject', 'message'];

        return array_filter(
            array_intersect_key($entities, array_flip($allowed)),
            fn ($v) => $v !== null && $v !== ''
        );
    }
}
