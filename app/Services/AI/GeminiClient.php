<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around the Google Gemini generateContent API.
 *
 * Every failure mode (missing key, HTTP error, malformed body) returns null so
 * callers can fall back gracefully — Gemini is always an optional enhancement.
 */
class GeminiClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.gemini.api_key'));
    }

    /**
     * Send a prompt and return the model's raw text reply (or null on failure).
     */
    public function generateText(string $prompt, float $temperature = 0.7): ?string
    {
        return $this->send($prompt, ['temperature' => $temperature]);
    }

    /**
     * Send a prompt and decode the model's JSON reply (or null on failure).
     *
     * @return array<mixed>|null
     */
    public function generateJson(string $prompt, float $temperature = 0.2): ?array
    {
        $text = $this->send($prompt, [
            'responseMimeType' => 'application/json',
            'temperature' => $temperature,
        ]);

        if (! is_string($text)) {
            return null;
        }

        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $generationConfig
     */
    private function send(string $prompt, array $generationConfig): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $model = config('services.gemini.model', 'gemini-2.5-flash');
        $baseUrl = rtrim((string) config('services.gemini.base_url'), '/');
        $endpoint = "{$baseUrl}/models/{$model}:generateContent";

        try {
            $response = Http::timeout((int) config('services.gemini.timeout', 30))
                ->withHeaders(['x-goog-api-key' => config('services.gemini.api_key')])
                ->asJson()
                ->post($endpoint, [
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [['text' => $prompt]],
                    ]],
                    'generationConfig' => $generationConfig,
                ]);

            if ($response->failed()) {
                Log::warning('Gemini request failed', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                return null;
            }

            $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

            if (! is_string($text) || trim($text) === '') {
                Log::warning('Gemini returned an empty response');

                return null;
            }

            return $text;
        } catch (\Throwable $e) {
            Log::warning('Gemini request threw an exception', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
