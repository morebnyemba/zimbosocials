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
            For EACH input service, produce:
            - "name": a clean, concise, customer-friendly English title. Fix ALL-CAPS, remove provider junk, codes, and emojis, and keep it short.
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
