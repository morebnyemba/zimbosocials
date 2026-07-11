<?php

namespace App\WhatsApp\Intent;

use App\WhatsApp\AI\AIGuard;
use App\WhatsApp\AI\GeminiProvider;

/**
 * Thin gate around the AI brain. Gemini is the sole resolver for free text; the
 * knowledge base is consulted only *inside* the provider as grounding context,
 * never as a separate answer path. When AI is unavailable or over its daily
 * budget, resolve() reports unhandled and the router falls back to the menu.
 */
class IntentEngine
{
    public function __construct(
        private readonly GeminiProvider $ai,
        private readonly AIGuard $guard,
    ) {}

    /**
     * @param  array{user:?\App\Models\User, authenticated:bool, current_flow:?string, history:array}  $context
     * @return array{handled:bool, reply?:string, flow?:?string, flow_data?:array}
     */
    public function resolve(string $text, string $phone, array $context): array
    {
        if (! $this->ai->isConfigured() || ! $this->guard->allow($phone)) {
            return ['handled' => false];
        }

        $this->guard->record($phone);
        $res = $this->ai->respond($text, $context);
        if (! $res) {
            return ['handled' => false];
        }

        return [
            'handled' => true,
            'reply' => $res['reply'],
            'flow' => $res['flow'],
            'flow_data' => $res['flow_data'],
        ];
    }
}
