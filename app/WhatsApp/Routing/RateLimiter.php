<?php

namespace App\WhatsApp\Routing;

use Illuminate\Support\Facades\Cache;

/**
 * Per-phone flood control. Allows up to MAX messages per WINDOW seconds; warns
 * once on the message that crosses the limit, then silently drops the rest.
 */
class RateLimiter
{
    private const MAX = 15;
    private const WINDOW = 30;

    /** @return array{allowed: bool, warn: bool} */
    public function check(string $phone): array
    {
        $key = 'wa:rl:'.$phone;
        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, self::WINDOW);

        if ($count > self::MAX) {
            return ['allowed' => false, 'warn' => $count === self::MAX + 1];
        }

        return ['allowed' => true, 'warn' => false];
    }
}
