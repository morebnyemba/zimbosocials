<?php

namespace App\Services\AI;

/**
 * Uses Gemini to turn admin dashboard statistics into a plain-English executive
 * summary.
 */
class AnalyticsSummarizer
{
    public function __construct(private readonly GeminiClient $client) {}

    public function isAvailable(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * Summarize platform activity.
     *
     * @param  array<string, mixed>  $stats
     * @param  array<int, array<string, mixed>>  $dailyRevenue
     * @param  array<string, int>|array<int, array<string, mixed>>  $ordersByStatus
     */
    public function summarize(array $stats, array $dailyRevenue, array $ordersByStatus, int $days = 7): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $result = $this->client->generateText($this->buildPrompt($stats, $dailyRevenue, $ordersByStatus, $days), 0.3);

        if (! is_string($result) || trim($result) === '') {
            return null;
        }

        return trim($result);
    }

    /**
     * @param  array<string, mixed>  $stats
     * @param  array<int, array<string, mixed>>  $dailyRevenue
     * @param  array<string, int>|array<int, array<string, mixed>>  $ordersByStatus
     */
    private function buildPrompt(array $stats, array $dailyRevenue, array $ordersByStatus, int $days): string
    {
        $context = json_encode([
            'period_days' => $days,
            'stats' => $stats,
            'daily_revenue' => $dailyRevenue,
            'orders_by_status' => $ordersByStatus,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
            You are the analytics assistant for Zimbo Socials, a Zimbabwean SMM platform.
            Write a concise executive summary for the admin team based on the last {$days} days of data.

            Guidelines:
            - Keep it under 150 words.
            - Highlight revenue trend, order health, and any anomalies.
            - Include one actionable recommendation.
            - Use plain English, no markdown headings.

            Data:
            {$context}

            Summary:
            PROMPT;
    }
}
