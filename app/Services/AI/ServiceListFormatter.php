<?php

namespace App\Services\AI;

/**
 * Restyles an already-correct, mechanically-generated service list for a
 * target platform (WhatsApp, Telegram, Twitter/X, Instagram, Facebook, ...).
 *
 * Deliberately never asked to source prices itself — it's handed the exact
 * name/price/minimum text as the source of truth and instructed to only
 * change formatting, tone, and structure. This keeps financial figures out
 * of Gemini's hands while still getting platform-appropriate styling.
 */
class ServiceListFormatter
{
    public function __construct(private GeminiClient $client)
    {
    }

    public function isAvailable(): bool
    {
        return $this->client->isConfigured();
    }

    public function format(string $rawList, string $platform, ?string $extraInstructions = null): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $extra = $extraInstructions ? "\n- {$extraInstructions}" : '';

        $prompt = <<<PROMPT
You are reformatting a service price list so it can be shared on {$platform}.

STRICT RULES:
- Do NOT invent, omit, reorder within a category, or alter any service name, price, minimum order value, percentage, or link. Copy every name, number, percentage, and URL EXACTLY as given below, character for character.
- You may only change formatting, section headers, emojis, line breaks, a short intro line, and a short closing call-to-action — nothing that touches the actual data.
- Match the formatting conventions and tone typical of {$platform} (e.g. WhatsApp: bold with single *asterisks*, short lines, emojis; Telegram: channel-post style; Twitter/X: compact, punchy, thread-friendly; Instagram: caption style, can include hashtags at the end; Facebook: friendly post style).{$extra}

Raw source text (source of truth — do not change any values in here):
{$rawList}

Return ONLY the final formatted text, ready to paste directly into {$platform}. No explanations, no markdown code fences, no commentary.
PROMPT;

        return $this->client->generateText($prompt, 0.4);
    }
}
