<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UpstreamProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'api_key',
        'is_active',
        'balance',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'balance' => 'decimal:4',
    ];

    public function serviceUpstreams(): HasMany
    {
        return $this->hasMany(ServiceUpstream::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
