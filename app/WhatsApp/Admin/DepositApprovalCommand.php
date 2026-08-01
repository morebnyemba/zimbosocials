<?php

namespace App\WhatsApp\Admin;

use App\Models\Transaction;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\Services\DepositService;

/**
 * Lets an admin approve a pending manual deposit straight from WhatsApp —
 * "APPROVE 161" / "RECEIVED 161", or a bare "approve"/"received" as a swipe
 * reply to the alert itself, no id typed.
 *
 * Deliberately narrow: this is the ONLY WhatsApp path that moves money.
 * Every check below exists to keep a customer's "received" (a plain
 * window-keepalive, never parsed) from ever being mistaken for this, and to
 * keep a typo'd id from crediting the wrong deposit.
 */
class DepositApprovalCommand
{
    public function __construct(private readonly DepositService $deposits) {}

    /**
     * @return string|null  The reply to send back to the admin, or null if
     *                      this message isn't an approval attempt at all
     *                      (caller falls through to normal handling).
     */
    public function tryHandle(WhatsAppAccount $account, string $text, ?string $quotedWaMessageId): ?string
    {
        $user = $account->user;
        if (! $user || $user->role !== 'admin' || ! $user->is_active) {
            return null;
        }

        $id = $this->extractId($text, $quotedWaMessageId);
        if ($id === null) {
            return null;
        }

        $transaction = Transaction::find($id);
        if (! $transaction || $transaction->type !== 'deposit') {
            return "⚠️ No pending deposit found with id *#{$id}*.";
        }
        if ($transaction->status !== 'pending') {
            return "⚠️ Deposit *#{$id}* is already *{$transaction->status}* — nothing to approve.";
        }

        $credited = $this->deposits->credit($transaction, 'whatsapp_admin_approval');
        if (! $credited) {
            return "⚠️ Deposit *#{$id}* was already processed by someone else just now.";
        }

        $transaction->forceFill(['processed_by' => $user->id, 'processed_at' => now()])->save();

        $depositor = $transaction->user;
        $amount = number_format((float) $transaction->amount, 2);
        $cur = $depositor?->currency ?? 'USD';
        $balance = number_format((float) ($depositor?->fresh()->balance ?? 0), 2);
        $name = $depositor?->name ?? 'the customer';

        return "✅ Approved. Credited *{$amount} {$cur}* to {$name}'s wallet — new balance *{$balance} {$cur}*.";
    }

    /**
     * "APPROVE 161" / "RECEIVED #161" → the id is right there. A bare
     * "approve"/"received" with no id relies on the swipe-reply instead: pull
     * the id from OUR OWN alert text, which always carries it as "(#123)" or
     * "deposit #123" — never trust anything the admin didn't explicitly name
     * or reply to.
     */
    private function extractId(string $text, ?string $quotedWaMessageId): ?int
    {
        $t = trim($text);

        if (preg_match('/^(?:approve|received)\s*#?\s*(\d+)$/i', $t, $m)) {
            return (int) $m[1];
        }

        if (! preg_match('/^(?:approve|received)$/i', $t) || $quotedWaMessageId === null) {
            return null;
        }

        $quoted = WhatsAppMessage::where('wa_message_id', $quotedWaMessageId)
            ->where('direction', 'out')
            ->first();

        if (! $quoted || ! preg_match('/#(\d+)/', (string) $quoted->body, $m)) {
            return null;
        }

        return (int) $m[1];
    }
}
