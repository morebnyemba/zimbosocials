<?php

namespace App\Services\AI;

/**
 * Uses Gemini to generate marketing campaign copy from a brief, including
 * English, Shona, and Ndebele variants.
 */
class MarketingCopyGenerator
{
    public function __construct(private readonly GeminiClient $client) {}

    public function isAvailable(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * Generate campaign copy.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string>|null
     */
    public function generate(array $input): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $brief = (string) ($input['brief'] ?? '');
        $channels = (array) ($input['channels'] ?? ['email']);
        $tone = (string) ($input['tone'] ?? 'promotional, energetic, trustworthy');

        if ($brief === '') {
            return null;
        }

        $result = $this->client->generateJson($this->buildPrompt($brief, $channels, $tone), 0.4);

        if (! is_array($result)) {
            return null;
        }

        return $this->normalize($result, $brief);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, string>
     */
    private function normalize(array $result, string $fallbackName): array
    {
        $fields = [
            'campaign_name',
            'subject_en',
            'body_en',
            'subject_sn',
            'body_sn',
            'subject_nd',
            'body_nd',
        ];

        $output = [];

        foreach ($fields as $field) {
            $value = trim((string) ($result[$field] ?? ''));
            $output[$field] = $value;
        }

        if ($output['campaign_name'] === '') {
            $output['campaign_name'] = $fallbackName;
        }

        // Fall back to English when local variants are empty.
        foreach (['sn', 'nd'] as $locale) {
            if ($output["subject_{$locale}"] === '') {
                $output["subject_{$locale}"] = $output['subject_en'];
            }
            if ($output["body_{$locale}"] === '') {
                $output["body_{$locale}"] = $output['body_en'];
            }
        }

        return $output;
    }

    /**
     * @param  array<int, string>  $channels
     */
    private function buildPrompt(string $brief, array $channels, string $tone): string
    {
        $channelsJson = json_encode(array_values($channels), JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
            You are a marketing copywriter for Zimbo Socials, a Zimbabwean social-media marketing (SMM) platform.
            Write a broadcast campaign based on the brief below.

            Tone: {$tone}
            Channels: {$channelsJson}

            Guidelines:
            - Keep subject lines under 60 characters.
            - If WhatsApp is included, keep the body short (under 300 characters).
            - If email/in_app are included, the body can be up to 600 characters.
            - Never invent discounts, guarantees, or deadlines unless they are in the brief.
            - Platform and brand names (Instagram, TikTok, YouTube, Facebook, X, Twitter, Telegram, WhatsApp) stay in English.
            - Return ONLY a JSON object with exactly these keys:
              campaign_name, subject_en, body_en, subject_sn, body_sn, subject_nd, body_nd.
            - Translations should be natural Zimbabwe Shona and Ndebele, not word-for-word.

            Brief:
            {$brief}
            PROMPT;
    }
}
