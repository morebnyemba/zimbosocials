<?php

namespace App\WhatsApp\Flow\Definitions;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\WhatsApp\Flow\AbstractFlow;
use App\WhatsApp\Flow\FlowResult;
use App\WhatsApp\Session\SessionContext;

/**
 * Support hub. Flow id: 'tickets'.
 * States: pick (choose a ticket from the list) → view (read the conversation,
 * including our team's responses) → reply (append an update to the ticket).
 * The "New ticket" row uses the global fl_ticket id so it hands off to
 * CreateTicketFlow via normal router navigation.
 */
class ViewTicketsFlow extends AbstractFlow
{
    private const STATUS_EMOJI = ['open' => '🟢', 'pending' => '🟡', 'closed' => '⚪'];

    public function id(): string
    {
        return 'tickets';
    }

    public function prompt(string $state, SessionContext $ctx): FlowResult
    {
        if (! $this->user($ctx)) {
            return FlowResult::fail("⚠️ Couldn't load your tickets. Type *menu*.");
        }

        // AI fast-forward: jump straight into a specific ticket's conversation.
        $prefill = $ctx->pullPrefill('ticket_id');
        if ($prefill !== null && is_numeric($prefill)) {
            return $this->viewTicket((int) $prefill, $ctx);
        }

        return $this->listTickets($ctx);
    }

    public function handle(string $state, string $input, SessionContext $ctx): FlowResult
    {
        return match ($state) {
            'pick' => $this->pick($input, $ctx),
            'view' => $this->viewActions($input, $ctx),
            'reply' => $this->saveReply($input, $ctx),
            default => $this->prompt('start', $ctx),
        };
    }

    private function listTickets(SessionContext $ctx): FlowResult
    {
        $user = $this->user($ctx);
        $tickets = Ticket::where('user_id', $user->id)
            ->orderByDesc('last_reply_at')
            ->limit(9)
            ->get();

        if ($tickets->isEmpty()) {
            return FlowResult::complete('🆘 You have no support tickets yet. Need help with something?', ['skipMenu' => true])
                ->withButtons([['id' => 'fl_ticket', 'title' => '➕ New ticket']]);
        }

        $ctx->set('tickets_ids', $tickets->pluck('id')->all());

        $rows = $tickets->values()->map(function (Ticket $t, $i) {
            $mark = self::STATUS_EMOJI[$t->status] ?? '•';

            return [
                'id' => 'fs:'.($i + 1),
                'title' => "#{$t->id} {$t->subject}",
                'description' => "{$mark} ".ucfirst($t->status).' · '.$t->last_reply_at?->diffForHumans(),
            ];
        })->all();
        $rows[] = ['id' => 'fl_ticket', 'title' => '➕ New ticket', 'description' => 'Open a new support request'];

        return FlowResult::step('🆘 Your support tickets — tap one to read the conversation:', 'pick')
            ->withList('View ticket', [['title' => 'Tickets', 'rows' => $rows]], 'Support');
    }

    private function pick(string $input, SessionContext $ctx): FlowResult
    {
        $ids = collect($ctx->get('tickets_ids', []));
        $number = (int) preg_replace('/\D+/', '', $input);

        // A list position (1-9) or a raw ticket number ("#123") both work.
        $id = $ids->get($number - 1) ?? ($ids->contains($number) ? $number : null);
        if (! $id) {
            return FlowResult::retry('Tap a ticket from the list, or send its number (e.g. *#12*).', 'pick');
        }

        return $this->viewTicket((int) $id, $ctx);
    }

    /** Render the ticket conversation — the original message plus the latest replies. */
    private function viewTicket(int $id, SessionContext $ctx): FlowResult
    {
        $user = $this->user($ctx);
        $ticket = Ticket::with(['replies' => fn ($q) => $q->latest()->limit(3)])
            ->where('user_id', $user->id)
            ->find($id);

        if (! $ticket) {
            return FlowResult::fail("❌ No ticket *#{$id}* on your account. Type *support* to see your tickets.");
        }

        $ctx->set('ticket_view_id', $ticket->id);
        $mark = self::STATUS_EMOJI[$ticket->status] ?? '•';

        $msg = "🎫 *Ticket #{$ticket->id}* — {$ticket->subject}\n";
        $msg .= "Status: {$mark} ".ucfirst($ticket->status)."\n\n";
        $msg .= '👤 You · '.$ticket->created_at?->format('d M')."\n".$this->excerpt($ticket->message)."\n";

        if ($ticket->replies->isEmpty()) {
            $msg .= "\n_No responses yet — our team will get back to you soon._";
        } else {
            // Query took the newest 3; show them oldest-first like a chat.
            foreach ($ticket->replies->reverse()->values() as $reply) {
                $who = $reply->is_admin ? '🛟 Support' : '👤 You';
                $msg .= "\n{$who} · ".$reply->created_at?->format('d M H:i')."\n".$this->excerpt($reply->message)."\n";
            }
        }

        $buttons = [['id' => 'fs:list', 'title' => '📋 All tickets']];
        if ($ticket->status !== 'closed') {
            array_unshift($buttons, ['id' => 'fs:reply', 'title' => '💬 Reply']);
        }

        return FlowResult::step($msg, 'view')->withButtons($buttons);
    }

    private function viewActions(string $input, SessionContext $ctx): FlowResult
    {
        $choice = mb_strtolower(trim($input));

        if ($choice === 'list' || $choice === 'tickets') {
            return $this->listTickets($ctx);
        }
        if ($choice === 'reply') {
            $id = (int) $ctx->get('ticket_view_id');

            return FlowResult::step("💬 Type your reply to *ticket #{$id}*:", 'reply');
        }

        return FlowResult::retry('Tap *💬 Reply* to add an update, or *📋 All tickets* to go back.', 'view');
    }

    private function saveReply(string $input, SessionContext $ctx): FlowResult
    {
        $message = trim($input);
        if (mb_strlen($message) < 2) {
            return FlowResult::retry('Please type a longer reply, or type *cancel*.', 'reply');
        }

        $user = $this->user($ctx);
        $ticket = Ticket::where('user_id', $user->id)->find((int) $ctx->get('ticket_view_id'));
        if (! $ticket) {
            return FlowResult::fail('Something went wrong. Type *support* to try again.');
        }

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => mb_substr($message, 0, 2000),
            'is_admin' => false,
        ]);

        // Same status semantics as replying on the website.
        $ticket->update(['status' => 'pending', 'last_reply_at' => now()]);

        return FlowResult::complete(
            "✅ Reply added to *ticket #{$ticket->id}*. Our team will get back to you soon.\n\nType *support* anytime to check for responses."
        );
    }

    /** Chat-friendly excerpt that keeps the whole thread inside message limits. */
    private function excerpt(string $text): string
    {
        $text = trim($text);

        return mb_strlen($text) > 220 ? mb_substr($text, 0, 219).'…' : $text;
    }
}
