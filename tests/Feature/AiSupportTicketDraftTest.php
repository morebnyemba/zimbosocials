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

    private function makeTicket(User $customer, string $status = 'open'): Ticket
    {
        return Ticket::create([
            'user_id' => $customer->id,
            'subject' => 'Order delay',
            'message' => 'My order has not started yet.',
            'status' => $status,
            'priority' => 'medium',
        ]);
    }

    private function fakeGemini(string $text): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => $text,
                    ]]],
                ]],
            ], 200),
        ]);
    }

    public function test_draft_reply_returns_ai_generated_text_from_admin_intent(): void
    {
        $this->fakeGemini('Thanks for reaching out. Your order was refunded yesterday and the funds are in your wallet.');

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $ticket = $this->makeTicket($customer);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.tickets.draft-reply', $ticket), [
                'intent' => 'order was refunded yesterday, funds are in the wallet, apologise for the delay',
            ]);

        $response->assertOk()
            ->assertJsonFragment(['draft' => 'Thanks for reaching out. Your order was refunded yesterday and the funds are in your wallet.']);
    }

    public function test_draft_reply_requires_the_admins_intent(): void
    {
        // The AI must never write a reply without being told what to say.
        config(['services.gemini.api_key' => 'test-key']);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $ticket = $this->makeTicket($customer);

        $this->actingAs($admin)
            ->postJson(route('admin.tickets.draft-reply', $ticket))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['intent']);
    }

    public function test_enhance_reply_polishes_admin_draft(): void
    {
        $this->fakeGemini('Hello John, your order #55 completed this morning — please check and let us know if anything is missing.');

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $ticket = $this->makeTicket($customer);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.tickets.enhance-reply', $ticket), [
                'message' => 'order 55 done this morning check it',
            ]);

        $response->assertOk()
            ->assertJsonFragment(['draft' => 'Hello John, your order #55 completed this morning — please check and let us know if anything is missing.']);
    }

    public function test_summarize_returns_thread_summary(): void
    {
        $this->fakeGemini("Issue: order delay.\nSo far: no replies yet.\nSuggested next step: check the order status.");

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $ticket = $this->makeTicket($customer);

        $this->actingAs($admin)
            ->postJson(route('admin.tickets.summarize', $ticket))
            ->assertOk()
            ->assertJsonStructure(['summary']);
    }

    public function test_draft_reply_returns_503_when_ai_unconfigured(): void
    {
        config(['services.gemini.api_key' => null]);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $ticket = $this->makeTicket($customer);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.tickets.draft-reply', $ticket), [
                'intent' => 'let them know we are checking',
            ]);

        $response->assertStatus(503)
            ->assertJsonFragment(['message' => 'AI assistant is not available.']);
    }

    public function test_draft_reply_fails_for_closed_ticket(): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $ticket = $this->makeTicket($customer, 'closed');

        $response = $this->actingAs($admin)
            ->postJson(route('admin.tickets.draft-reply', $ticket), [
                'intent' => 'let them know we are checking',
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Ticket is closed.']);
    }
}
