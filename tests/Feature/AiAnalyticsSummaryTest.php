<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAnalyticsSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_summary_returns_plain_text_summary(): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => 'Revenue is trending up with strong order completion rates. Focus on pending deposits.',
                    ]]],
                ]],
            ], 200),
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.analytics.summary', ['days' => 7]));

        $response->assertOk()
            ->assertJsonFragment(['summary' => 'Revenue is trending up with strong order completion rates. Focus on pending deposits.']);
    }

    public function test_ai_summary_returns_503_when_ai_unconfigured(): void
    {
        config(['services.gemini.api_key' => null]);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.analytics.summary', ['days' => 7]));

        $response->assertStatus(503)
            ->assertJsonFragment(['message' => 'AI summarizer is not available.']);
    }

    public function test_ai_summary_rejects_invalid_days(): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => 'Summary text.',
                    ]]],
                ]],
            ], 200),
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.analytics.summary', ['days' => 99]));

        $response->assertOk()
            ->assertJsonFragment(['days' => 7]);
    }
}
