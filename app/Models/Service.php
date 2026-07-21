<?php

// app/Models/Service.php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'name_sn', 'name_nd', 'description', 'description_sn', 'description_nd',
        'category', 'type', 'upstream_service_id', 'rate', 'min_qty', 'max_qty',
        'is_active', 'is_dripfeed', 'is_refill', 'refill_days',
        'avg_time_minutes', 'display_order',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'is_active' => 'boolean',
            'is_dripfeed' => 'boolean',
            'is_refill' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory(Builder $query, string $cat): Builder
    {
        return $query->where('category', $cat);
    }

    /** Price for a given quantity */
    public function calculateCharge(int $quantity): float
    {
        // A promo bundle is a flat price for an exact quantity ("3,000 for
        // $12"). Applying it here means the quote, the confirm card and the
        // actual wallet debit can never disagree — every caller goes through
        // this method.
        $bundle = PromoBundle::priceFor((int) $this->id, $quantity);
        if ($bundle !== null) {
            return round($bundle, 4);
        }

        return round(($quantity / 1000) * $this->rate, 4);
    }

    /** Localised name helper (falls back to the English name when a translation is missing) */
    public function getLocalName(string $locale = 'sn'): string
    {
        return match ($locale) {
            'sn' => $this->name_sn ?: $this->name,
            'nd' => $this->name_nd ?: $this->name,
            default => $this->name,
        };
    }

    /**
     * Get the multiple upstreams mapped to this service.
     */
    public function upstreams(): HasMany
    {
        return $this->hasMany(ServiceUpstream::class)->orderBy('priority')->where('is_active', true);
    }

    /** Flat-price promo bundles for this service, cheapest first. */
    public function promoBundles(): HasMany
    {
        return $this->hasMany(PromoBundle::class)->where('is_active', true)->orderBy('quantity');
    }
}
