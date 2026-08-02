<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A paid one-off service booking (advertising setup & training, ads account
 * fix, account recovery/unbanning) — human-fulfilled, so it lands as
 * 'pending_setup' for an admin rather than going to the upstream dispatcher.
 */
class ExtraServiceBooking extends Model
{
    protected $fillable = [
        'user_id', 'wa_phone', 'item', 'total', 'status', 'notes',
    ];

    protected $casts = [
        'total' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** All configured items, keyed by slug. */
    public static function items(): array
    {
        return (array) config('extra_services.items', []);
    }

    public static function item(string $key): ?array
    {
        return self::items()[$key] ?? null;
    }

    public function label(): string
    {
        return (string) (self::item($this->item)['label'] ?? ucfirst(str_replace('_', ' ', (string) $this->item)));
    }
}
