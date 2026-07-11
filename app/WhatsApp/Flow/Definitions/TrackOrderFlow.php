<?php

namespace App\WhatsApp\Flow\Definitions;

use App\Models\Order;
use App\WhatsApp\Flow\AbstractFlow;
use App\WhatsApp\Flow\FlowResult;
use App\WhatsApp\Session\SessionContext;

/**
 * Track a specific order by id. Flow id: 'track'.
 * States: start (ask for id) → lookup handles the reply.
 */
class TrackOrderFlow extends AbstractFlow
{
    public function id(): string
    {
        return 'track';
    }

    public function prompt(string $state, SessionContext $ctx): FlowResult
    {
        // If the AI layer pre-filled an order id, resolve immediately.
        $prefill = $ctx->pullPrefill('order_id');
        if ($prefill !== null && is_numeric($prefill)) {
            return $this->lookup((int) $prefill, $ctx);
        }

        return FlowResult::step("🔎 Which order? Send the *order number* (e.g. 1234).", 'awaiting_id');
    }

    public function handle(string $state, string $input, SessionContext $ctx): FlowResult
    {
        $id = (int) preg_replace('/\D+/', '', $input);
        if ($id <= 0) {
            return FlowResult::step("Please send a valid order number, or type *cancel*.", 'awaiting_id');
        }

        return $this->lookup($id, $ctx);
    }

    private function lookup(int $id, SessionContext $ctx): FlowResult
    {
        $user = $this->user($ctx);
        if (! $user) {
            return FlowResult::fail("⚠️ Please try again from the *menu*.");
        }

        $order = Order::with('service')->where('user_id', $user->id)->find($id);
        if (! $order) {
            return FlowResult::fail("❌ No order *#{$id}* found on your account. Type *orders* to see your orders.");
        }

        return FlowResult::complete($this->rb->orderCard($order, $user->currency ?? 'USD'));
    }
}
