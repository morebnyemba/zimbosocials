<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppSavedOrder;
use App\WhatsApp\Messaging\Responder;
use Illuminate\Console\Command;

/**
 * Recover stalled purchases: a customer built an order but couldn't place it for
 * lack of funds (it's saved), then never topped up. Send ONE friendly reminder
 * that their order is ready the moment they deposit — within the 24h window so
 * it's deliverable as free-form text. Fires once per saved order (reminded_at).
 */
class RemindSavedWhatsAppOrders extends Command
{
    protected $signature = 'whatsapp:remind-saved-orders';

    protected $description = 'Remind WhatsApp customers to top up so their saved order can be placed';

    // Wait a bit (they may top up on their own), but stay inside the 24h window.
    private const MIN_AGE_HOURS = 1;
    private const MAX_AGE_HOURS = 20;

    public function handle(Responder $responder): int
    {
        $saved = WhatsAppSavedOrder::query()
            ->with('service')
            ->whereNull('reminded_at')
            ->where('created_at', '<=', now()->subHours(self::MIN_AGE_HOURS))
            ->where('created_at', '>=', now()->subHours(self::MAX_AGE_HOURS))
            ->limit(200)
            ->get();

        $sent = 0;

        foreach ($saved as $order) {
            $service = $order->service;
            $user = User::find($order->user_id);
            $account = WhatsAppAccount::where('wa_phone', $order->wa_phone)->first();

            // Gone stale, or not reachable right now — mark reminded so we don't
            // keep reconsidering it every run.
            if (! $service || ! $user || ! $account || ! $account->opted_in || $account->inAgentHandoff()) {
                $order->update(['reminded_at' => now()]);

                continue;
            }

            $charge = $service->calculateCharge((int) $order->quantity);
            $cur = $user->currency ?? 'USD';

            // Already funded (a resume should have fired) — clear it, don't nag.
            if ((float) $user->balance >= $charge) {
                WhatsAppSavedOrder::where('id', $order->id)->delete();

                continue;
            }

            $qty = number_format((int) $order->quantity);
            $amount = number_format($charge, 2);
            $name = (string) ($account->firstName() ?? '');
            $hi = $name === '' ? 'Hi!' : "Hi {$name}!";

            $responder->send(
                $order->wa_phone,
                "{$hi} 👋 Your *{$qty} {$service->name}* order is saved and ready — top up just *{$amount} {$cur}* and I'll place it for you right away. Reply *deposit* to finish. 💰",
                ['handled_by' => 'system', 'intent' => 'saved_order_reminder']
            );

            $order->update(['reminded_at' => now()]);
            $sent++;
        }

        $this->info("Reminded {$sent} saved order(s).");

        return self::SUCCESS;
    }
}
