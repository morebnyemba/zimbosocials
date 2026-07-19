<?php

namespace App\Services\AI;

/**
 * Uses Gemini to turn raw upstream SMM service listings into clean, customer-ready
 * English names/descriptions plus Shona (sn) and Ndebele (nd) translations.
 *
 * Always degrades gracefully: if Gemini is unavailable or the reply can't be
 * parsed, enrich() returns an empty map and the importer keeps the raw values.
 */
class ServiceEnricher
{
    public function __construct(private readonly GeminiClient $client) {}

    public function isAvailable(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * @param  array<int, array<string, mixed>>  $services  Raw provider services (need 'service' + 'name', optional 'category').
     * @return array<string, array<string, string>> Enriched fields keyed by external_service_id (empty fields omitted).
     */
    public function enrich(array $services): array
    {
        if ($services === [] || ! $this->isAvailable()) {
            return [];
        }

        $input = collect($services)
            ->map(fn (array $s): array => [
                'external_service_id' => (string) ($s['service'] ?? ''),
                'name' => (string) ($s['name'] ?? ''),
                'category' => (string) ($s['category'] ?? ''),
            ])
            ->filter(fn (array $s): bool => $s['external_service_id'] !== '' && $s['name'] !== '')
            ->values();

        if ($input->isEmpty()) {
            return [];
        }

        $result = $this->client->generateJson($this->buildPrompt($input->all()));

        if (! is_array($result)) {
            return [];
        }

        // Accept either a bare JSON array or an object wrapping one.
        $rows = array_is_list($result)
            ? $result
            : ($result['services'] ?? $result['items'] ?? []);

        $map = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = (string) ($row['external_service_id'] ?? '');
            $name = $this->clean($row['name'] ?? '', 255);

            if ($id === '' || $name === '') {
                continue;
            }

            $entry = ['name' => $name];

            foreach (['name_sn' => 255, 'name_nd' => 255, 'description' => 1000, 'description_sn' => 1000, 'description_nd' => 1000] as $field => $max) {
                $value = $this->clean($row[$field] ?? '', $max);
                if ($value !== '') {
                    $entry[$field] = $value;
                }
            }

            $map[$id] = $entry;
        }

        return $map;
    }

    private function clean(mixed $value, int $max): string
    {
        return mb_substr(trim((string) $value), 0, $max);
    }

    /**
     * @param  array<int, array<string, string>>  $services
     */
    private function buildPrompt(array $services): string
    {
        $json = json_encode($services, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
            You localize social media marketing (SMM) service listings for a Zimbabwean platform.

            CRITICAL — THESE SERVICES MUST STAY TELLABLE APART. The input is a set of competing
            variants of the same thing (e.g. six kinds of "Facebook Followers") that differ by
            quality, refill/guarantee period, speed, drop rate or maximum. If you strip those
            details you destroy the catalogue: the customer sees six identical names at different
            prices and cannot choose. So:
            - KEEP the distinguishing attributes: quality tier (Real / HQ / Old Accounts),
              refill or guarantee period (e.g. 30-Day Refill), speed (Instant, Fast, 10K/day),
              drop rate (Low Drop) and any max.
            - REMOVE only the noise: emojis, ALL-CAPS shouting, provider codes/IDs, stray symbols
              and duplicated punctuation.
            - Shape: "<Platform> <Service> — <attribute>, <attribute>"
              e.g. "Instagram Followers — Old Accounts, 30-Day Refill, Fast".
            - EVERY name you return in this batch MUST BE UNIQUE. If two would come out the same,
              add whichever attribute separates them until they differ. Never output a duplicate name.

            For EACH input service, produce:
            - "name": a clean, customer-friendly English title following the rules above.
            - "description": one short, factual English sentence describing the service. Never invent guarantees, delivery speeds, or prices.
            - "name_sn" / "description_sn": a natural Shona translation.
            - "name_nd" / "description_nd": a natural Ndebele translation.
            Keep platform and brand names (Instagram, TikTok, YouTube, Facebook, X, Twitter, Telegram, WhatsApp) in English across every language.
            Return ONLY a JSON array — one object per input service — each with exactly these keys:
            external_service_id, name, description, name_sn, description_sn, name_nd, description_nd.
            Match each object to its input using external_service_id.

            Input services:
            {$json}
            PROMPT;
    }
}
