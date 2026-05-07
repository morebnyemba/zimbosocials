<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Notification extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'user_id', 'type', 'title', 'body', 'data', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data'    => 'array',
            'read_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Notification $notification) {
            if (empty($notification->id)) {
                $notification->id = (string) Str::uuid();
            }
        });

        // Bust the cached unread count whenever a notification is created or updated
        static::created(function (Notification $notification) {
            Cache::forget("user:{$notification->user_id}:unread_notifications");
        });

        static::updated(function (Notification $notification) {
            Cache::forget("user:{$notification->user_id}:unread_notifications");
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function markAsRead(): void
    {
        if (!$this->isRead()) {
            $this->update(['read_at' => now()]);
        }
    }

    // ─── Static helpers ──────────────────────────────────────────────────────

    public static function send(int $userId, string $type, string $title, string $body, array $data = []): self
    {
        return static::create([
            'user_id' => $userId,
            'type'    => $type,
            'title'   => $title,
            'body'    => $body,
            'data'    => $data,
        ]);
    }

    public static function unreadCountFor(int $userId): int
    {
        return static::where('user_id', $userId)->whereNull('read_at')->count();
    }
}
