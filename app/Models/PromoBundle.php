<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * A flat-price bundle for an exact quantity of a service ("3,000 followers for
 * $12"). Looked up on every charge calculation, so the whole active set is
 * cached and the cache is dropped whenever a bundle changes.
 */
class PromoBundle extends Model
{
    private const CACHE_KEY = 'promo:bundles';

    protected $fillable = [
        'service_id', 'quantity', 'price', 'label', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        $flush = fn () => Cache::forget(self::CACHE_KEY);
        static::saved($flush);
        static::deleted($flush);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** Active bundles keyed "serviceId:quantity" => price. */
    public static function priceMap(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function (): array {
            if (! \Illuminate\Support\Facades\Schema::hasTable('promo_bundles')) {
                return [];
            }

            return self::query()
                ->where('is_active', true)
                ->get(['service_id', 'quantity', 'price'])
                ->mapWithKeys(fn (self $b) => [$b->service_id.':'.$b->quantity => (float) $b->price])
                ->all();
        });
    }

    /** The flat price for this exact service+quantity, or null if not a bundle. */
    public static function priceFor(int $serviceId, int $quantity): ?float
    {
        return self::priceMap()[$serviceId.':'.$quantity] ?? null;
    }

    /** Active bundles for a service, cheapest first — for quoting. */
    public static function forService(int $serviceId): array
    {
        $out = [];
        foreach (self::priceMap() as $key => $price) {
            [$id, $qty] = explode(':', $key);
            if ((int) $id === $serviceId) {
                $out[(int) $qty] = $price;
            }
        }
        ksort($out);

        return $out;
    }
}
