<?php

namespace App\WhatsApp\Flow\Definitions;

use App\Models\Transaction;
use App\WhatsApp\Flow\AbstractFlow;
use App\WhatsApp\Flow\FlowResult;
use App\WhatsApp\Session\SessionContext;

/** Lists the user's most recent wallet transactions. Flow id: 'history'. */
class TransactionHistoryFlow extends AbstractFlow
{
    public function id(): string
    {
        return 'history';
    }

    public function prompt(string $state, SessionContext $ctx): FlowResult
    {
        $user = $this->user($ctx);
        if (! $user) {
            return FlowResult::fail("⚠️ Couldn't load your history. Type *menu*.");
        }

        $cur = $user->currency ?? 'USD';
        $txns = Transaction::where('user_id', $user->id)
            ->latest()
            ->limit(6)
            ->get();

        if ($txns->isEmpty()) {
            return FlowResult::complete("🧾 No transactions yet.\n\nType *deposit* to add funds.");
        }

        $msg = "🧾 *Recent transactions*\n\n";
        $msg .= $txns->map(fn (Transaction $t) => $this->rb->transactionLine($t, $cur))->implode("\n\n");

        return FlowResult::complete($msg);
    }

    public function handle(string $state, string $input, SessionContext $ctx): FlowResult
    {
        return $this->prompt($state, $ctx);
    }
}
