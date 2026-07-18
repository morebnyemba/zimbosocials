<?php

namespace App\WhatsApp\Session;

use App\Models\WhatsAppSession;
use Illuminate\Support\Carbon;

/**
 * Loads and persists SessionContext against whatsapp_sessions rows. Sessions
 * idle longer than TTL_MINUTES are flagged expired: the flow is kept so the
 * router can offer resume/restart, but wasExpired signals the timeout.
 */
class SessionManager
{
    private const TTL_MINUTES = 30;

    public function load(string $phone): SessionContext
    {
        $ctx = new SessionContext($phone);

        $row = WhatsAppSession::where('wa_phone', $phone)->first();
        if (! $row) {
            return $ctx;
        }

        $expired = $row->expires_at !== null && Carbon::parse($row->expires_at)->isPast();

        $ctx->flow = $row->current_flow;
        $ctx->state = $row->current_state;
        $ctx->stateStack = is_array($row->state_stack) ? $row->state_stack : [];
        $ctx->context = is_array($row->context) ? $row->context : [];
        $ctx->wasExpired = $expired && $ctx->flow !== null;

        return $ctx;
    }

    public function save(SessionContext $ctx): void
    {
        $now = now();
        $status = $ctx->inFlow() ? 'active' : 'idle';

        WhatsAppSession::updateOrCreate(
            ['wa_phone' => $ctx->phone],
            [
                'current_flow' => $ctx->flow,
                'current_state' => $ctx->state,
                'state_stack' => $ctx->stateStack,
                'context' => $ctx->context,
                'status' => $status,
                'last_activity' => $now,
                'expires_at' => $now->copy()->addMinutes(self::TTL_MINUTES),
                // They just acted — clear any stall nudge so a *later* stall can
                // be nudged again.
                'nudged_at' => null,
            ]
        );
    }
}
