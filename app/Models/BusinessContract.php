<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessContract extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_FILLED = 'filled';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'user_id',
        'title',
        'platform',
        'description',
        'budget',
        'slots',
        'funded_amount',
        'deadline_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'funded_amount' => 'decimal:2',
            'deadline_at' => 'date',
            'slots' => 'integer',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(ContractApplication::class, 'business_contract_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    /** Whether the contract's deadline (if any) has passed. */
    public function isPastDeadline(): bool
    {
        return $this->deadline_at !== null && $this->deadline_at->endOfDay()->isPast();
    }
}
