<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\WhatsApp\AdminNudge;
use Illuminate\Console\Command;

/**
 * Flags people who made first contact — usually straight off an advert — and
 * then went quiet without buying anything.
 *
 * These are the most expensive leads we have: the ad has already been paid for
 * and they raised their hand. A person messaging them back within the hour
 * converts a decent share of them, but only if someone knows they exist. Each
 * alert carries a wa.me link so following up is one tap.
 *
 * Usage:    php artisan whatsapp:flag-idle-leads [--quiet-for=20] [--within=24]
 * Schedule: every ten minutes (see routes/console.php)
 */
class FlagIdleLeads extends Command
{
    protected $signature = 'whatsapp:flag-idle-leads
        {--quiet-for=20 : Minutes of silence before a new lead is flagged}
        {--within=24 : Only consider contacts first seen in the last N hours}';

    protected $description = 'Alert the team about new WhatsApp leads who went quiet without ordering';

    public function handle(): int
    {
        $quietFor = max(5, (int) $this->option('quiet-for'));
        $within = max(1, (int) $this->option('within'));

        $candidates = WhatsAppAccount::query()
            ->where('created_at', '>=', now()->subHours($within))
            // Silent for a while, but not so long that following up is odd.
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '<=', now()->subMinutes($quietFor))
            ->where('last_seen_at', '>=', now()->subHours($within))
            ->get();

        $flagged = 0;

        foreach ($candidates as $account) {
            // Someone who bought isn't a dropped lead.
            if ($account->user_id && Order::where('user_id', $account->user_id)->exists()) {
                continue;
            }

            // A human is already in this conversation.
            if ($account->inAgentHandoff()) {
                continue;
            }

            $inbound = WhatsAppMessage::where('wa_phone', $account->wa_phone)
                ->where('direction', 'in')
                ->count();

            // One or two messages then silence is a dropped lead. A longer
            // conversation that paused is a different thing, and nagging the
            // team about those would bury the ones that matter.
            if ($inbound === 0 || $inbound > 3) {
                continue;
            }

            $name = $account->friendlyName();
            $who = $name !== null ? "{$name} " : '';
            $reason = "new lead — {$who}sent {$inbound} message"
                .($inbound === 1 ? '' : 's').' then went quiet, no order yet';

            // AdminNudge dedupes, so re-running this never re-alerts.
            if (AdminNudge::raise((string) $account->wa_phone, $reason, 'idle-lead')) {
                $flagged++;
            }
        }

        $this->info("Checked {$candidates->count()} recent contact(s); flagged {$flagged}.");

        return self::SUCCESS;
    }
}
