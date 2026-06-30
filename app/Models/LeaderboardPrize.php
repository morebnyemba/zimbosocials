<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaderboardPrize extends Model
{
    protected $fillable = [
        'category',
        'rank',
        'title',
        'description',
        'service_id',
        'service_quantity',
        'bonus_amount',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'service_quantity' => 'integer',
            'bonus_amount' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }
}
