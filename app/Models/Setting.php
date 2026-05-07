<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group'];

    /** Cache TTL in seconds (5 minutes). */
    private const CACHE_TTL = 300;

    /**
     * Get a setting value by key (cached).
     */
    public static function get(string $key, $default = null)
    {
        return Cache::remember(
            "setting:{$key}",
            self::CACHE_TTL,
            fn () => static::where('key', $key)->first()?->value
        ) ?? $default;
    }

    /**
     * Set a setting value and bust the cache.
     */
    public static function set(string $key, $value, string $group = 'general')
    {
        Cache::forget("setting:{$key}");

        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group]
        );
    }
}
