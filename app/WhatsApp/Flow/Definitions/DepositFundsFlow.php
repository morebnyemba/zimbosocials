<?php

namespace App\WhatsApp\Flow\Definitions;

use App\Models\ManualPaymentDetail;
use App\WhatsApp\Flow\AbstractFlow;
use App\WhatsApp\Flow\FlowResult;
use App\WhatsApp\Session\SessionContext;

/**
 * Deposit funds. Flow id: 'deposit'.
 * States: ask_amount → choose_method → guidance.
 *
 * Lists the panel's real payment methods (ManualPaymentDetail) so the user
 * picks one. Gateway (Paynow) methods are completed on the secure wallet page;
 * manual methods show the account details + a submit-proof link. We never take
 * card/mobile-money credentials in chat.
 */
class DepositFundsFlow extends AbstractFlow
{
    public function id(): string
    {
        return 'deposit';
    }

    public function prompt(string $state, SessionContext $ctx): FlowResult
    {
        // AI fast-forward: if an amount was extracted, skip straight to methods.
        $amount = (float) preg_replace('/[^0-9.]/', '', (string) $ctx->pullPrefill('amount'));
        if ($amount >= 1 && $amount <= 10000) {
            $ctx->set('deposit_amount', $amount);

            return $this->showMethods($amount, $ctx);
        }

        return FlowResult::step("➕ *Add funds*\n\nHow much would you like to deposit? (enter an amount)", 'ask_amount');
    }

    public function handle(string $state, string $input, SessionContext $ctx): FlowResult
    {
        if ($state === 'choose_method') {
            return $this->pickMethod($input, $ctx);
        }

        // ask_amount
        $amount = (float) preg_replace('/[^0-9.]/', '', $input);
        if ($amount < 1) {
            return FlowResult::step('Please enter an amount of at least 1, or type *cancel*.', 'ask_amount');
        }
        if ($amount > 10000) {
            return FlowResult::step('Maximum deposit is 10,000. Enter a smaller amount, or type *cancel*.', 'ask_amount');
        }

        $ctx->set('deposit_amount', $amount);

        return $this->showMethods($amount, $ctx);
    }

    private function showMethods(float $amount, SessionContext $ctx): FlowResult
    {
        $methods = ManualPaymentDetail::active()->ordered()->get();

        // No methods configured → fall back to the wallet page.
        if ($methods->isEmpty()) {
            return $this->walletFallback($amount);
        }

        $ctx->set('deposit_methods', $methods->pluck('method_key')->all());
        $user = $this->user($ctx);
        $cur = $user?->currency ?? 'USD';

        $list = $methods->values()->map(fn ($m, $i) => ($i + 1).". {$m->label}")->implode("\n");

        return FlowResult::step(
            "💳 *Deposit ".$this->money($amount, $cur)."*\n\nHow would you like to pay? Reply with a number:\n\n{$list}",
            'choose_method'
        );
    }

    private function pickMethod(string $input, SessionContext $ctx): FlowResult
    {
        $keys = collect($ctx->get('deposit_methods', []));
        $idx = (int) preg_replace('/\D+/', '', $input) - 1;
        $methodKey = $keys->get($idx);
        if (! $methodKey) {
            return FlowResult::step('Please reply with a valid number, or type *cancel*.', 'choose_method');
        }

        $method = ManualPaymentDetail::active()->where('method_key', $methodKey)->first();
        $amount = (float) $ctx->get('deposit_amount', 0);
        $user = $this->user($ctx);
        $cur = $user?->currency ?? 'USD';
        $url = url('/wallet');

        if (! $method) {
            return $this->walletFallback($amount);
        }

        // Gateway methods (Paynow) are completed online.
        if ($method->gateway_type === 'paynow') {
            return FlowResult::complete(
                "💳 *".$this->money($amount, $cur)." via {$method->label}*\n\n"
                ."Open your wallet to complete payment securely:\n{$url}\n\n"
                .'Choose *'.$method->label.'* there — your balance updates automatically once payment is confirmed.'
            );
        }

        // Manual methods — show the account details + submit-proof link.
        $msg = "💳 *".$this->money($amount, $cur)." via {$method->label}*\n\n";
        if ($method->account_name) {
            $msg .= "Account name: *{$method->account_name}*\n";
        }
        if ($method->account_number) {
            $msg .= "Account/Number: *{$method->account_number}*\n";
        }
        if ($method->instructions) {
            $msg .= "\n{$method->instructions}\n";
        }
        $msg .= "\nAfter paying, submit your proof of payment here:\n{$url}";

        return FlowResult::complete($msg);
    }

    private function walletFallback(float $amount): FlowResult
    {
        $url = url('/wallet');

        return FlowResult::complete(
            "💳 *Deposit*\n\nOpen your wallet to add funds securely (EcoCash, card and more):\n{$url}\n\n"
            .'Your balance updates automatically once payment is confirmed.'
        );
    }
}
