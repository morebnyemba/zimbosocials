<?php

namespace App\WhatsApp\Flow\Definitions;

use App\WhatsApp\Flow\AbstractFlow;
use App\WhatsApp\Flow\FlowResult;
use App\WhatsApp\Session\SessionContext;

/** One-shot wallet balance card. Flow id: 'balance'. */
class WalletBalanceFlow extends AbstractFlow
{
    public function id(): string
    {
        return 'balance';
    }

    public function prompt(string $state, SessionContext $ctx): FlowResult
    {
        $user = $this->user($ctx);
        if (! $user) {
            return FlowResult::fail("⚠️ Couldn't load your balance. Type *menu* to try again.");
        }

        $cur = $user->currency ?? 'USD';
        $msg = "💰 *Wallet Balance*\n\n";
        $msg .= 'Balance: *'.$this->money((float) $user->balance, $cur)."*\n\n";
        $msg .= 'Type *deposit* to add funds.';

        return FlowResult::complete($msg);
    }

    public function handle(string $state, string $input, SessionContext $ctx): FlowResult
    {
        return $this->prompt($state, $ctx);
    }
}
