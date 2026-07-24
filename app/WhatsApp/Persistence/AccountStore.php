<?php

namespace App\WhatsApp\Persistence;

use App\Models\User;
use App\Models\WhatsAppAccount;

/**
 * Manages phone <-> user identity bindings. On first contact a guest account is
 * created; if the phone matches an existing user's `phone`, it is auto-linked.
 */
class AccountStore
{
    public function resolveOrCreate(string $phone, ?string $displayName = null): WhatsAppAccount
    {
        $account = WhatsAppAccount::firstOrNew(['wa_phone' => $phone]);

        if (! $account->exists) {
            $account->link_status = 'guest';
            $account->opted_in = true;
        }

        if ($displayName && ! $account->display_name) {
            $account->display_name = $displayName;
        }

        // Auto-link to an existing web account with a matching phone number.
        if ($account->user_id === null) {
            $user = $this->matchUserByPhone($phone);
            if ($user) {
                $account->user_id = $user->id;
                $account->link_status = 'linked';
            }
        }

        // Remember when we last heard from them BEFORE overwriting it — the
        // router uses the gap to give a returning customer a welcome-back.
        $previousSeen = $account->exists ? $account->last_seen_at : null;

        $account->last_seen_at = now();
        $account->save();

        $account->previousSeenAt = $previousSeen;

        return $account;
    }

    /** Pause the bot for this chat so a human can take over (AI escalation). */
    public function startAgentHandoff(string $phone, int $hours = 2): void
    {
        WhatsAppAccount::where('wa_phone', $phone)->update(['agent_handoff_until' => now()->addHours($hours)]);
    }

    public function setOptOut(string $phone, bool $optedIn): void
    {
        WhatsAppAccount::where('wa_phone', $phone)->update(['opted_in' => $optedIn]);
    }

    /** Match a user whose stored phone equals this WhatsApp number (digits only). */
    private function matchUserByPhone(string $phone): ?User
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return null;
        }

        return User::query()
            ->whereNotNull('phone')
            ->whereRaw("REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', '') = ?", [$digits])
            ->first();
    }
}
