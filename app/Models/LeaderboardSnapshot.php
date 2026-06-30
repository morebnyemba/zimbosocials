<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaderboardSnapshot extends Model
{
    protected $fillable = [
        'year',
        'month',
        'category',
        'user_id',
        'rank',
        'score',
        'is_awarded',
        'awarded_at',
        'prize_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'rank' => 'integer',
            'score' => 'float',
            'is_awarded' => 'boolean',
            'awarded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prize(): BelongsTo
    {
        return $this->belongsTo(LeaderboardPrize::class, 'prize_id');
    }
}
