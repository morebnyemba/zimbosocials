<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialPlatformStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'platform',
        'metric_key',
        'value',
        'source',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'fetched_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
