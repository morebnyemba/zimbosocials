<?php

namespace App\WhatsApp;

use App\Models\WhatsAppAccount;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Tells the team about a conversation that needs a person — without silencing
 * the assistant the way a handoff does.
 *
 * Every alert carries a wa.me link, because the useful action is always the
 * same: open a chat with that customer. Reading a phone number off a screen and
 * retyping it is exactly the friction that stops anyone following up.
 */
class AdminNudge
{
    /** One nudge per number per reason per this many minutes. */
    private const COOLDOWN_MINUTES = 60;

    /**
     * @param  string  $reason  Short, human: "confused about delivery time".
     * @param  string  $kind    Groups the cooldown, e.g. 'confused' | 'idle'.
     * @return bool  Whether an alert was actually sent (false = still cooling down).
     */
    public static function raise(string $phone, string $reason, string $kind = 'ai'): bool
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if ($digits === '') {
            return false;
        }

        // Without this, a customer who stays confused for ten messages
        // generates ten identical alerts and the team stops reading them.
        $key = "wa:nudge:{$kind}:{$digits}";
        if (! Cache::add($key, true, now()->addMinutes(self::COOLDOWN_MINUTES))) {
            return false;
        }

        $account = WhatsAppAccount::where('wa_phone', 'like', '%'.substr($digits, -9))->first();
        $name = $account?->friendlyName();
        $who = $name !== null ? "*{$name}* (+{$digits})" : "+{$digits}";

        try {
            NotificationService::notifyAdmins(
                'admin_whatsapp_nudge',
                'A WhatsApp chat needs a person',
                "{$who} — {$reason}\n\n"
                ."💬 Reply to them directly: https://wa.me/{$digits}"
                .($account ? "\n🖥 Or open the transcript: ".route('admin.whatsapp.conversation', $account->id) : ''),
                ['wa_phone' => $digits, 'reason' => $reason, 'kind' => $kind],
            );
        } catch (\Throwable $e) {
            Log::warning('Admin nudge failed', ['phone' => $digits, 'message' => $e->getMessage()]);

            return false;
        }

        return true;
    }
}
