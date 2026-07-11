<?php

namespace App\WhatsApp\Flow\Definitions;

use App\Models\User;
use App\Models\WhatsAppAccount;
use App\WhatsApp\Flow\AbstractFlow;
use App\WhatsApp\Flow\FlowResult;
use App\WhatsApp\Session\SessionContext;
use Illuminate\Support\Facades\Mail;

/**
 * Links this WhatsApp number to an existing web account via an email OTP.
 * Flow id: 'link' (also serves 'login'). States: ask_email → ask_otp.
 */
class LinkAccountFlow extends AbstractFlow
{
    private const OTP_TTL_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;

    public function id(): string
    {
        return 'link';
    }

    public function authRequired(): bool
    {
        return false;
    }

    public function prompt(string $state, SessionContext $ctx): FlowResult
    {
        if ($state === 'ask_otp') {
            return FlowResult::step("Enter the 6-digit code we emailed you.", 'ask_otp');
        }

        // AI fast-forward: if an email was extracted, send the code straight away.
        $email = trim((string) $ctx->pullPrefill('email'));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->handle('ask_email', $email, $ctx);
        }

        return FlowResult::step("🔗 *Link your account*\n\nWhat's the *email* on your existing account?", 'ask_email');
    }

    public function handle(string $state, string $input, SessionContext $ctx): FlowResult
    {
        $input = trim($input);

        if ($state === 'ask_email') {
            if (! filter_var($input, FILTER_VALIDATE_EMAIL)) {
                return FlowResult::step("That doesn't look like a valid email. Try again, or type *cancel*.", 'ask_email');
            }

            $user = User::where('email', mb_strtolower($input))->first();
            if (! $user) {
                return FlowResult::fail("📩 No account found with that email. Type *register* to create one.");
            }

            $otp = (string) random_int(100000, 999999);
            WhatsAppAccount::where('wa_phone', $ctx->phone)->update([
                'link_status' => 'pending_link',
                'link_otp' => $otp,
                'link_otp_expires' => now()->addMinutes(self::OTP_TTL_MINUTES),
                'link_attempts' => 0,
            ]);
            $ctx->set('link_user_id', $user->id);

            $this->sendOtpEmail($user, $otp);

            return FlowResult::step(
                "📨 We sent a 6-digit code to *{$this->maskEmail($user->email)}*.\n\nEnter it here to finish linking. (Expires in ".self::OTP_TTL_MINUTES." minutes.)",
                'ask_otp'
            );
        }

        if ($state === 'ask_otp') {
            $account = WhatsAppAccount::where('wa_phone', $ctx->phone)->first();
            $targetId = (int) $ctx->get('link_user_id');
            if (! $account || ! $targetId) {
                return FlowResult::fail("Something went wrong. Type *link* to start again.");
            }

            if ($account->link_attempts >= self::MAX_ATTEMPTS) {
                $account->update(['link_otp' => null, 'link_status' => 'guest']);

                return FlowResult::fail("🚫 Too many attempts. Type *link* to try again later.");
            }

            $account->increment('link_attempts');

            $code = preg_replace('/\D+/', '', $input);
            $expired = $account->link_otp_expires === null || $account->link_otp_expires->isPast();

            if ($expired) {
                return FlowResult::fail("⌛ That code has expired. Type *link* to request a new one.");
            }
            if (! $account->link_otp || ! hash_equals($account->link_otp, $code)) {
                $left = self::MAX_ATTEMPTS - $account->link_attempts;

                return FlowResult::step("❌ Incorrect code. {$left} attempt(s) left — try again, or type *cancel*.", 'ask_otp');
            }

            // Success — bind the number.
            $account->update([
                'user_id' => $targetId,
                'link_status' => 'linked',
                'link_otp' => null,
                'link_otp_expires' => null,
                'link_attempts' => 0,
            ]);

            $user = User::find($targetId);
            if ($user && empty($user->whatsapp_number)) {
                $user->forceFill(['whatsapp_number' => $ctx->phone, 'phone' => $user->phone ?: $ctx->phone])->save();
            }
            $ctx->set('_user_id', $targetId);

            return FlowResult::complete("✅ *Linked!* This number is now connected to your account. Type *menu* to get started.");
        }

        return $this->prompt('ask_email', $ctx);
    }

    private function sendOtpEmail(User $user, string $otp): void
    {
        try {
            Mail::raw(
                "Your account-linking code is: {$otp}\n\nIt expires in ".self::OTP_TTL_MINUTES." minutes. If you didn't request this, you can ignore this email.",
                function ($message) use ($user): void {
                    $message->to($user->email)->subject('Your WhatsApp linking code');
                }
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Link OTP email failed', ['message' => $e->getMessage()]);
        }
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $shown = mb_substr($local, 0, 1);

        return $shown.str_repeat('*', max(1, mb_strlen($local) - 1)).'@'.$domain;
    }
}
