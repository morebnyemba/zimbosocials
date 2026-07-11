<?php

namespace App\WhatsApp\Flow\Definitions;

use App\Models\Ticket;
use App\WhatsApp\Flow\AbstractFlow;
use App\WhatsApp\Flow\FlowResult;
use App\WhatsApp\Session\SessionContext;

/** Lists the user's recent support tickets. Flow id: 'tickets'. */
class ViewTicketsFlow extends AbstractFlow
{
    public function id(): string
    {
        return 'tickets';
    }

    public function prompt(string $state, SessionContext $ctx): FlowResult
    {
        $user = $this->user($ctx);
        if (! $user) {
            return FlowResult::fail("⚠️ Couldn't load your tickets. Type *menu*.");
        }

        $tickets = Ticket::where('user_id', $user->id)->latest()->limit(5)->get();
        if ($tickets->isEmpty()) {
            return FlowResult::complete("🆘 You have no support tickets.\n\nType *support* to open one.");
        }

        $emoji = ['open' => '🟢', 'pending' => '🟡', 'closed' => '⚪'];
        $lines = $tickets->map(function (Ticket $t) use ($emoji) {
            $mark = $emoji[$t->status] ?? '•';

            return "{$mark} *#{$t->id}* — {$t->subject}\n   ".ucfirst($t->status).' · '.$t->created_at?->diffForHumans();
        })->implode("\n\n");

        return FlowResult::complete("🆘 *Your tickets*\n\n{$lines}\n\nType *support* to open a new ticket.");
    }

    public function handle(string $state, string $input, SessionContext $ctx): FlowResult
    {
        return $this->prompt($state, $ctx);
    }
}
