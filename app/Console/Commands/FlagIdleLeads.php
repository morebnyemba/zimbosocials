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

    /**
     * Ceiling per run. A busy hour, or a first run against an existing table,
     * should not put fifty alerts on someone's phone at once — past a handful
     * they stop being read and the channel is worth nothing.
     */
    private const MAX_PER_RUN = 5;

    public function handle(): int
    {
        $quietFor = max(5, (int) $this->option('quiet-for'));
        $within = max(1, (int) $this->option('within'));

        $candidates = WhatsAppAccount::query()
            ->where('created_at', '>=', now()->subHours($within))
            // Each lead is raised ONCE, ever. This used to rely on a cache
            // entry, so the same lead came back every hour until it aged out —
            // and any deploy that cleared the cache re-reported all of them at
            // once.
            ->whereNull('lead_flagged_at')
            // Silent for a while, but not so long that following up is odd.
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '<=', now()->subMinutes($quietFor))
            ->where('last_seen_at', '>=', now()->subHours($within))
            // Oldest first, so a backlog is worked through in order rather than
            // the newest few starving the rest.
            ->orderBy('last_seen_at')
            ->limit(self::MAX_PER_RUN)
            ->get();

        $flagged = 0;

        foreach ($candidates as $account) {
            // Everything below settles this contact one way or the other, so
            // stamp it either way: a contact we decide NOT to alert on must not
            // be re-examined on every run for the next day.
            $account->forceFill(['lead_flagged_at' => now()])->save();

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

            if (AdminNudge::raise((string) $account->wa_phone, $reason, 'idle-lead')) {
                $flagged++;
            }
        }

        $this->info("Checked {$candidates->count()} contact(s); flagged {$flagged}.");

        return self::SUCCESS;
    }
}
