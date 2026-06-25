<?php

namespace Tests\Feature;

use App\Models\BusinessContract;
use App\Models\ContractApplication;
use App\Models\ContractProofSubmission;
use App\Models\MarketerPortfolio;
use App\Models\MarketerReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiContentModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_moderate_portfolio_flags_inappropriate_content(): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'flagged' => true,
                            'reason' => 'Contains misleading engagement guarantees.',
                            'severity' => 'medium',
                        ]),
                    ]]],
                ]],
            ], 200),
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $marketer = User::factory()->create(['role' => 'marketer', 'is_active' => true]);
        $portfolio = MarketerPortfolio::create([
            'user_id' => $marketer->id,
            'title' => 'Buy 10k followers instantly',
            'platform' => 'Instagram',
            'url' => 'https://example.com',
            'description' => 'Guaranteed real followers in one hour.',
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.moderation.portfolio', $portfolio));

        $response->assertOk()
            ->assertJsonFragment(['flagged' => true])
            ->assertJsonFragment(['severity' => 'medium']);
    }

    public function test_moderate_proof_returns_503_when_clean(): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'flagged' => false,
                            'reason' => '',
                            'severity' => 'low',
                        ]),
                    ]]],
                ]],
            ], 200),
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $marketer = User::factory()->create(['role' => 'marketer', 'is_active' => true]);
        $contract = BusinessContract::create([
            'user_id' => $admin->id,
            'title' => 'Test Contract',
            'platform' => 'Instagram',
            'description' => 'Test',
            'budget' => 10,
            'slots' => 1,
            'status' => 'open',
        ]);
        $application = ContractApplication::create([
            'business_contract_id' => $contract->id,
            'marketer_id' => $marketer->id,
            'pitch' => 'I can do this.',
            'status' => 'approved',
        ]);
        $proof = ContractProofSubmission::create([
            'contract_application_id' => $application->id,
            'marketer_id' => $marketer->id,
            'proof_url' => 'https://example.com/proof',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.moderation.proof', $proof));

        $response->assertStatus(503)
            ->assertJsonFragment(['message' => 'AI moderator is not available or content looks clean.']);
    }

    public function test_moderate_review_returns_503_when_ai_unconfigured(): void
    {
        config(['services.gemini.api_key' => null]);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $marketer = User::factory()->create(['role' => 'marketer', 'is_active' => true]);
        $contract = BusinessContract::create([
            'user_id' => $admin->id,
            'title' => 'Test Contract',
            'platform' => 'Instagram',
            'description' => 'Test',
            'budget' => 10,
            'slots' => 1,
            'status' => 'open',
        ]);
        $application = ContractApplication::create([
            'business_contract_id' => $contract->id,
            'marketer_id' => $marketer->id,
            'pitch' => 'I can do this.',
            'status' => 'approved',
        ]);
        $review = MarketerReview::create([
            'business_contract_id' => $contract->id,
            'contract_application_id' => $application->id,
            'reviewer_id' => $admin->id,
            'marketer_id' => $marketer->id,
            'rating' => 5,
            'comment' => 'Great work.',
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.moderation.review', $review));

        $response->assertStatus(503)
            ->assertJsonFragment(['message' => 'AI moderator is not available or content looks clean.']);
    }
}
