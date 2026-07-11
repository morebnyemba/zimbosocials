<?php

namespace App\WhatsApp\Flow\Definitions;

use App\WhatsApp\Auth\WhatsAppRegistrar;
use App\WhatsApp\Flow\AbstractFlow;
use App\WhatsApp\Flow\FlowResult;
use App\WhatsApp\Messaging\ResponseBuilder;
use App\WhatsApp\Session\SessionContext;

/**
 * Guided sign-up: collect name → email → create the account. Flow id: 'register'.
 * A guest hitting an auth-gated action is routed here by the router.
 */
class RegistrationFlow extends AbstractFlow
{
    public function __construct(ResponseBuilder $rb, private readonly WhatsAppRegistrar $registrar)
    {
        parent::__construct($rb);
    }

    public function id(): string
    {
        return 'register';
    }

    public function authRequired(): bool
    {
        return false;
    }

    public function prompt(string $state, SessionContext $ctx): FlowResult
    {
        return match ($state) {
            'ask_email' => FlowResult::step("📧 Great! What's your *email address*?", 'ask_email'),
            default => FlowResult::step("📝 *Let's create your account.*\n\nWhat's your *full name*?", 'ask_name'),
        };
    }

    public function handle(string $state, string $input, SessionContext $ctx): FlowResult
    {
        $input = trim($input);

        if ($state === 'ask_name') {
            if (mb_strlen($input) < 2) {
                return FlowResult::step("Please enter your full name (at least 2 characters).", 'ask_name');
            }
            $ctx->set('reg_name', $input);

            return FlowResult::step("📧 Thanks, *{$input}*! What's your *email address*?", 'ask_email');
        }

        if ($state === 'ask_email') {
            if (! filter_var($input, FILTER_VALIDATE_EMAIL)) {
                return FlowResult::step("That doesn't look like a valid email. Please try again, or type *cancel*.", 'ask_email');
            }

            $res = $this->registrar->register($ctx->phone, (string) $ctx->get('reg_name', 'WhatsApp User'), $input);

            if (! empty($res['ok'])) {
                $ctx->set('_user_id', $res['user']->id);

                return FlowResult::complete("✅ *Welcome aboard!* Your account is ready.\n\nYou can now browse services, place orders and top up your wallet right here. Type *menu* to begin.");
            }

            if (($res['error'] ?? null) === 'email_taken') {
                return FlowResult::fail("📩 That email already has an account. Type *link* to connect this WhatsApp number to it instead.");
            }

            return FlowResult::fail("⚠️ Sorry, something went wrong creating your account. Please try again later.");
        }

        return $this->prompt('start', $ctx);
    }
}
