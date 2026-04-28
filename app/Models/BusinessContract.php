<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessContract extends Model
{
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

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }
}
