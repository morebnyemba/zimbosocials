<?php
// app/Models/Service.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Service extends Model
{
    protected $fillable = [
        'name', 'name_sn', 'description', 'description_sn',
        'category', 'type', 'upstream_service_id', 'rate', 'min_qty', 'max_qty',
        'is_active', 'is_dripfeed', 'is_refill', 'refill_days',
        'avg_time_minutes', 'display_order',
    ];

    protected function casts(): array
    {
        return [
            'rate'       => 'decimal:4',
            'is_active'  => 'boolean',
            'is_dripfeed'=> 'boolean',
            'is_refill'  => 'boolean',
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
        return round(($quantity / 1000) * $this->rate, 4);
    }

    /** Localised name helper */
    public function getLocalName(string $locale = 'sn'): string
    {
        return $locale === 'sn' ? $this->name_sn : $this->name;
    }

    /**
     * Get the multiple upstreams mapped to this service.
     */
    public function upstreams(): HasMany
    {
        return $this->hasMany(ServiceUpstream::class)->orderBy('priority')->where('is_active', true);
    }
}
