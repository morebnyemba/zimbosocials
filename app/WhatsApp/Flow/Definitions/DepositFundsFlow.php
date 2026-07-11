<?php

namespace App\WhatsApp\Flow\Definitions;

use App\WhatsApp\Flow\AbstractFlow;
use App\WhatsApp\Flow\FlowResult;
use App\WhatsApp\Session\SessionContext;

/**
 * Deposit funds. Flow id: 'deposit'.
 *
 * Guided handoff by design: we collect the amount then send the user to the
 * tested, webhook-backed wallet top-up page rather than duplicating Paynow's
 * mobile-money initiation (provider-specific OTP/PIN branches) inside chat.
 * A future enhancement can push an EcoCash prompt in-chat by reusing an
 * extracted DepositService::initiateMobile() — crediting is already handled by
 * the Paynow webhook.
 */
class DepositFundsFlow extends AbstractFlow
{
    public function prompt(string $state, SessionContext $ctx): FlowResult
    {
        return FlowResult::step("➕ *Add funds*\n\nHow much would you like to deposit? (enter an amount)", 'ask_amount');
    }

    public function id(): string
    {
        return 'deposit';
    }

    public function handle(string $state, string $input, SessionContext $ctx): FlowResult
    {
        $amount = (float) preg_replace('/[^0-9.]/', '', $input);
        if ($amount < 1) {
            return FlowResult::step('Please enter an amount of at least 1, or type *cancel*.', 'ask_amount');
        }
        if ($amount > 10000) {
            return FlowResult::step('Maximum deposit is 10,000. Enter a smaller amount, or type *cancel*.', 'ask_amount');
        }

        $user = $this->user($ctx);
        $cur = $user?->currency ?? 'USD';
        $url = url('/wallet');

        return FlowResult::complete(
            "💳 *Deposit ".$this->money($amount, $cur)."*\n\n"
            ."To complete your payment securely (EcoCash, card and more), open your wallet:\n{$url}\n\n"
            .'Your balance updates automatically once payment is confirmed. Type *balance* to check it.'
        );
    }
}
