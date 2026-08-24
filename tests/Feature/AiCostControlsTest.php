<?php

namespace Tests\Feature;

use App\Models\AiUsage;
use App\Services\AI\GeminiClient;
use App\WhatsApp\AI\AIGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The bill was not made of the things the dashboard was counting.
 *
 * Thinking tokens bill at the output rate and were never recorded; the model
 * chose its own budget for them; every call went to the flagship model whether
 * or not it was making a decision; and a call-count cap is not a spend cap.
 * These are the controls for each of those.
 */
class AiCostControlsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.gemini.api_key' => 'k',
            'services.gemini.model' => 'gemini-2.5-flash',
            'services.gemini.model_light' => 'gemini-2.5-flash-lite',
            'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'services.gemini.cache_system_prompt' => false,
        ]);
        Cache::flush();
    }

    private function fakeOk(array $usage = []): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => '{"reply":"hi"}']]]]],
            'usageMetadata' => $usage,
        ], 200)]);
    }

    /** An unset thinkingConfig is not a default — it is the model's own budget, billed as output. */
    public function test_thinking_budget_is_always_sent_explicitly(): void
    {
        config(['services.gemini.thinking_budget' => 0]);
        $this->fakeOk();

        app(GeminiClient::class)->generateJson('prompt');

        Http::assertSent(fn ($request) => $request->data()['generationConfig']['thinkingConfig']['thinkingBudget'] === 0);
    }

    public function test_thinking_budget_is_configurable_and_dynamic_is_still_reachable(): void
    {
        config(['services.gemini.thinking_budget' => 512, 'services.gemini.thinking_budget_text' => -1]);
        $this->fakeOk();

        $client = app(GeminiClient::class);
        $client->generateJson('prompt');
        $client->generateText('prompt');

        $budgets = [];
        Http::assertSent(function ($request) use (&$budgets) {
            $budgets[] = $request->data()['generationConfig']['thinkingConfig']['thinkingBudget'];

            return true;
        });
        $this->assertSame([512, -1], $budgets);
    }

    /** Reported separately from the reply, billed the same. Counting one and not the other under-reads the bill. */
    public function test_thinking_tokens_are_banked_and_costed(): void
    {
        $this->fakeOk([
            'promptTokenCount' => 10_000,
            'cachedContentTokenCount' => 0,
            'candidatesTokenCount' => 200,
            'thoughtsTokenCount' => 1_300,
        ]);

        app(GeminiClient::class)->generateJson('prompt');

        $row = AiUsage::first();
        $this->assertSame(1_300, $row->thinking_tokens);

        // 10k input @ $0.30/M + (200 + 1300) output @ $2.50/M, rounded to 4dp.
        $this->assertSame(round(0.003 + 0.00375, 4), $row->cost());

        // The reply alone would have read as barely half of that.
        $this->assertGreaterThan(0.003 + 200 / 1_000_000 * 2.50, $row->cost());
    }

    public function test_light_calls_go_to_the_cheap_model_and_are_costed_at_its_rates(): void
    {
        $this->fakeOk(['promptTokenCount' => 1_000, 'candidatesTokenCount' => 1_000]);

        app(GeminiClient::class)->generateText('prompt', light: true);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'gemini-2.5-flash-lite:generateContent'));

        // Flash-Lite: $0.10/M in, $0.40/M out — not the flagship's $0.30/$2.50.
        $this->assertEqualsWithDelta(0.0001 + 0.0004, AiUsage::first()->cost(), 0.00001);
    }

    public function test_light_routing_falls_back_to_the_main_model_when_unset(): void
    {
        config(['services.gemini.model_light' => null]);
        $this->fakeOk();

        app(GeminiClient::class)->generateText('prompt', light: true);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'gemini-2.5-flash:generateContent'));
    }

    /** A run of 400s used to cost four full prompt-sized requests for one inbound message. */
    public function test_a_single_json_call_never_costs_more_than_two_requests(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'bad schema']], 400)]);

        $this->assertNull(app(GeminiClient::class)->generateJson('prompt', schema: ['type' => 'OBJECT']));

        Http::assertSentCount(2);
    }

    public function test_the_assistant_stops_calling_the_model_once_the_daily_budget_is_spent(): void
    {
        config([
            'services.gemini.daily_budget_usd' => 0.35,
            'services.whatsapp.ai_daily_limit' => 0,
            'services.whatsapp.ai_global_daily_limit' => 0,
        ]);

        $guard = app(AIGuard::class);
        $this->assertTrue($guard->allow('263771234567'), 'nothing spent yet');

        // 1M output tokens on the flagship = $2.50, well past the ceiling.
        AiUsage::record('gemini-2.5-flash', 0, 0, 1_000_000);
        Cache::forget('wa:ai:spend:'.now()->format('Y-m-d'));

        $this->assertFalse($guard->allow('263771234567'), 'over budget — falls back to the deterministic menu');
    }

    public function test_a_zero_budget_means_no_ceiling(): void
    {
        config([
            'services.gemini.daily_budget_usd' => 0,
            'services.whatsapp.ai_daily_limit' => 0,
            'services.whatsapp.ai_global_daily_limit' => 0,
        ]);

        AiUsage::record('gemini-2.5-flash', 0, 0, 10_000_000);
        Cache::forget('wa:ai:spend:'.now()->format('Y-m-d'));

        $this->assertTrue(app(AIGuard::class)->allow('263771234567'));
    }
}
