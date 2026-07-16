<?php

namespace App\WhatsApp\Auth;

use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Creates a real app user from a guided WhatsApp sign-up (name + email) and
 * binds it to the phone's whatsapp_accounts row. Mirrors AuthController@register:
 * unique username, generated referral code + API key, welcome notification. A
 * random password is set; the user runs "forgot password" to set one for web.
 */
class WhatsAppRegistrar
{
    /** Synthetic mailbox domain for silently auto-registered WhatsApp users. */
    public static function autoEmailDomain(): string
    {
        return (string) config('services.whatsapp.auto_email_domain', 'zimbosocials.co.zw');
    }

    public static function autoEmailFor(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone).'@'.self::autoEmailDomain();
    }

    /** Whether an address is one of our synthetic auto-registration mailboxes (never send real mail there). */
    public static function isAutoEmail(?string $email): bool
    {
        return is_string($email) && str_ends_with(mb_strtolower($email), '@'.mb_strtolower(self::autoEmailDomain()));
    }

    /**
     * Silent background registration: create a real account keyed to the
     * synthetic {digits}@domain mailbox and bind the phone — no questions
     * asked, so the user jumps straight into business. Idempotent: if the
     * synthetic user already exists (re-contact after an unlink), it relinks.
     * No welcome email/WhatsApp blast — the conversation itself is the
     * onboarding; they can set a real email later via 'register' or 'link'.
     *
     * @return array{ok:bool, user?:User, error?:string}
     */
    public function autoRegister(string $phone, ?string $displayName = null): array
    {
        $email = self::autoEmailFor($phone);

        $existing = User::where('email', $email)->first();
        if ($existing) {
            $this->bindPhone($phone, $existing);

            return ['ok' => true, 'user' => $existing];
        }

        $name = trim((string) $displayName) !== '' ? trim((string) $displayName) : 'WhatsApp User';

        $result = $this->register($phone, $name, $email, silent: true);

        if (! empty($result['ok'])) {
            $this->safely(fn () => NotificationService::notifyAdmins(
                'admin_new_registration',
                'New User Auto-Registered (WhatsApp)',
                "{$result['user']->name} ({$phone}) was auto-registered via WhatsApp and jumped straight into the conversation.",
                ['user_name' => $result['user']->name, 'user_email' => $email, 'role' => 'user']
            ));
        }

        return $result;
    }

    /** @return array{ok:bool, user?:User, error?:string} */
    public function register(string $phone, string $name, string $email, bool $silent = false): array
    {
        $email = mb_strtolower(trim($email));

        if (User::where('email', $email)->exists()) {
            return ['ok' => false, 'error' => 'email_taken'];
        }

        try {
            $user = User::create([
                'name' => trim($name),
                'username' => $this->uniqueUsername($name, $email),
                'email' => $email,
                'whatsapp_number' => $phone,
                'phone' => $phone,
                'password' => Hash::make(Str::password(16)),
                'locale' => 'sn',
                'currency' => 'USD',
                'role' => 'user',
                'account_type' => 'individual',
                'referral_code' => User::generateReferralCode(),
            ]);

            $user->generateApiKey();

            $this->bindPhone($phone, $user);

            if (! $silent) {
                $this->safely(fn () => NotificationService::sendWelcome($user));
                $this->safely(fn () => NotificationService::notifyAdmins(
                    'admin_new_registration',
                    'New User Registered (WhatsApp)',
                    "{$user->name} ({$user->email}) signed up via WhatsApp.",
                    ['user_name' => $user->name, 'user_email' => $user->email, 'role' => $user->role]
                ));
            }

            return ['ok' => true, 'user' => $user];
        } catch (\Throwable $e) {
            Log::error('WhatsAppRegistrar failed', ['phone' => $phone, 'message' => $e->getMessage()]);

            return ['ok' => false, 'error' => 'server_error'];
        }
    }

    private function bindPhone(string $phone, User $user): void
    {
        WhatsAppAccount::where('wa_phone', $phone)->update([
            'user_id' => $user->id,
            'link_status' => 'linked',
            'link_otp' => null,
            'link_otp_expires' => null,
            'link_attempts' => 0,
        ]);
    }

    private function uniqueUsername(string $name, string $email): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9_]/', '', str_replace(' ', '_', strtolower($name))));
        if (strlen($base) < 4) {
            $base = strtolower(preg_replace('/[^a-z0-9]/', '', explode('@', $email)[0]));
        }
        if (strlen($base) < 4) {
            $base = 'wa'.Str::random(6);
        }
        $base = substr(trim($base, '_'), 0, 24);

        $username = $base;
        while (User::where('username', $username)->exists()) {
            $username = substr($base, 0, 24).Str::lower(Str::random(4));
        }

        return $username;
    }

    private function safely(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            Log::warning('WhatsAppRegistrar post-create hook failed', ['message' => $e->getMessage()]);
        }
    }
}
