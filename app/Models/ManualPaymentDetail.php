<?php

// app/Models/ManualPaymentDetail.php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ManualPaymentDetail extends Model
{
    protected $fillable = [
        'method_key',
        'label',
        'account_name',
        'account_number',
        'instructions',
        'is_active',
        'sort_order',
        'gateway_type',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
