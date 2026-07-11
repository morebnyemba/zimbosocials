<?php

namespace App\WhatsApp\Flow\Definitions;

use App\Models\Order;
use App\WhatsApp\Flow\AbstractFlow;
use App\WhatsApp\Flow\FlowResult;
use App\WhatsApp\Session\SessionContext;

/** One-shot account summary. Flow id: 'profile'. */
class ProfileFlow extends AbstractFlow
{
    public function id(): string
    {
        return 'profile';
    }

    public function prompt(string $state, SessionContext $ctx): FlowResult
    {
        $user = $this->user($ctx);
        if (! $user) {
            return FlowResult::fail("⚠️ Couldn't load your profile. Type *menu*.");
        }

        $cur = $user->currency ?? 'USD';
        $orders = Order::where('user_id', $user->id)->count();

        $msg = "👤 *Your profile*\n\n";
        $msg .= "Name: {$user->name}\n";
        $msg .= "Email: {$user->email}\n";
        $msg .= 'Balance: '.$this->money((float) $user->balance, $cur)."\n";
        $msg .= "Orders: {$orders}\n";
        if ($user->referral_code) {
            $msg .= "Referral code: *{$user->referral_code}*\n";
        }
        $msg .= 'Member since: '.$user->created_at?->format('M Y');

        return FlowResult::complete($msg);
    }

    public function handle(string $state, string $input, SessionContext $ctx): FlowResult
    {
        return $this->prompt($state, $ctx);
    }
}
