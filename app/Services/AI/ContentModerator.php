<?php

namespace App\Services\AI;

use App\Models\ContractProofSubmission;
use App\Models\MarketerPortfolio;
use App\Models\MarketerReview;

/**
 * Uses Gemini to flag potentially inappropriate marketer-generated content.
 * Results are advisory; a human admin makes the final decision.
 */
class ContentModerator
{
    public function __construct(private readonly GeminiClient $client) {}

    public function isAvailable(): bool
    {
        return $this->client->isConfigured();
    }

    public function reviewPortfolio(MarketerPortfolio $item): ?array
    {
        $content = implode("\n", array_filter([
            "Title: {$item->title}",
            "Platform: {$item->platform}",
            'Description: '.($item->description ?? ''),
            "URL: {$item->url}",
        ]));

        return $this->moderate($content, 'portfolio');
    }

    public function reviewProof(ContractProofSubmission $proof): ?array
    {
        $content = implode("\n", array_filter([
            "Proof URL: {$proof->proof_url}",
            'Notes: '.($proof->notes ?? ''),
        ]));

        return $this->moderate($content, 'proof submission');
    }

    public function reviewReview(MarketerReview $review): ?array
    {
        $content = implode("\n", array_filter([
            "Rating: {$review->rating}/5",
            'Comment: '.($review->comment ?? ''),
        ]));

        return $this->moderate($content, 'review');
    }

    private function moderate(string $content, string $contentType): ?array
    {
        if (! $this->isAvailable() || trim($content) === '') {
            return null;
        }

        $result = $this->client->generateJson($this->buildPrompt($content, $contentType), 0.2);

        if (! is_array($result)) {
            return null;
        }

        $flagged = filter_var($result['flagged'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $reason = trim((string) ($result['reason'] ?? ''));
        $severity = in_array($result['severity'] ?? '', ['low', 'medium', 'high'], true)
            ? $result['severity']
            : 'low';

        if (! $flagged || $reason === '') {
            return null;
        }

        return compact('flagged', 'reason', 'severity');
    }

    private function buildPrompt(string $content, string $contentType): string
    {
        return <<<PROMPT
            You are a content moderator for Zimbo Socials, a Zimbabwean SMM platform.
            Review the following {$contentType} and decide if it violates platform standards.

            Flag if it contains:
            - Hate speech, harassment, or discrimination
            - Adult content, violence, or illegal activity
            - Misleading claims or scams
            - Excessive spam or self-promotion
            - Personally identifying information of others

            Return ONLY a JSON object with exactly these keys:
            flagged (boolean), reason (short sentence), severity (low|medium|high).

            Content:
            {$content}
            PROMPT;
    }
}
