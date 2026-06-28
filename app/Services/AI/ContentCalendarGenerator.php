<?php

namespace App\Services\AI;

/**
 * Uses Gemini to generate a 7-day social-media content calendar for creators.
 * Output includes a theme and captions in English, Shona, and Ndebele.
 */
class ContentCalendarGenerator
{
    public function __construct(private readonly GeminiClient $client) {}

    public function isAvailable(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * Generate a 7-day content calendar.
     *
     * @return array<string, mixed>|null
     */
    public function generate(string $brief, ?string $platform = null, ?string $tone = null): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        if ($brief === '') {
            return null;
        }

        $result = $this->client->generateJson($this->buildPrompt($brief, $platform, $tone), 0.5);

        if (! is_array($result)) {
            return null;
        }

        return $this->normalize($result);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function normalize(array $result): array
    {
        $days = [];

        foreach (($result['days'] ?? []) as $index => $day) {
            if (! is_array($day)) {
                continue;
            }

            $dayNumber = (int) ($day['day'] ?? ($index + 1));
            $theme = trim((string) ($day['theme'] ?? ''));
            $captionEn = trim((string) ($day['caption_en'] ?? ''));
            $captionSn = trim((string) ($day['caption_sn'] ?? ''));
            $captionNd = trim((string) ($day['caption_nd'] ?? ''));
            $hashtags = $this->normalizeHashtags($day['hashtags'] ?? []);

            if ($captionEn === '') {
                continue;
            }

            if ($captionSn === '') {
                $captionSn = $captionEn;
            }

            if ($captionNd === '') {
                $captionNd = $captionEn;
            }

            $days[] = [
                'day' => $dayNumber,
                'theme' => $theme !== '' ? $theme : "Day {$dayNumber}",
                'caption_en' => $captionEn,
                'caption_sn' => $captionSn,
                'caption_nd' => $captionNd,
                'hashtags' => $hashtags,
            ];
        }

        if ($days === []) {
            return null;
        }

        return [
            'platform' => trim((string) ($result['platform'] ?? '')) ?: ($platform ?? 'social media'),
            'days' => $days,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function normalizeHashtags(mixed $hashtags): array
    {
        if (is_string($hashtags)) {
            $hashtags = array_filter(array_map('trim', explode(',', $hashtags)));
        }

        if (! is_array($hashtags)) {
            return [];
        }

        $clean = [];
        foreach ($hashtags as $tag) {
            $tag = ltrim(trim((string) $tag), '#');
            if ($tag !== '') {
                $clean[] = $tag;
            }
        }

        return array_values(array_unique($clean));
    }

    private function buildPrompt(string $brief, ?string $platform, ?string $tone): string
    {
        $platformText = $platform ? "Primary platform: {$platform}" : 'Primary platform: general social media';
        $toneText = $tone ?: 'engaging, authentic, and creator-friendly';

        return <<<PROMPT
            You are a social-media strategist for Zimbo Socials, a Zimbabwean SMM platform.
            Create a 7-day content calendar for a creator who wants to promote their services and grow their audience.

            {$platformText}
            Tone: {$toneText}

            Guidelines:
            - Each day should have a clear theme and a ready-to-post caption.
            - Captions must feel natural, not overly salesy.
            - Do not invent discounts, guarantees, or deadlines unless they are in the brief.
            - Platform and brand names (Instagram, TikTok, YouTube, Facebook, X, Twitter, Telegram, WhatsApp) stay in English.
            - Return ONLY a JSON object with exactly this shape:
              {
                "platform": "string",
                "days": [
                  {
                    "day": 1,
                    "theme": "string",
                    "caption_en": "string",
                    "caption_sn": "string",
                    "caption_nd": "string",
                    "hashtags": ["tag1", "tag2", "tag3"]
                  }
                ]
              }
            - Provide exactly 7 days.
            - Translations should be natural Zimbabwe Shona and Ndebele, not word-for-word.
            - Keep each caption under 220 characters so it works on most platforms.

            Brief:
            {$brief}
            PROMPT;
    }
}
