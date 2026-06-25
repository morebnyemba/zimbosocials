<?php

namespace App\Services\AI;

use App\Models\Service;
use App\Models\User;

/**
 * Uses Gemini to pick relevant services for a customer based on their order
 * history and the active catalog.
 */
class ServiceRecommendationEngine
{
    public function __construct(private readonly GeminiClient $client) {}

    public function isAvailable(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * Recommend service IDs for the given user.
     *
     * @return array<int, array{id: int, reason: string}>
     */
    public function recommendFor(User $user, int $limit = 4): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        $history = $user->orders()
            ->with('service:id,name,category')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($order) => [
                'service' => $order->service?->name ?? 'Unknown',
                'category' => $order->service?->category ?? '',
                'status' => $order->status,
                'quantity' => $order->quantity,
            ])
            ->toArray();

        $catalog = Service::active()
            ->select(['id', 'name', 'category'])
            ->orderBy('category')
            ->orderBy('display_order')
            ->get()
            ->map(fn ($service) => [
                'id' => $service->id,
                'name' => $service->name,
                'category' => $service->category,
            ])
            ->toArray();

        if ($catalog === []) {
            return [];
        }

        $result = $this->client->generateJson($this->buildPrompt($history, $catalog, $limit), 0.3);

        if (! is_array($result)) {
            return [];
        }

        $rows = array_is_list($result) ? $result : ($result['recommendations'] ?? $result['services'] ?? []);

        $map = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $map[$id] = [
                'id' => $id,
                'reason' => $this->clean($row['reason'] ?? '', 160),
            ];
        }

        // Ensure all IDs exist in the active catalog.
        $validIds = Service::active()->whereIn('id', array_keys($map))->pluck('id')->toArray();

        $recommendations = [];
        foreach ($validIds as $id) {
            if (isset($map[$id])) {
                $recommendations[] = $map[$id];
            }
            if (count($recommendations) >= $limit) {
                break;
            }
        }

        return $recommendations;
    }

    private function clean(mixed $value, int $max): string
    {
        return mb_substr(trim((string) $value), 0, $max);
    }

    /**
     * @param  array<int, array<string, mixed>>  $history
     * @param  array<int, array<string, mixed>>  $catalog
     */
    private function buildPrompt(array $history, array $catalog, int $limit): string
    {
        $historyJson = json_encode($history, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $catalogJson = json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
            You recommend social-media marketing services for a Zimbabwean SMM platform called Zimbo Socials.
            Given the customer's recent order history and the active service catalog, pick the {$limit} most relevant services to recommend next.

            Guidelines:
            - Prefer services in categories the customer has already ordered.
            - Suggest complementary services (e.g. views after followers, comments after likes).
            - Do not recommend services the customer already ordered recently unless there is a strong reason.
            - Return ONLY a JSON array. Each object must have exactly:
              id (int), reason (short sentence, max 20 words).

            Recent order history:
            {$historyJson}

            Active catalog:
            {$catalogJson}
            PROMPT;
    }
}
