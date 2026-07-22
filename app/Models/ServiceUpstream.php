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
        'link_type',
        'external_rate',
        'markup_type',
        'markup_value',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'external_rate' => 'decimal:4',
        'markup_value' => 'decimal:4',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Apply this pivot's stored markup to an upstream cost to get the local sell rate.
     */
    public function applyMarkup(float $upstreamRate): float
    {
        $local = $this->markup_type === 'fixed'
            ? $upstreamRate + (float) $this->markup_value
            : $upstreamRate * (1 + ((float) $this->markup_value / 100));

        return round($local, 4);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(UpstreamProvider::class, 'upstream_provider_id');
    }
}
