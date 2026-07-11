<?php

namespace App\WhatsApp\Flow\Definitions;

use App\Models\Ticket;
use App\WhatsApp\Flow\AbstractFlow;
use App\WhatsApp\Flow\FlowResult;
use App\WhatsApp\Session\SessionContext;

/**
 * Open a support ticket. Flow id: 'ticket'.
 * States: subject → message → create.
 */
class CreateTicketFlow extends AbstractFlow
{
    public function id(): string
    {
        return 'ticket';
    }

    public function prompt(string $state, SessionContext $ctx): FlowResult
    {
        if ($state === 'ask_message') {
            return FlowResult::step('Now describe your issue in a message.', 'ask_message');
        }

        return FlowResult::step("🆘 *New support ticket*\n\nWhat's the *subject*? (a short summary)", 'ask_subject');
    }

    public function handle(string $state, string $input, SessionContext $ctx): FlowResult
    {
        $input = trim($input);

        if ($state === 'ask_subject') {
            if (mb_strlen($input) < 3) {
                return FlowResult::step('Please give a short subject (at least 3 characters).', 'ask_subject');
            }
            $ctx->set('ticket_subject', mb_substr($input, 0, 150));

            return FlowResult::step('📝 Thanks. Now describe your issue in a message.', 'ask_message');
        }

        if ($state === 'ask_message') {
            if (mb_strlen($input) < 5) {
                return FlowResult::step('Please add a bit more detail (at least 5 characters).', 'ask_message');
            }

            $user = $this->user($ctx);
            if (! $user) {
                return FlowResult::fail('Please try again from the *menu*.');
            }

            $ticket = Ticket::create([
                'user_id' => $user->id,
                'subject' => (string) $ctx->get('ticket_subject', 'Support request'),
                'message' => mb_substr($input, 0, 2000),
                'status' => 'open',
                'priority' => 'medium',
                'last_reply_at' => now(),
            ]);

            return FlowResult::complete(
                "✅ *Ticket #{$ticket->id} created.*\n\nOur team will get back to you soon. Type *tickets* to view your tickets."
            );
        }

        return $this->prompt('ask_subject', $ctx);
    }
}
