<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiContentCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketer_can_view_content_calendar_page(): void
    {
        $marketer = User::factory()->create([
            'role' => 'marketer',
            'is_active' => true,
        ]);

        $response = $this->actingAs($marketer)
            ->get(route('marketer.content-calendar'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketer/ContentCalendar')
            );
    }

    public function test_generate_calendar_returns_seven_days(): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        $days = [];
        for ($i = 1; $i <= 7; $i++) {
            $days[] = [
                'day' => $i,
                'theme' => "Theme {$i}",
                'caption_en' => "English caption {$i}",
                'caption_sn' => "Shona caption {$i}",
                'caption_nd' => "Ndebele caption {$i}",
                'hashtags' => ['zimbabwe', 'smm'],
            ];
        }

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'platform' => 'instagram',
                            'days' => $days,
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
            ->postJson(route('marketer.content-calendar.generate'), [
                'brief' => 'Promote my graphic design services',
                'platform' => 'instagram',
                'tone' => 'playful',
            ]);

        $response->assertOk()
            ->assertJsonPath('platform', 'instagram')
            ->assertJsonCount(7, 'days')
            ->assertJsonPath('days.0.caption_en', 'English caption 1')
            ->assertJsonPath('days.0.hashtags', ['zimbabwe', 'smm']);
    }

    public function test_generate_calendar_falls_back_to_english_for_missing_translations(): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'platform' => 'tiktok',
                            'days' => [[
                                'day' => 1,
                                'theme' => 'Intro',
                                'caption_en' => 'Only English caption',
                                'caption_sn' => '',
                                'caption_nd' => '',
                                'hashtags' => 'smm, zimbabwe',
                            ]],
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
            ->postJson(route('marketer.content-calendar.generate'), [
                'brief' => 'Grow my TikTok presence',
            ]);

        $response->assertOk()
            ->assertJsonPath('days.0.caption_sn', 'Only English caption')
            ->assertJsonPath('days.0.caption_nd', 'Only English caption')
            ->assertJsonPath('days.0.hashtags', ['smm', 'zimbabwe']);
    }

    public function test_generate_calendar_returns_503_when_ai_unconfigured(): void
    {
        config(['services.gemini.api_key' => null]);

        $marketer = User::factory()->create([
            'role' => 'marketer',
            'is_active' => true,
        ]);

        $response = $this->actingAs($marketer)
            ->postJson(route('marketer.content-calendar.generate'), [
                'brief' => 'Promote my services',
            ]);

        $response->assertStatus(503)
            ->assertJsonFragment(['message' => 'AI content calendar is not available.']);
    }

    public function test_non_marketer_cannot_access_content_calendar(): void
    {
        $user = User::factory()->create([
            'role' => 'individual',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('marketer.content-calendar'))
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson(route('marketer.content-calendar.generate'), [
                'brief' => 'Promote my services',
            ])
            ->assertForbidden();
    }
}
