<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A single-use, short-lived link that logs a WhatsApp customer straight into
 * their web account — no password needed. Only the token's hash is ever
 * persisted; the raw token exists just long enough to go into the WhatsApp
 * message and the URL the customer taps.
 */
class WebLoginToken extends Model
{
    private const TTL_MINUTES = 10;

    protected $fillable = ['user_id', 'wa_phone', 'token_hash', 'expires_at', 'used_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Mint a new token for this user; returns the RAW token to send, never stored as-is. */
    public static function issue(int $userId, string $waPhone): string
    {
        $raw = Str::random(64);

        self::create([
            'user_id' => $userId,
            'wa_phone' => $waPhone,
            'token_hash' => hash('sha256', $raw),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        return $raw;
    }

    /** Consume a raw token: valid, unused, unexpired → the User; otherwise null. Always marks it used so a link can never work twice. */
    public static function consume(string $rawToken): ?User
    {
        $token = self::where('token_hash', hash('sha256', $rawToken))->first();

        if (! $token || $token->used_at !== null || $token->expires_at->isPast()) {
            return null;
        }

        $token->update(['used_at' => now()]);

        return $token->user;
    }

    public static function ttlMinutes(): int
    {
        return self::TTL_MINUTES;
    }
}
