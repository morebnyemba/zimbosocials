<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiSeoGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_category_seo_content(): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        Service::factory()->create([
            'name' => 'Instagram Followers',
            'category' => 'instagram',
            'is_active' => true,
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'headline' => 'Grow your Instagram presence',
                            'body' => 'Real follower growth for creators and brands.',
                            'meta_title' => 'Instagram Growth Services',
                            'meta_description' => 'Buy Instagram followers, likes and views in Zimbabwe.',
                        ]),
                    ]]],
                ]],
            ], 200),
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.seo.generate'), [
                'type' => 'category',
                'category' => 'instagram',
            ]);

        $response->assertOk()
            ->assertJsonFragment(['headline' => 'Grow your Instagram presence']);
    }

    public function test_generate_faq_content(): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        Service::factory()->create([
            'name' => 'TikTok Views',
            'category' => 'tiktok',
            'is_active' => true,
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            ['question' => 'Is it safe?', 'answer' => 'Yes, we never ask for passwords.'],
                        ]),
                    ]]],
                ]],
            ], 200),
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.seo.generate'), [
                'type' => 'faq',
                'count' => 1,
            ]);

        $response->assertOk()
            ->assertJsonPath('faqs.0.question', 'Is it safe?');
    }

    public function test_generate_seo_returns_503_when_ai_unconfigured(): void
    {
        config(['services.gemini.api_key' => null]);

        Service::factory()->create([
            'name' => 'Instagram Followers',
            'category' => 'instagram',
            'is_active' => true,
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.seo.generate'), [
                'type' => 'category',
                'category' => 'instagram',
            ]);

        $response->assertStatus(503)
            ->assertJsonFragment(['message' => 'AI SEO generator is not available or no services found.']);
    }
}
