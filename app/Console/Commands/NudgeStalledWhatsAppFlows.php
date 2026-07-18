<?php

namespace App\Console\Commands;

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppSession;
use App\WhatsApp\Messaging\Responder;
use Illuminate\Console\Command;

/**
 * Recover drop-offs: a customer who went quiet in the middle of a flow (picking
 * a service, entering a link, at a confirm) gets ONE gentle "still there?" nudge
 * to pull them back — while they're still inside WhatsApp's 24h free-form window,
 * so no template is needed. Fires once per stall (nudged_at), never while a human
 * agent is handling the chat, and never for opted-out numbers.
 */
class NudgeStalledWhatsAppFlows extends Command
{
    protected $signature = 'whatsapp:nudge-stalled';

    protected $description = 'Nudge WhatsApp customers who stalled mid-flow so they don\'t drop off';

    // Give them a beat to reply on their own before nudging; stay well inside the
    // 24h window so the nudge is deliverable as free-form text.
    private const IDLE_MIN_MINUTES = 20;
    private const IDLE_MAX_HOURS = 12;

    public function handle(Responder $responder): int
    {
        $sessions = WhatsAppSession::query()
            ->whereNotNull('current_flow')
            ->where('status', 'active')
            ->whereNull('nudged_at')
            ->where('last_activity', '<=', now()->subMinutes(self::IDLE_MIN_MINUTES))
            ->where('last_activity', '>=', now()->subHours(self::IDLE_MAX_HOURS))
            ->limit(200)
            ->get();

        $sent = 0;

        foreach ($sessions as $session) {
            $account = WhatsAppAccount::where('wa_phone', $session->wa_phone)->first();

            // Skip opted-out numbers and chats a human agent is currently handling.
            if (! $account || ! $account->opted_in || $account->inAgentHandoff()) {
                // Still mark it so we don't reconsider this stall every minute.
                $session->update(['nudged_at' => now()]);

                continue;
            }

            $name = $this->firstName($account->display_name);
            $responder->send(
                $session->wa_phone,
                $name === ''
                    ? "Still there? 😊 We were in the middle of setting something up — just reply with the *number* of the option you'd like, or ask me anything. Type *menu* to start over."
                    : "Still there, {$name}? 😊 We were in the middle of setting something up — just reply with the *number* of the option you'd like, or ask me anything. Type *menu* to start over.",
                ['handled_by' => 'system', 'intent' => 'stall_nudge']
            );

            $session->update(['nudged_at' => now()]);
            $sent++;
        }

        $this->info("Nudged {$sent} stalled conversation(s).");

        return self::SUCCESS;
    }

    private function firstName(?string $name): string
    {
        return trim(explode(' ', trim((string) $name))[0] ?? '');
    }
}
