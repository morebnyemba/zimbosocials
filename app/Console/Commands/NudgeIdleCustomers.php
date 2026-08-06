<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppSession;
use App\WhatsApp\AI\GeminiProvider;
use App\WhatsApp\Messaging\Responder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Three warm, AI-composed check-ins for anyone who went quiet — at 2h, 12h
 * and 23h since their last message, timed to land before Meta's 24-hour
 * free-form messaging window closes. Each tier fires once; nudge_tier and
 * nudged_at reset the moment the customer replies (SessionManager::save()),
 * so a later stall gets its own fresh set of three.
 *
 * Runs for linked customers AND unlinked guests alike — a guest mid-order
 * (or just chatting) deserves the same follow-up as a registered one. It
 * still overlaps deliberately with whatsapp:flag-idle-leads, which alerts a
 * HUMAN much earlier (~20 min) for brand-new, barely-engaged contacts; this
 * command's own agent-handoff check means once a human has actually taken
 * over, the AI stops sending these.
 *
 * Usage:    php artisan whatsapp:nudge-idle-customers
 * Schedule: every 15 minutes (see routes/console.php)
 */
class NudgeIdleCustomers extends Command
{
    protected $signature = 'whatsapp:nudge-idle-customers';

    protected $description = 'Send an AI-composed re-engagement message to anyone who went quiet, before the 24h window closes';

    /** tier => hours since last message. Checked highest-first so a long-idle customer gets the right tone, not tier 1. */
    private const TIERS = [3 => 23, 2 => 12, 1 => 2];

    private const MAX_PER_RUN = 15;

    public function handle(GeminiProvider $ai, Responder $responder): int
    {
        if (! $ai->isConfigured()) {
            $this->warn('Gemini not configured — skipping (these messages are always AI-composed, never a canned fallback).');

            return self::SUCCESS;
        }

        $sessions = WhatsAppSession::query()
            ->where('last_activity', '<=', now()->subHours(2))
            ->where('last_activity', '>=', now()->subHours(24))
            ->orderBy('last_activity')
            ->limit(300)
            ->get();

        $sent = 0;

        foreach ($sessions as $session) {
            if ($sent >= self::MAX_PER_RUN) {
                break;
            }

            $account = WhatsAppAccount::where('wa_phone', $session->wa_phone)->first();
            if (! $account || ! $account->opted_in || $account->inAgentHandoff()) {
                continue;
            }

            // Free-form message — Meta only delivers it inside the 24h window.
            // Should always hold true here given the query bounds; checked
            // anyway since it's the one thing that actually gates delivery.
            if (! WhatsAppAccount::serviceWindowOpen($session->wa_phone)) {
                continue;
            }

            // Recently frustrated is not the same as quiet. Someone who just
            // went through (or is mid-) a loop escalation to a human — even if
            // that 2h handoff window has since lapsed — went idle BECAUSE the
            // bot was already the problem; an automated "still there?" on top
            // of that reads as the same bot that just failed them pretending
            // nothing happened. Give a human a full day to actually pick it up
            // before the bot re-approaches. See WhatsAppLoopBreakerTest and
            // MessageRouter::breakLoop().
            if ($this->recentlyEscalated($account, $session)) {
                continue;
            }

            $elapsedHours = $session->last_activity->diffInHours(now());
            $tier = null;
            foreach (self::TIERS as $t => $hours) {
                if ($elapsedHours >= $hours) {
                    $tier = $t;
                    break;
                }
            }
            if ($tier === null || (int) $session->nudge_tier >= $tier) {
                continue;
            }

            $message = $ai->reengagementMessage([
                'name' => $account->friendlyName(),
                'situation' => $this->situationFor($session),
                'tier' => $tier,
            ]);

            if ($message === null) {
                Log::info('Re-engagement message skipped — AI did not compose one', [
                    'phone' => $session->wa_phone, 'tier' => $tier,
                ]);

                continue;
            }

            $responder->send($session->wa_phone, $message, [
                'handled_by' => 'ai', 'ai_used' => true, 'intent' => 'reengagement_tier_'.$tier,
            ]);

            $session->forceFill(['nudge_tier' => $tier, 'nudged_at' => now()])->save();
            $sent++;
        }

        $this->info("Nudged {$sent} customer(s).");

        return self::SUCCESS;
    }

    /**
     * True if this contact was escalated to a human within the last 24h
     * (loop handoff or otherwise), or the bot itself already flagged the
     * conversation as repeating before they went quiet. Either way, a human
     * — not another automated message — is the right next move.
     */
    private function recentlyEscalated(WhatsAppAccount $account, WhatsAppSession $session): bool
    {
        if ($account->agent_handoff_until !== null && $account->agent_handoff_until->gt(now()->subDay())) {
            return true;
        }

        $context = (array) $session->context;

        return (bool) ($context['_loop_nudged'] ?? false);
    }

    /** Ground the AI in what the customer was actually doing before they went quiet. */
    private function situationFor(WhatsAppSession $session): string
    {
        $context = (array) $session->context;

        if ($session->current_flow === 'order' && $session->current_state === 'confirm') {
            $qty = $context['order_quantity'] ?? null;
            $serviceName = ! empty($context['order_service_id'])
                ? Service::find($context['order_service_id'])?->name
                : null;
            if ($serviceName) {
                return 'They were quoted a price for '.($qty ? number_format((float) $qty).' ' : '').$serviceName
                    .' and went quiet before confirming the order.';
            }
        }

        if ($session->current_flow !== null) {
            return 'They were partway through '.str_replace('_', ' ', (string) $session->current_flow).' and went quiet.';
        }

        $history = (array) ($context['_ai_history'] ?? []);
        $last = end($history);
        if (is_array($last) && ! empty($last['user'])) {
            return 'Their last message was: "'.mb_substr((string) $last['user'], 0, 140).'" — they have not replied since.';
        }

        return 'They messaged before and went quiet, with no specific pending task.';
    }
}
