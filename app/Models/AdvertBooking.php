<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A paid sponsored-advert campaign booking. Priced flat per week and set up by
 * a human, so it lands as 'pending_setup' rather than going to the upstream
 * dispatcher like a catalogue order.
 */
class AdvertBooking extends Model
{
    protected $fillable = [
        'user_id', 'wa_phone', 'package', 'weeks', 'weekly_price',
        'total', 'promoting', 'target_link', 'status', 'notes',
    ];

    protected $casts = [
        'weeks' => 'integer',
        'weekly_price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** All configured packages, keyed by slug. */
    public static function packages(): array
    {
        return (array) config('adverts.packages', []);
    }

    public static function package(string $key): ?array
    {
        return self::packages()[$key] ?? null;
    }

    public function packageLabel(): string
    {
        return (string) (self::package($this->package)['label'] ?? ucfirst($this->package));
    }
}
