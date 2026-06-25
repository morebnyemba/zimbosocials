<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceUpstream extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'upstream_provider_id',
        'external_service_id',
        'external_rate',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'external_rate' => 'decimal:4',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(UpstreamProvider::class, 'upstream_provider_id');
    }
}
