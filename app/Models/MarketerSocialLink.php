<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketerSocialLink extends Model
{
    protected $fillable = [
        'user_id',
        'platform',
        'handle',
        'profile_url',
        'follower_count',
        'verified',
    ];

    protected function casts(): array
    {
        return [
            'verified' => 'boolean',
            'follower_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function platforms(): array
    {
        return ['instagram', 'tiktok', 'youtube', 'twitter', 'facebook', 'telegram'];
    }
}
