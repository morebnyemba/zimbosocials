<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketerPortfolio extends Model
{
    protected $fillable = [
        'user_id', 'title', 'platform', 'url',
        'thumbnail_url', 'description', 'metrics', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'metrics' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
