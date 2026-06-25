<?php

namespace App\Services\AI;

/**
 * Uses Gemini to generate SEO-friendly marketing copy for public pages.
 */
class SeoContentGenerator
{
    public function __construct(private readonly GeminiClient $client) {}

    public function isAvailable(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * Generate category page content.
     *
     * @param  array<int, array<string, mixed>>  $services
     * @return array<string, string>|null
     */
    public function generateCategoryDescription(string $category, array $services, ?string $angle = null): ?array
    {
        if (! $this->isAvailable() || $services === []) {
            return null;
        }

        $angle ??= 'general growth and engagement';
        $result = $this->client->generateJson($this->buildCategoryPrompt($category, $services, $angle), 0.4);

        return $this->normalizeContent($result);
    }

    /**
     * Generate FAQ entries.
     *
     * @param  array<int, array<string, mixed>>  $services
     * @return array<int, array{question: string, answer: string}>|null
     */
    public function generateFaqPage(array $services, int $count = 5): ?array
    {
        if (! $this->isAvailable() || $services === []) {
            return null;
        }

        $result = $this->client->generateJson($this->buildFaqPrompt($services, $count), 0.4);

        if (! is_array($result)) {
            return null;
        }

        $rows = array_is_list($result) ? $result : ($result['faqs'] ?? $result['questions'] ?? []);
        $faqs = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $question = trim((string) ($row['question'] ?? ''));
            $answer = trim((string) ($row['answer'] ?? ''));

            if ($question !== '' && $answer !== '') {
                $faqs[] = compact('question', 'answer');
            }
        }

        return $faqs ?: null;
    }

    /**
     * @param  array<string, mixed>|null  $result
     * @return array<string, string>|null
     */
    private function normalizeContent(?array $result): ?array
    {
        if (! is_array($result)) {
            return null;
        }

        $output = [];
        foreach (['headline', 'body', 'meta_title', 'meta_description'] as $field) {
            $output[$field] = $this->clean($result[$field] ?? '', $field === 'body' ? 1000 : 160);
        }

        if ($output['headline'] === '' || $output['body'] === '') {
            return null;
        }

        return $output;
    }

    private function clean(mixed $value, int $max): string
    {
        return mb_substr(trim((string) $value), 0, $max);
    }

    /**
     * @param  array<int, array<string, mixed>>  $services
     */
    private function buildCategoryPrompt(string $category, array $services, string $angle): string
    {
        $servicesJson = json_encode($services, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
            You write SEO copy for Zimbo Socials, a Zimbabwean SMM platform.
            Generate content for the {$category} services page.

            Angle: {$angle}

            Guidelines:
            - Headline should be punchy and under 70 characters.
            - Body should be 1-2 short paragraphs, under 300 words total.
            - Meta title under 60 characters.
            - Meta description under 160 characters.
            - Do not promise specific results, delivery speeds, or prices.
            - Return ONLY a JSON object with keys: headline, body, meta_title, meta_description.

            Services in this category:
            {$servicesJson}
            PROMPT;
    }

    /**
     * @param  array<int, array<string, mixed>>  $services
     */
    private function buildFaqPrompt(array $services, int $count): string
    {
        $servicesJson = json_encode($services, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
            You write FAQ content for Zimbo Socials, a Zimbabwean SMM platform.
            Generate {$count} common customer questions and clear answers based on the services below.

            Guidelines:
            - Questions should be practical (safety, ordering, deposits, delivery, refunds).
            - Answers must be honest; do not invent guarantees or policies.
            - Keep each answer under 120 words.
            - Return ONLY a JSON array of objects with keys: question, answer.

            Services:
            {$servicesJson}
            PROMPT;
    }
}
