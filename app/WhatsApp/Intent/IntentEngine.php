<?php

namespace App\WhatsApp\Intent;

use App\WhatsApp\AI\AIGuard;
use App\WhatsApp\AI\GeminiProvider;
use Illuminate\Support\Facades\Log;

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
    public function resolve(string $text, string $phone, array $context, array $media = []): array
    {
        if (! $this->ai->isConfigured()) {
            return ['handled' => false, 'reason' => 'not_configured'];
        }

        if (! $this->guard->allow($phone)) {
            Log::info('WhatsApp AI skipped: daily limit reached', ['phone' => $phone]);

            return ['handled' => false, 'reason' => 'over_daily_limit'];
        }

        // An AI failure must degrade to the menu, never break the conversation —
        // and it shouldn't count against the user's daily AI budget either.
        try {
            $res = $this->ai->respond($text, $context, $media);
        } catch (\Throwable $e) {
            Log::error('WhatsApp AI resolve failed', ['message' => $e->getMessage()]);

            return ['handled' => false, 'reason' => 'error'];
        }

        if (! $res) {
            return ['handled' => false, 'reason' => 'empty_response'];
        }

        $this->guard->record($phone);

        return [
            'handled' => true,
            'reply' => $res['reply'],
            'follow_up' => $res['follow_up'] ?? null,
            'flow' => $res['flow'],
            'flow_data' => $res['flow_data'],
            'prompt_version' => $res['prompt_version'] ?? null,
        ];
    }

    /**
     * "One voice": fuse the AI's draft reply with a flow step's scripted prompt
     * into a single message. Null → caller sends the scripted text (the safe
     * fallback). Deliberately NOT budget-gated: it belongs to a resolve() that
     * already passed the gate — one user message costs one budget unit even
     * though it may use two model calls.
     */
    public function voice(string $draft, string $scripted, string $userMessage): ?string
    {
        if (! $this->ai->isConfigured()) {
            return null;
        }

        try {
            return $this->ai->voiceStep($draft, $scripted, $userMessage);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp AI voice pass failed', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
