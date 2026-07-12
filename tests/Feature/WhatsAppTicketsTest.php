<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\WhatsApp\Flow\FlowEngine;
use App\WhatsApp\Menu\MenuProvider;
use App\WhatsApp\Session\SessionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppTicketsTest extends TestCase
{
    use RefreshDatabase;

    private function makeTicketWithAdminReply(User $user): Ticket
    {
        $ticket = Ticket::create([
            'user_id' => $user->id,
            'subject' => 'Order stuck',
            'message' => 'My order has been processing for two days.',
            'status' => 'open',
            'priority' => 'medium',
            'last_reply_at' => now(),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'message' => 'We are checking with the provider now.',
            'is_admin' => true,
        ]);

        return $ticket;
    }

    public function test_ticket_hub_lists_tickets_and_shows_admin_responses(): void
    {
        $user = User::factory()->create();
        $ticket = $this->makeTicketWithAdminReply($user);

        $ctx = new SessionContext('263771234567');
        $ctx->set('_user_id', $user->id);
        $engine = app(FlowEngine::class);

        // List: interactive, ticket row + "New ticket" (global fl_ticket id).
        $res = $engine->start($ctx, 'tickets');
        $this->assertNotNull($res->list);
        $rows = $res->list['sections'][0]['rows'];
        $this->assertSame('fs:1', $rows[0]['id']);
        $this->assertStringContainsString("#{$ticket->id}", $rows[0]['title']);
        $this->assertSame('fl_ticket', end($rows)['id']);

        // Open the ticket: the team's response is visible, with Reply button.
        $res = $engine->advance($ctx, '1');
        $this->assertStringContainsString('We are checking with the provider now.', (string) $res->reply);
        $this->assertStringContainsString('🛟 Support', (string) $res->reply);
        $this->assertSame('fs:reply', $res->buttons[0]['id']);
    }

    public function test_user_can_reply_to_a_ticket_from_chat(): void
    {
        $user = User::factory()->create();
        $ticket = $this->makeTicketWithAdminReply($user);

        $ctx = new SessionContext('263771234567');
        $ctx->set('_user_id', $user->id);
        $engine = app(FlowEngine::class);

        $engine->start($ctx, 'tickets');
        $engine->advance($ctx, '1');            // open ticket
        $engine->advance($ctx, 'reply');        // 💬 Reply button (fs:reply)
        $res = $engine->advance($ctx, 'Still not delivered, please escalate.');

        $this->assertStringContainsString('Reply added', (string) $res->reply);
        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => 'Still not delivered, please escalate.',
            'is_admin' => false,
        ]);
        $this->assertSame('pending', $ticket->fresh()->status);
    }

    public function test_ai_prefill_jumps_straight_into_a_ticket_thread(): void
    {
        $user = User::factory()->create();
        $ticket = $this->makeTicketWithAdminReply($user);

        $ctx = new SessionContext('263771234567');
        $ctx->set('_user_id', $user->id);
        $ctx->set('_prefill_ticket_id', $ticket->id);

        $res = app(FlowEngine::class)->start($ctx, 'tickets');

        $this->assertStringContainsString("Ticket #{$ticket->id}", (string) $res->reply);
        $this->assertSame('view', $ctx->state);
    }

    public function test_closed_tickets_do_not_offer_reply(): void
    {
        $user = User::factory()->create();
        $ticket = $this->makeTicketWithAdminReply($user);
        $ticket->update(['status' => 'closed']);

        $ctx = new SessionContext('263771234567');
        $ctx->set('_user_id', $user->id);
        $ctx->set('_prefill_ticket_id', $ticket->id);

        $res = app(FlowEngine::class)->start($ctx, 'tickets');

        $ids = array_column($res->buttons ?? [], 'id');
        $this->assertNotContains('fs:reply', $ids);
    }

    public function test_main_menu_respects_whatsapp_ten_row_limit(): void
    {
        foreach ([app(MenuProvider::class)->mainMenu('Test', '5.00 USD'), app(MenuProvider::class)->guestMenu()] as $menu) {
            $rowCount = collect($menu['sections'])->sum(fn ($s) => count($s['rows']));
            $this->assertLessThanOrEqual(10, $rowCount, 'WhatsApp rejects lists with more than 10 rows');

            // Every row must resolve to a known navigation target.
            foreach (collect($menu['sections'])->flatMap(fn ($s) => $s['rows']) as $row) {
                $known = isset(MenuProvider::$actionFlow[$row['id']])
                    || in_array($row['id'], ['guest_learn', 'menu_home'], true);
                $this->assertTrue($known, "Menu row id {$row['id']} has no navigation mapping");
            }
        }
    }
}
