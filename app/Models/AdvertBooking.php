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
        'user_id', 'wa_phone', 'package', 'days', 'weeks', 'weekly_price',
        'total', 'promoting', 'target_link', 'target_audience', 'status', 'notes',
    ];

    protected $casts = [
        'days' => 'integer',
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

    /**
     * The price for a package, honoring a temporary grandfather window: a
     * contact who already existed before a reprice keeps seeing the old
     * number for a short grace period, so a price change never yanks the rug
     * out from under someone mid-conversation. A brand new contact always
     * sees the current price. See config/adverts.php for the actual dates.
     */
    public static function priceFor(string $key, ?\Illuminate\Support\Carbon $contactSince = null): float
    {
        $pkg = self::package($key);
        if (! $pkg) {
            return 0.0;
        }

        $repricedAt = config('adverts.repriced_at');
        $graceDays = (int) config('adverts.reprice_grace_days', 0);

        if ($repricedAt && $graceDays > 0 && $contactSince !== null) {
            $cutover = \Illuminate\Support\Carbon::parse($repricedAt);
            if ($contactSince->lt($cutover) && now()->lt($cutover->copy()->addDays($graceDays))) {
                $old = config("adverts.previous_packages.{$key}.price");
                if ($old !== null) {
                    return (float) $old;
                }
            }
        }

        return (float) ($pkg['price'] ?? 0);
    }

    public function packageLabel(): string
    {
        return (string) (self::package($this->package)['label'] ?? ucfirst($this->package));
    }

    /** Human duration for this booking ("3 days"), from the stored day count. */
    public function durationLabel(): string
    {
        $d = (int) ($this->days ?? 0);
        if ($d <= 0) {
            return $this->packageLabel();
        }

        return match (true) {
            $d % 30 === 0 => ($d / 30).' month'.($d > 30 ? 's' : ''),
            $d % 7 === 0 => ($d / 7).' week'.($d > 7 ? 's' : ''),
            default => $d.' day'.($d > 1 ? 's' : ''),
        };
    }

    /** Whether this booking's package includes a made-for-you video advert. */
    public function includesVideo(): bool
    {
        return (bool) (self::package($this->package)['includes_video'] ?? false);
    }

    /** The package the AI should nudge people toward, if one is flagged. */
    public static function recommendedKey(): ?string
    {
        foreach (self::packages() as $key => $pkg) {
            if (! empty($pkg['recommended'])) {
                return $key;
            }
        }

        return null;
    }
}
