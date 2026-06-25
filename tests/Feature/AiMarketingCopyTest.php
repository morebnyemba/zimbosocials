<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiMarketingCopyTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_copy_returns_campaign_content(): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'campaign_name' => 'Weekend Bonus Blast',
                            'subject_en' => 'Get 10% extra on deposits',
                            'body_en' => 'Top up this weekend and get a 10% bonus on every deposit.',
                            'subject_sn' => 'Wana 10% yawanikwa pamadhipoziti',
                            'body_sn' => 'Wedzera mari kupedzisira kwesvondo uye uwane 10% yawanikwa.',
                            'subject_nd' => 'Thola i-10% engeziwe kwizidiphozithi',
                            'body_nd' => 'Faka imali kuleli veki uthole i-10% engeziwe.',
                        ]),
                    ]]],
                ]],
            ], 200),
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.campaigns.generate-copy'), [
                'brief' => 'Announce a 10% deposit bonus this weekend',
                'channels' => ['email', 'whatsapp'],
            ]);

        $response->assertOk()
            ->assertJsonFragment(['campaign_name' => 'Weekend Bonus Blast'])
            ->assertJsonFragment(['subject_en' => 'Get 10% extra on deposits']);
    }

    public function test_generate_copy_returns_503_when_ai_unconfigured(): void
    {
        config(['services.gemini.api_key' => null]);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.campaigns.generate-copy'), [
                'brief' => 'Announce a weekend promo',
            ]);

        $response->assertStatus(503)
            ->assertJsonFragment(['message' => 'AI copywriter is not available.']);
    }

    public function test_generate_copy_validates_brief(): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.campaigns.generate-copy'), [
                'brief' => '',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['brief']);
    }
}
