<?php

namespace Tests\Feature;

use App\Models\AiUsage;
use App\Services\AI\GeminiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The system prompt is thousands of tokens and byte-identical on every message,
 * yet every message paid full input price for it. Caching it with Google bills
 * reuse at a fraction of that — and recording usage means the saving is a
 * number on a dashboard rather than a hope.
 */
class AiUsageAndCachingTest extends TestCase
{
    use RefreshDatabase;

    private const SYSTEM = 'You are a helpful assistant. '; // padded below

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.gemini.api_key' => 'k',
            'services.gemini.model' => 'gemini-2.5-flash',
            'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'services.gemini.cache_system_prompt' => true,
        ]);
        Cache::flush();
    }

    /** Long enough to clear Google's minimum for a cache. */
    private function systemPrompt(): string
    {
        return str_repeat(self::SYSTEM, 400);
    }

    private function fakeGemini(array $usage = [], bool $cacheOk = true): void
    {
        Http::fake([
            '*/cachedContents*' => $cacheOk
                ? Http::response(['name' => 'cachedContents/abc123'])
                : Http::response(['error' => ['message' => 'too small']], 400),
            '*generateContent*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '{"reply":"hi"}']]]]],
                'usageMetadata' => $usage + ['promptTokenCount' => 12000, 'cachedContentTokenCount' => 9000, 'candidatesTokenCount' => 200],
            ]),
        ]);
    }

    public function test_the_system_prompt_is_cached_and_referenced_not_resent(): void
    {
        $this->fakeGemini();

        app(GeminiClient::class)->generateJson('hello', 0.4, system: $this->systemPrompt());

        $call = collect(Http::recorded())->first(fn ($r) => str_contains($r[0]->url(), 'generateContent'));
        $body = json_decode($call[0]->body(), true);

        // Referenced by name...
        $this->assertSame('cachedContents/abc123', $body['cachedContent'] ?? null);
        // ...and NOT resent inline. Sending both is an error, and resending it
        // is the whole cost we are removing.
        $this->assertArrayNotHasKey('systemInstruction', $body);
    }

    public function test_the_cache_is_created_once_and_reused(): void
    {
        $this->fakeGemini();
        $client = app(GeminiClient::class);

        $client->generateJson('one', 0.4, system: $this->systemPrompt());
        $client->generateJson('two', 0.4, system: $this->systemPrompt());
        $client->generateJson('three', 0.4, system: $this->systemPrompt());

        $creates = collect(Http::recorded())->filter(fn ($r) => str_contains($r[0]->url(), 'cachedContents'))->count();
        $this->assertSame(1, $creates, 'the prompt should be cached once, not per message');
    }

    public function test_an_edited_prompt_gets_its_own_cache(): void
    {
        $this->fakeGemini();
        $client = app(GeminiClient::class);

        $client->generateJson('one', 0.4, system: $this->systemPrompt());
        $client->generateJson('two', 0.4, system: $this->systemPrompt().' NEW RULE.');

        // Otherwise a prompt change would silently keep serving old instructions.
        $creates = collect(Http::recorded())->filter(fn ($r) => str_contains($r[0]->url(), 'cachedContents'))->count();
        $this->assertSame(2, $creates);
    }

    public function test_a_refused_cache_falls_back_to_sending_it_inline(): void
    {
        $this->fakeGemini(cacheOk: false);

        $result = app(GeminiClient::class)->generateJson('hello', 0.4, system: $this->systemPrompt());

        // Fail-open: a caching problem must never cost a customer their reply.
        $this->assertIsArray($result);
        $call = collect(Http::recorded())->first(fn ($r) => str_contains($r[0]->url(), 'generateContent'));
        $body = json_decode($call[0]->body(), true);
        $this->assertArrayHasKey('systemInstruction', $body);
    }

    public function test_usage_is_banked_for_the_dashboard(): void
    {
        $this->fakeGemini();

        app(GeminiClient::class)->generateJson('hello', 0.4, system: $this->systemPrompt());

        $row = AiUsage::first();
        $this->assertNotNull($row);
        $this->assertSame(1, $row->requests);
        $this->assertSame(12000, $row->prompt_tokens);
        $this->assertSame(9000, $row->cached_tokens);
        $this->assertSame(200, $row->output_tokens);
    }

    public function test_cached_tokens_are_costed_cheaper_than_fresh_ones(): void
    {
        config([
            'services.gemini.rates.input' => 0.30,
            'services.gemini.rates.cached_input' => 0.075,
            'services.gemini.rates.output' => 2.50,
        ]);

        $cached = AiUsage::create([
            'day' => now()->toDateString(), 'model' => 'a',
            'requests' => 1, 'prompt_tokens' => 100_000, 'cached_tokens' => 90_000, 'output_tokens' => 0,
        ]);
        $fresh = AiUsage::create([
            'day' => now()->subDay()->toDateString(), 'model' => 'a',
            'requests' => 1, 'prompt_tokens' => 100_000, 'cached_tokens' => 0, 'output_tokens' => 0,
        ]);

        // Same tokens, materially cheaper — which is the point of the exercise.
        $this->assertLessThan($fresh->cost(), $cached->cost());
        $this->assertEqualsWithDelta(0.0098, $cached->cost(), 0.0005);
        $this->assertEqualsWithDelta(0.03, $fresh->cost(), 0.0005);
    }

    public function test_several_requests_accumulate_rather_than_overwrite(): void
    {
        $this->fakeGemini();
        $client = app(GeminiClient::class);

        $client->generateJson('one', 0.4, system: $this->systemPrompt());
        $client->generateJson('two', 0.4, system: $this->systemPrompt());

        $row = AiUsage::first();
        $this->assertSame(2, $row->requests);
        $this->assertSame(24000, $row->prompt_tokens);
    }
}
