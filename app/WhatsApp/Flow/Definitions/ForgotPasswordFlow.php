<?php

namespace App\WhatsApp\Flow\Definitions;

use App\WhatsApp\Flow\AbstractFlow;
use App\WhatsApp\Flow\FlowResult;
use App\WhatsApp\Session\SessionContext;
use Illuminate\Support\Facades\Password;

/**
 * Triggers a standard web password-reset email. Flow id: 'forgot'.
 * We always reply the same way regardless of whether the email exists, so the
 * bot never discloses which addresses have accounts.
 */
class ForgotPasswordFlow extends AbstractFlow
{
    public function id(): string
    {
        return 'forgot';
    }

    public function authRequired(): bool
    {
        return false;
    }

    public function prompt(string $state, SessionContext $ctx): FlowResult
    {
        return FlowResult::step("🔑 *Reset password*\n\nWhat's the *email* on your account?", 'ask_email');
    }

    public function handle(string $state, string $input, SessionContext $ctx): FlowResult
    {
        $input = trim($input);
        if (! filter_var($input, FILTER_VALIDATE_EMAIL)) {
            return FlowResult::retry("That doesn't look like a valid email. Try again, or type *cancel*.", 'ask_email');
        }

        try {
            Password::sendResetLink(['email' => mb_strtolower($input)]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('WA forgot-password reset link failed', ['message' => $e->getMessage()]);
        }

        // Uniform response — do not reveal whether the account exists.
        return FlowResult::complete("📨 If an account exists for *{$input}*, we've emailed a password-reset link. Check your inbox (and spam).");
    }
}
