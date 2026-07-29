<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Binds a WhatsApp phone number to an app user (or holds a guest until linked).
 */
class WhatsAppAccount extends Model
{
    protected $table = 'whatsapp_accounts';

    protected $fillable = [
        'wa_phone', 'user_id', 'link_status', 'display_name',
        'link_otp', 'link_otp_expires', 'link_attempts', 'opted_in',
        'agent_handoff_until', 'last_seen_at', 'lead_flagged_at',
    ];

    protected function casts(): array
    {
        return [
            'link_otp_expires' => 'datetime',
            'agent_handoff_until' => 'datetime',
            'last_seen_at' => 'datetime',
            'lead_flagged_at' => 'datetime',
            'opted_in' => 'boolean',
            'link_attempts' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * When we last heard from this number BEFORE the current message — set by
     * AccountStore at resolve time (not a DB column; last_seen_at is already
     * overwritten by then). Null for a brand-new contact.
     */
    public ?\Illuminate\Support\Carbon $previousSeenAt = null;

    public function isLinked(): bool
    {
        return $this->link_status === 'linked' && $this->user_id !== null;
    }

    /**
     * Is the 24-hour customer service window open for this number?
     *
     * WhatsApp only allows free-form (non-template) messages for 24 hours after
     * the person last messaged US — a business message never opens the window.
     * last_seen_at is stamped on every inbound message (AccountStore::resolve),
     * so it is exactly that clock. Outside the window Meta rejects a free-form
     * send (error 131047), so callers must check before sending.
     */
    public static function serviceWindowOpen(string $phone, int $hours = 24): bool
    {
        $key = self::phoneKey($phone);
        if ($key === '') {
            return false;
        }

        return self::query()
            ->where('wa_phone', 'like', '%'.$key)
            ->where('last_seen_at', '>=', now()->subHours($hours))
            ->exists();
    }

    /**
     * The last 9 digits of a number — tolerant of the ways the same phone gets
     * stored ("0771234567", "+263 77 123 4567", "263771234567").
     */
    public static function phoneKey(string $phone): string
    {
        $digits = (string) preg_replace('/\D+/', '', $phone);

        return strlen($digits) > 9 ? substr($digits, -9) : $digits;
    }

    /**
     * A known contact coming back after a quiet spell — worth a warm
     * welcome-back. False for brand-new numbers (no prior visit) and for anyone
     * active within the window.
     */
    public function returningAfterGap(int $hours = 24): bool
    {
        return ! $this->wasRecentlyCreated
            && $this->previousSeenAt !== null
            && $this->previousSeenAt->lt(now()->subHours($hours));
    }

    /**
     * A name we can safely greet someone by. WhatsApp profile names are free
     * text and are often an email address, a phone number or a shop slogan —
     * "Hi megaman_music@gmail.com" reads like spam, so anything that isn't
     * name-shaped returns null and the caller greets without a name.
     */
    public function friendlyName(): ?string
    {
        $name = trim((string) $this->display_name);

        if ($name === '' || mb_strlen($name) > 40) {
            return null;
        }
        // Emails, handles and URLs.
        if (preg_match('/[@\/]|https?:/i', $name)) {
            return null;
        }
        // Phone numbers / anything mostly digits.
        if (preg_match('/^[\d\s+()-]+$/', $name)) {
            return null;
        }
        // Must contain at least one run of letters to be a name at all.
        if (! preg_match('/\p{L}{2,}/u', $name)) {
            return null;
        }

        return $name;
    }

    /** Just the first name, for a natural greeting ("Hi Tendai"). */
    public function firstName(): ?string
    {
        $name = $this->friendlyName();
        if ($name === null) {
            return null;
        }

        $first = trim(explode(' ', $name)[0]);

        return $first !== '' ? $first : null;
    }

    public function inAgentHandoff(): bool
    {
        return $this->agent_handoff_until !== null && $this->agent_handoff_until->isFuture();
    }
}
