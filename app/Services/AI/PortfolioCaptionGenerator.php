<?php

namespace App\Services\AI;

/**
 * Uses Gemini to generate a short portfolio caption/description in English,
 * Shona, and Ndebele for a creator's portfolio piece.
 */
class PortfolioCaptionGenerator
{
    public function __construct(private readonly GeminiClient $client) {}

    public function isAvailable(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * Generate a portfolio caption.
     *
     * @return array<string, string>|null
     */
    public function generate(string $title, string $platform, ?string $tone = null): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        if ($title === '' || $platform === '') {
            return null;
        }

        $result = $this->client->generateJson($this->buildPrompt($title, $platform, $tone), 0.5);

        if (! is_array($result)) {
            return null;
        }

        return $this->normalize($result);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, string>
     */
    private function normalize(array $result): array
    {
        $captionEn = trim((string) ($result['caption_en'] ?? ''));
        $captionSn = trim((string) ($result['caption_sn'] ?? ''));
        $captionNd = trim((string) ($result['caption_nd'] ?? ''));

        if ($captionSn === '') {
            $captionSn = $captionEn;
        }

        if ($captionNd === '') {
            $captionNd = $captionEn;
        }

        return [
            'caption_en' => $captionEn,
            'caption_sn' => $captionSn,
            'caption_nd' => $captionNd,
        ];
    }

    private function buildPrompt(string $title, string $platform, ?string $tone): string
    {
        $toneText = $tone ?: 'professional, authentic, and creator-friendly';

        return <<<PROMPT
            You are a copywriter for Zimbo Socials, a Zimbabwean social-media marketing (SMM) platform.
            Write a short, engaging portfolio description/caption for a creator's work.

            Portfolio title: {$title}
            Platform: {$platform}
            Tone: {$toneText}

            Guidelines:
            - Keep it under 160 characters.
            - Highlight the value or outcome of the work.
            - Do not invent facts, metrics, or client names.
            - Platform and brand names (Instagram, TikTok, YouTube, Facebook, X, Twitter, Telegram, WhatsApp) stay in English.
            - Return ONLY a JSON object with exactly these keys:
              caption_en, caption_sn, caption_nd.
            - Translations should be natural Zimbabwe Shona and Ndebele, not word-for-word.

            PROMPT;
    }
}
