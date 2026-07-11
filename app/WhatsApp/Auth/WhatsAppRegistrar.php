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
    /** @return array{ok:bool, user?:User, error?:string} */
    public function register(string $phone, string $name, string $email): array
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

            // Bind the phone to the new user.
            WhatsAppAccount::where('wa_phone', $phone)->update([
                'user_id' => $user->id,
                'link_status' => 'linked',
                'link_otp' => null,
                'link_otp_expires' => null,
                'link_attempts' => 0,
            ]);

            $this->safely(fn () => NotificationService::sendWelcome($user));
            $this->safely(fn () => NotificationService::notifyAdmins(
                'admin_new_registration',
                'New User Registered (WhatsApp)',
                "{$user->name} ({$user->email}) signed up via WhatsApp.",
                ['user_name' => $user->name, 'user_email' => $user->email, 'role' => $user->role]
            ));

            return ['ok' => true, 'user' => $user];
        } catch (\Throwable $e) {
            Log::error('WhatsAppRegistrar failed', ['phone' => $phone, 'message' => $e->getMessage()]);

            return ['ok' => false, 'error' => 'server_error'];
        }
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
