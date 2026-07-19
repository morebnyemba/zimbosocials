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
    /** HTTP status of the most recent failed request (schema-rejection detection). */
    private ?int $lastStatus = null;

    public function isConfigured(): bool
    {
        return filled(config('services.gemini.api_key'));
    }

    /**
     * Send a prompt and return the model's raw text reply (or null on failure).
     */
    public function generateText(string $prompt, float $temperature = 0.7, ?string $system = null, ?int $timeout = null): ?string
    {
        return $this->send($prompt, ['temperature' => $temperature], $system, $timeout);
    }

    /**
     * Send a prompt and decode the model's JSON reply (or null on failure).
     *
     * @param  array|null  $schema  Optional Gemini responseSchema — constrains the
     *                              output shape server-side (enums, types), which
     *                              eliminates malformed decisions at the source.
     * @param  string|null  $system  Optional system instruction, sent as its own
     *                               field so user text stays a plain data turn.
     * @param  int|null  $timeout  Per-call timeout override (seconds).
     * @return array<mixed>|null
     */
    public function generateJson(string $prompt, float $temperature = 0.2, ?array $schema = null, ?string $system = null, ?int $timeout = null, array $media = []): ?array
    {
        $config = ['responseMimeType' => 'application/json', 'temperature' => $temperature];
        if ($schema !== null) {
            $config['responseSchema'] = $schema;
        }

        // One retry: a transient malformed body is common enough to be worth
        // a single second attempt before giving up to the deterministic path.
        foreach ([1, 2] as $attempt) {
            $text = $this->send($prompt, $config, $system, $timeout, $media);

            // Safety net: if the API rejects the schema itself (endpoint/version
            // quirks), degrade to a schema-less JSON call instead of turning
            // every AI request into a hard failure.
            if (! is_string($text) && isset($config['responseSchema']) && $this->lastStatus === 400) {
                Log::warning('Gemini rejected responseSchema — retrying without it');
                unset($config['responseSchema']);
                $text = $this->send($prompt, $config, $system, $timeout, $media);
            }

            if (! is_string($text)) {
                return null; // transport-level failure — already logged, no retry
            }

            $decoded = json_decode($text, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            Log::warning("Gemini returned non-JSON despite JSON mime type (attempt {$attempt})", ['text' => mb_substr($text, 0, 300)]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $generationConfig
     * @param  array<int, array{mime:string, data:string}>  $media  Raw bytes to send
     *                                    alongside the prompt (images, audio, PDF).
     *                                    Sent inline, so keep the total well under
     *                                    the ~20MB request cap — WhatsApp media is.
     */
    private function send(string $prompt, array $generationConfig, ?string $system = null, ?int $timeout = null, array $media = []): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $model = config('services.gemini.model', 'gemini-2.5-flash');
        $baseUrl = rtrim((string) config('services.gemini.base_url'), '/');
        $endpoint = "{$baseUrl}/models/{$model}:generateContent";

        // Media first, then the instruction — Gemini follows a prompt that comes
        // after the thing it's being asked about more reliably.
        $parts = [];
        foreach ($media as $item) {
            if (! isset($item['mime'], $item['data']) || $item['data'] === '') {
                continue;
            }
            $parts[] = ['inline_data' => [
                'mime_type' => $item['mime'],
                'data' => base64_encode($item['data']),
            ]];
        }
        $parts[] = ['text' => $prompt];

        $payload = [
            'contents' => [[
                'role' => 'user',
                'parts' => $parts,
            ]],
            'generationConfig' => $generationConfig,
        ];
        if ($system !== null) {
            $payload['systemInstruction'] = ['parts' => [['text' => $system]]];
        }

        try {
            $response = Http::timeout($timeout ?? (int) config('services.gemini.timeout', 30))
                ->withHeaders(['x-goog-api-key' => config('services.gemini.api_key')])
                ->asJson()
                ->post($endpoint, $payload);

            $this->lastStatus = $response->status();

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
