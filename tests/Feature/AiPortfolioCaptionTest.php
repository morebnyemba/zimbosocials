<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiPortfolioCaptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketer_can_view_portfolio_caption_page(): void
    {
        $marketer = User::factory()->create([
            'role' => 'marketer',
            'is_active' => true,
        ]);

        $response = $this->actingAs($marketer)
            ->get(route('marketer.portfolio-caption'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketer/PortfolioCaption')
            );
    }

    public function test_generate_portfolio_caption_returns_captions(): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'caption_en' => 'English caption',
                            'caption_sn' => 'Shona caption',
                            'caption_nd' => 'Ndebele caption',
                        ]),
                    ]]],
                ]],
            ], 200),
        ]);

        $marketer = User::factory()->create([
            'role' => 'marketer',
            'is_active' => true,
        ]);

        $response = $this->actingAs($marketer)
            ->postJson(route('marketer.portfolio-caption.generate'), [
                'title' => 'My best TikTok campaign',
                'platform' => 'tiktok',
                'tone' => 'playful',
            ]);

        $response->assertOk()
            ->assertJsonPath('caption_en', 'English caption')
            ->assertJsonPath('caption_sn', 'Shona caption')
            ->assertJsonPath('caption_nd', 'Ndebele caption');
    }

    public function test_generate_portfolio_caption_falls_back_to_english(): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'caption_en' => 'Only English caption',
                            'caption_sn' => '',
                            'caption_nd' => '',
                        ]),
                    ]]],
                ]],
            ], 200),
        ]);

        $marketer = User::factory()->create([
            'role' => 'marketer',
            'is_active' => true,
        ]);

        $response = $this->actingAs($marketer)
            ->postJson(route('marketer.portfolio-caption.generate'), [
                'title' => 'Instagram launch',
                'platform' => 'instagram',
            ]);

        $response->assertOk()
            ->assertJsonPath('caption_sn', 'Only English caption')
            ->assertJsonPath('caption_nd', 'Only English caption');
    }

    public function test_generate_portfolio_caption_returns_503_when_ai_unconfigured(): void
    {
        config(['services.gemini.api_key' => null]);

        $marketer = User::factory()->create([
            'role' => 'marketer',
            'is_active' => true,
        ]);

        $response = $this->actingAs($marketer)
            ->postJson(route('marketer.portfolio-caption.generate'), [
                'title' => 'My portfolio',
                'platform' => 'instagram',
            ]);

        $response->assertStatus(503)
            ->assertJsonFragment(['message' => 'AI caption generator is not available.']);
    }

    public function test_non_marketer_cannot_access_portfolio_caption(): void
    {
        $user = User::factory()->create([
            'role' => 'individual',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('marketer.portfolio-caption'))
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson(route('marketer.portfolio-caption.generate'), [
                'title' => 'My portfolio',
                'platform' => 'instagram',
            ])
            ->assertForbidden();
    }
}
