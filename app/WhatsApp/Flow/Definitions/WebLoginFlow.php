<?php

namespace App\WhatsApp\Flow\Definitions;

use App\Models\WebLoginToken;
use App\WhatsApp\Flow\AbstractFlow;
use App\WhatsApp\Flow\FlowResult;
use App\WhatsApp\Session\SessionContext;
use Illuminate\Support\Facades\Cache;

/**
 * One-shot: mint a single-use, short-lived link that logs the customer
 * straight into the website — no password needed. Flow id: 'weblogin'.
 *
 * Only reachable from an already-authenticated WhatsApp session (this number
 * was already OTP-linked or registered), so the link rides on that existing
 * proof of identity rather than handing out web access to anyone who can send
 * a message from the number.
 */
class WebLoginFlow extends AbstractFlow
{
    public function id(): string
    {
        return 'weblogin';
    }

    public function prompt(string $state, SessionContext $ctx): FlowResult
    {
        $user = $this->user($ctx);
        if (! $user) {
            return FlowResult::fail("⚠️ Couldn't verify your account. Type *menu* to try again.");
        }

        // One link at a time is plenty — stops a double-tap from burning
        // through tokens (and WhatsApp messages) for no reason.
        $key = 'weblogin:issue:'.$ctx->phone;
        if (! Cache::add($key, true, 30)) {
            return FlowResult::complete('⏳ Already sent one — check above, or wait a few seconds and try again.');
        }

        $token = WebLoginToken::issue((int) $user->id, $ctx->phone);
        $link = url('/wa-login/'.$token);
        $ttl = WebLoginToken::ttlMinutes();

        return FlowResult::complete(
            "🔓 *Your one-tap login link*\n\n{$link}\n\n"
            .'This logs you straight into your account on the website — no password needed. '
            ."It works *once* and expires in {$ttl} minutes."
        );
    }

    public function handle(string $state, string $input, SessionContext $ctx): FlowResult
    {
        return $this->prompt($state, $ctx);
    }
}
