<?php

namespace App\WhatsApp\Flow\Definitions;

use App\Models\Order;
use App\WhatsApp\Flow\AbstractFlow;
use App\WhatsApp\Flow\FlowResult;
use App\WhatsApp\Session\SessionContext;

/** Lists the user's most recent orders. Flow id: 'my_orders'. */
class MyOrdersFlow extends AbstractFlow
{
    public function id(): string
    {
        return 'my_orders';
    }

    public function prompt(string $state, SessionContext $ctx): FlowResult
    {
        $user = $this->user($ctx);
        if (! $user) {
            return FlowResult::fail("⚠️ Couldn't load your orders. Type *menu*.");
        }

        $cur = $user->currency ?? 'USD';
        $orders = Order::with('service')
            ->where('user_id', $user->id)
            ->latest()
            ->limit(5)
            ->get();

        if ($orders->isEmpty()) {
            return FlowResult::complete("📦 You have no orders yet.\n\nType *services* to browse and place your first order.");
        }

        $msg = "📦 *Your recent orders*\n\n";
        $msg .= $orders->map(fn (Order $o) => $this->rb->orderLine($o, $cur))->implode("\n\n");
        $msg .= "\n\nType *track* to track a specific order.";

        return FlowResult::complete($msg);
    }

    public function handle(string $state, string $input, SessionContext $ctx): FlowResult
    {
        return $this->prompt($state, $ctx);
    }
}
