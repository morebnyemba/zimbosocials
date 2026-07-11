<?php

namespace App\WhatsApp\Messaging;

use App\Models\Order;
use App\Models\Transaction;

/**
 * Shared text formatting for assistant replies (order cards, transaction lines,
 * status emojis). Keeps flow classes focused on logic rather than presentation.
 */
class ResponseBuilder
{
    public function money(float $amount, string $currency = 'USD'): string
    {
        return number_format($amount, 2).' '.$currency;
    }

    public function statusEmoji(string $status): string
    {
        return match ($status) {
            'completed' => '✅',
            'processing', 'in_progress' => '⏳',
            'pending' => '🕒',
            'partial' => '📦',
            'canceled', 'cancelled' => '✖️',
            'refunded' => '↩️',
            default => '•',
        };
    }

    /** One-line summary of an order for a list. */
    public function orderLine(Order $order, string $currency = 'USD'): string
    {
        $svc = $order->service?->name ?? 'Service #'.$order->service_id;

        return $this->statusEmoji($order->status)." *#{$order->id}* — {$svc}\n"
            ."   {$order->quantity} · ".$this->money((float) $order->charge, $currency)
            .' · '.ucfirst(str_replace('_', ' ', $order->status));
    }

    /** Detailed order card for tracking. */
    public function orderCard(Order $order, string $currency = 'USD'): string
    {
        $svc = $order->service?->name ?? 'Service #'.$order->service_id;
        $msg = "📦 *Order #{$order->id}*\n\n";
        $msg .= "Service: {$svc}\n";
        $msg .= "Quantity: {$order->quantity}\n";
        $msg .= 'Charge: '.$this->money((float) $order->charge, $currency)."\n";
        $msg .= 'Status: '.$this->statusEmoji($order->status).' '.ucfirst(str_replace('_', ' ', $order->status))."\n";
        if ($order->link) {
            $msg .= "Link: {$order->link}\n";
        }
        $msg .= 'Placed: '.$order->created_at?->diffForHumans();

        return $msg;
    }

    public function transactionLine(Transaction $t, string $currency = 'USD'): string
    {
        $sign = $t->amount >= 0 ? '+' : '−';
        $label = ucfirst(str_replace('_', ' ', (string) $t->type));

        return "{$sign}".$this->money(abs((float) $t->amount), $currency)." · {$label}\n"
            .'   '.($t->created_at?->format('d M Y').' · '.ucfirst((string) $t->status));
    }
}
