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
    public function generateText(string $prompt, float $temperature = 0.7, ?string $system = null, ?int $timeout = null, bool $light = false): ?string
    {
        return $this->send($prompt, $this->baseConfig($temperature, json: false), $system, $timeout, light: $light);
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
    public function generateJson(string $prompt, float $temperature = 0.2, ?array $schema = null, ?string $system = null, ?int $timeout = null, array $media = [], bool $light = false): ?array
    {
        $config = array_merge($this->baseConfig($temperature, json: true), ['responseMimeType' => 'application/json']);
        if ($schema !== null) {
            $config['responseSchema'] = $schema;
        }

        // One retry: a transient malformed body is common enough to be worth
        // a single second attempt before giving up to the deterministic path.
        //
        // Hard-bounded at two BILLED requests. The schema-less degrade below
        // used to sit inside this loop with its own send(), so a run of 400s
        // could quietly cost four full prompt-sized requests for one inbound
        // message. It now fires at most once, and consumes the retry it uses.
        $sends = 0;

        foreach ([1, 2] as $attempt) {
            if ($sends >= 2) {
                break;
            }

            $text = $this->send($prompt, $config, $system, $timeout, $media, $light);
            $sends++;

            // Safety net: if the API rejects the schema itself (endpoint/version
            // quirks), degrade to a schema-less JSON call instead of turning
            // every AI request into a hard failure.
            if (! is_string($text) && isset($config['responseSchema']) && $this->lastStatus === 400 && $sends < 2) {
                Log::warning('Gemini rejected responseSchema — retrying without it');
                unset($config['responseSchema']);
                $text = $this->send($prompt, $config, $system, $timeout, $media, $light);
                $sends++;
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
     * Both plain-text and JSON calls are uncapped by default (0 = omit
     * maxOutputTokens entirely): a fixed cap fights whatever length the
     * prompt/schema actually calls for. This bit the JSON path hardest — cut
     * off mid-string, a truncated structured reply isn't a shorter answer,
     * it's not valid JSON at all, so a too-tight shared cap failed the whole
     * request rather than shortening it (confirmed live — accuracy dropped
     * from ~93% to 78% the one time this happened).
     *
     * The JSON call runs on every inbound message — by far the busiest path
     * in the system — so its own cap (GEMINI_MAX_OUTPUT_TOKENS_JSON) is the
     * one to reach for first if a genuinely runaway/repetitive response is
     * ever seen there; GEMINI_MAX_OUTPUT_TOKENS covers the lower-volume
     * plain-text calls (nudges, dashboard summary, marketing copy, etc.).
     */
    private function baseConfig(float $temperature, bool $json): array
    {
        $config = ['temperature' => $temperature];
        $key = $json ? 'services.gemini.max_output_tokens_json' : 'services.gemini.max_output_tokens';

        $limit = (int) config($key, 0);
        if ($limit > 0) {
            $config['maxOutputTokens'] = $limit;
        }

        // Thinking tokens bill at the OUTPUT rate and 2.5 Flash decides the
        // budget itself unless told otherwise, so an unset thinkingConfig is
        // not "the default" — it is an open tab. -1 asks for the dynamic
        // behaviour explicitly; anything >= 0 is sent as a hard budget, with 0
        // meaning no thinking at all. See config/services.php for the
        // reasoning and for what to do if the eval regresses.
        $budget = (int) config(
            $json ? 'services.gemini.thinking_budget' : 'services.gemini.thinking_budget_text',
            0
        );
        $config['thinkingConfig'] = ['thinkingBudget' => max(-1, $budget)];

        return $config;
    }

    /**
     * @param  array<string, mixed>  $generationConfig
     * @param  array<int, array{mime:string, data:string}>  $media  Raw bytes to send
     *                                    alongside the prompt (images, audio, PDF).
     *                                    Sent inline, so keep the total well under
     *                                    the ~20MB request cap — WhatsApp media is.
     */
    private function send(string $prompt, array $generationConfig, ?string $system = null, ?int $timeout = null, array $media = [], bool $light = false): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        // Calls that only write prose from facts they were handed go to the
        // cheap model; anything making a flow, money or grounding decision
        // stays on the flagship. Falls back to the main model if no light
        // model is configured, so the split can be switched off in one place.
        $model = $light
            ? (string) (config('services.gemini.model_light') ?: config('services.gemini.model', 'gemini-2.5-flash'))
            : (string) config('services.gemini.model', 'gemini-2.5-flash');

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

        // The system prompt is thousands of tokens and identical on every
        // message. Where Google will cache it for us, reference the cache
        // instead of resending it — cached input is billed at a fraction of
        // fresh input. The two are mutually exclusive: the cache HOLDS the
        // system instruction, so sending both is an error.
        $cacheName = $system !== null
            ? app(GeminiPromptCache::class)->nameFor($system, $model)
            : null;

        if ($cacheName !== null) {
            $payload['cachedContent'] = $cacheName;
        } elseif ($system !== null) {
            $payload['systemInstruction'] = ['parts' => [['text' => $system]]];
        }

        try {
            $response = Http::timeout($timeout ?? (int) config('services.gemini.timeout', 30))
                ->withHeaders(['x-goog-api-key' => config('services.gemini.api_key')])
                ->asJson()
                ->post($endpoint, $payload);

            $this->lastStatus = $response->status();

            if ($response->failed()) {
                // A cache that expired between our TTL and Google's leaves the
                // name dangling. Drop it and retry once WITHOUT the cache, so a
                // caching problem never costs the customer their reply.
                if ($cacheName !== null && in_array($response->status(), [400, 403, 404], true)) {
                    app(GeminiPromptCache::class)->forget((string) $system, $model);
                    Log::info('Gemini prompt cache rejected — retrying inline', ['status' => $response->status()]);

                    unset($payload['cachedContent']);
                    $payload['systemInstruction'] = ['parts' => [['text' => $system]]];

                    $response = Http::timeout($timeout ?? (int) config('services.gemini.timeout', 30))
                        ->withHeaders(['x-goog-api-key' => config('services.gemini.api_key')])
                        ->asJson()
                        ->post($endpoint, $payload);
                    $this->lastStatus = $response->status();
                }
            }

            if ($response->failed()) {
                Log::warning('Gemini request failed', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                return null;
            }

            $this->recordUsage($response->json(), $model);

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

    /**
     * Bank what this request cost. cachedContentTokenCount is the slice of the
     * prompt served from cache — if it stays at zero, the caching is not doing
     * anything and the dashboard will show that plainly.
     *
     * thoughtsTokenCount is reported SEPARATELY from candidatesTokenCount and
     * is billed at the same output rate, so leaving it out did not make the
     * thinking free — it only hid it. On the chat path it was the largest
     * single component of a turn while the dashboard showed the reply alone.
     */
    private function recordUsage(mixed $body, string $model): void
    {
        $usage = data_get($body, 'usageMetadata');
        if (! is_array($usage)) {
            return;
        }

        \App\Models\AiUsage::record(
            $model,
            (int) ($usage['promptTokenCount'] ?? 0),
            (int) ($usage['cachedContentTokenCount'] ?? 0),
            (int) ($usage['candidatesTokenCount'] ?? 0),
            (int) ($usage['thoughtsTokenCount'] ?? 0),
        );
    }
}
