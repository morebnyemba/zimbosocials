<?php

namespace App\WhatsApp\Flow\Definitions;

use App\Models\User;
use App\Support\ReferralLink;
use App\WhatsApp\Flow\AbstractFlow;
use App\WhatsApp\Flow\FlowResult;
use App\WhatsApp\ReferralNudge;
use App\WhatsApp\Session\SessionContext;

/**
 * Show the user their referral link, the program's rewards, and their
 * progress. Flow id: 'referral'. Single-shot (no states).
 */
class ReferralFlow extends AbstractFlow
{
    public function id(): string
    {
        return 'referral';
    }

    public function prompt(string $state, SessionContext $ctx): FlowResult
    {
        $user = $this->user($ctx);
        if (! $user) {
            return FlowResult::fail('⚠️ Please try again from the *menu*.');
        }

        $link = ReferralLink::for($user);
        $referredCount = User::where('referred_by', $user->id)->count();
        $cur = $user->currency ?? 'USD';

        $reward = number_format((float) config('services.referral.first_deposit_reward', 1.00), 2);
        $commission = rtrim(rtrim(number_format((float) config('services.referral.order_commission_percent', 2.00), 2), '0'), '.');
        $friendBonus = rtrim(rtrim(number_format((float) config('services.referral.referred_first_deposit_bonus_percent', 10.00), 2), '0'), '.');
        $minDeposit = number_format((float) config('services.referral.min_qualifying_deposit', 5.00), 2);

        $msg = "🎁 *Invite friends, earn money*\n\n";
        $msg .= "How it works:\n";
        $msg .= "• Your friend gets a *{$friendBonus}% bonus* on their first deposit\n";
        $msg .= "• You earn *{$reward} {$cur}* when they make their first deposit (min {$minDeposit} {$cur})\n";
        $msg .= "• Plus *{$commission}% commission* on their orders — ongoing 💰\n\n";
        $msg .= $referredCount > 0
            ? "You've invited *{$referredCount}* ".($referredCount === 1 ? 'friend' : 'friends')." so far. Your earnings show in *history*.\n\n"
            : '';
        $msg .= "Your personal link — forward it to friends:\n{$link}";

        // They've just seen the full pitch — pause organic nudges for a while.
        ReferralNudge::mark($ctx->phone);

        return FlowResult::complete($msg);
    }

    public function handle(string $state, string $input, SessionContext $ctx): FlowResult
    {
        return $this->prompt($state, $ctx);
    }
}
