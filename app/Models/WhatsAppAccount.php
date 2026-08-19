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
        'last_automated_message_at', 'blocked_at', 'consecutive_send_failures',
    ];

    protected function casts(): array
    {
        return [
            'link_otp_expires' => 'datetime',
            'agent_handoff_until' => 'datetime',
            'last_seen_at' => 'datetime',
            'lead_flagged_at' => 'datetime',
            'last_automated_message_at' => 'datetime',
            'blocked_at' => 'datetime',
            'opted_in' => 'boolean',
            'link_attempts' => 'integer',
            'consecutive_send_failures' => 'integer',
        ];
    }

    /** Consecutive `failed` delivery statuses before we stop auto-messaging a contact. */
    private const BLOCK_THRESHOLD = 3;

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

    /**
     * Gate shared by every automated sender (idle nudges, saved-order
     * reminders, marketing broadcasts): opted in, not flagged as blocked, and
     * not messaged by ANY automated system within the cooldown. Each system
     * still runs its own timing/dedupe logic on top of this — this only stops
     * the systems from stacking on top of each other.
     */
    public function canReceiveAutomatedMessage(?int $cooldownHours = null): bool
    {
        if (! $this->opted_in || $this->blocked_at !== null) {
            return false;
        }

        $cooldownHours ??= (int) config('services.whatsapp.automated_cooldown_hours', 6);

        return $this->last_automated_message_at === null
            || $this->last_automated_message_at->lte(now()->subHours($cooldownHours));
    }

    /** Stamp the shared cooldown clock after any automated system sends to this contact. */
    public function markAutomatedMessageSent(): void
    {
        $this->forceFill(['last_automated_message_at' => now()])->save();
    }

    /**
     * Meta reported a `failed` delivery status for an outbound message. Three
     * in a row (nothing succeeding in between) auto-suppresses further
     * automated sends — a strong signal the contact is unreachable or has
     * blocked the business, and hammering a dead number is exactly what
     * drives the block rate up further.
     */
    public function recordSendFailure(): void
    {
        $this->increment('consecutive_send_failures');

        if ($this->consecutive_send_failures >= self::BLOCK_THRESHOLD && $this->blocked_at === null) {
            $this->forceFill(['blocked_at' => now()])->save();
        }
    }

    /** A send actually landed — clear any failure streak / block flag. */
    public function recordSendSuccess(): void
    {
        if ($this->consecutive_send_failures !== 0 || $this->blocked_at !== null) {
            $this->forceFill(['consecutive_send_failures' => 0, 'blocked_at' => null])->save();
        }
    }
}
