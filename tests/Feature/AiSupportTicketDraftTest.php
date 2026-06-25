<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiSupportTicketDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_reply_returns_ai_generated_text(): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => 'Thanks for reaching out. We are looking into your order and will update you shortly.',
                    ]]],
                ]],
            ], 200),
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $ticket = Ticket::create([
            'user_id' => $customer->id,
            'subject' => 'Order delay',
            'message' => 'My order has not started yet.',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.tickets.draft-reply', $ticket));

        $response->assertOk()
            ->assertJsonFragment(['draft' => 'Thanks for reaching out. We are looking into your order and will update you shortly.']);
    }

    public function test_draft_reply_returns_503_when_ai_unconfigured(): void
    {
        config(['services.gemini.api_key' => null]);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $ticket = Ticket::create([
            'user_id' => $customer->id,
            'subject' => 'Order delay',
            'message' => 'My order has not started yet.',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.tickets.draft-reply', $ticket));

        $response->assertStatus(503)
            ->assertJsonFragment(['message' => 'AI assistant is not available.']);
    }

    public function test_draft_reply_fails_for_closed_ticket(): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $ticket = Ticket::create([
            'user_id' => $customer->id,
            'subject' => 'Order delay',
            'message' => 'My order has not started yet.',
            'status' => 'closed',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.tickets.draft-reply', $ticket));

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Ticket is closed.']);
    }
}
