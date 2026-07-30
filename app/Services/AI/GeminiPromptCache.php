<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Keeps the system prompt cached WITH Gemini, so it isn't re-billed as fresh
 * input on every single message.
 *
 * Our system instruction is thousands of tokens of rules and examples and is
 * byte-identical on every call, yet each message paid full input price for it.
 * Google's explicit caching stores it once and bills reuse at roughly a quarter
 * of the rate, so the saving scales with conversation volume — the busier the
 * bot gets, the more this matters.
 *
 * Deliberately fail-open: if the cache cannot be created or has expired, the
 * caller sends the system prompt inline exactly as before. A caching problem
 * must never cost a customer their reply.
 */
class GeminiPromptCache
{
    /** Google rejects caches below this; nothing near our prompt size. */
    private const MIN_TOKENS_APPROX = 1024;

    /**
     * The cached-content resource name to attach to a request, or null to send
     * the system prompt inline.
     *
     * Keyed by a hash of the prompt itself, so bumping PROMPT_VERSION — or any
     * edit at all — creates a new cache rather than silently serving the old
     * instructions.
     */
    public function nameFor(string $systemPrompt, string $model): ?string
    {
        if (! config('services.gemini.cache_system_prompt', true)) {
            return null;
        }

        // Rough token estimate; ~4 chars per token is close enough to avoid a
        // pointless API call for a prompt that could never qualify.
        if (strlen($systemPrompt) / 4 < self::MIN_TOKENS_APPROX) {
            return null;
        }

        $key = 'gemini:prompt-cache:'.$model.':'.substr(hash('sha256', $systemPrompt), 0, 16);

        $name = Cache::get($key);
        if (is_string($name) && $name !== '') {
            return $name;
        }

        $name = $this->create($systemPrompt, $model);
        if ($name === null) {
            // Don't retry on every message if Google is refusing.
            Cache::put($key.':cooldown', true, now()->addMinutes(10));

            return null;
        }

        // Expire our record a little before Google's, so we never hand out a
        // name that has just lapsed.
        $ttl = max(120, (int) config('services.gemini.cache_ttl_seconds', 3600));
        Cache::put($key, $name, now()->addSeconds($ttl - 60));

        return $name;
    }

    /** Drop our record of a cache Google has told us is gone. */
    public function forget(string $systemPrompt, string $model): void
    {
        Cache::forget('gemini:prompt-cache:'.$model.':'.substr(hash('sha256', $systemPrompt), 0, 16));
    }

    private function create(string $systemPrompt, string $model): ?string
    {
        $base = rtrim((string) config('services.gemini.base_url'), '/');
        $ttl = max(120, (int) config('services.gemini.cache_ttl_seconds', 3600));

        try {
            $response = Http::timeout(15)
                ->withHeaders(['x-goog-api-key' => config('services.gemini.api_key')])
                ->asJson()
                ->post("{$base}/cachedContents", [
                    'model' => 'models/'.$model,
                    'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                    'ttl' => $ttl.'s',
                ]);

            if ($response->failed()) {
                // Most often the prompt is under the minimum, or the model does
                // not support caching. Worth seeing once, not every message.
                Log::info('Gemini prompt cache not created', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 300),
                ]);

                return null;
            }

            $name = data_get($response->json(), 'name');

            return is_string($name) && $name !== '' ? $name : null;
        } catch (\Throwable $e) {
            Log::info('Gemini prompt cache request failed', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
