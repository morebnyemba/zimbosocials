<?php

namespace App\WhatsApp\AI;

use Illuminate\Support\Facades\Cache;

/**
 * Caps AI usage per phone per day so a chatty user can't run up the Gemini
 * bill. Deterministic handlers (commands, flows, KB) are unaffected — only the
 * free-text AI fallback consults this.
 */
class AIGuard
{
    private const DAILY_LIMIT = 40;

    public function allow(string $phone): bool
    {
        return (int) Cache::get($this->key($phone), 0) < self::DAILY_LIMIT;
    }

    public function record(string $phone): void
    {
        $key = $this->key($phone);
        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, now()->endOfDay());
    }

    private function key(string $phone): string
    {
        return 'wa:ai:'.$phone.':'.now()->format('Y-m-d');
    }
}
