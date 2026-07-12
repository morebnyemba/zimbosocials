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
    public function allow(string $phone): bool
    {
        $limit = $this->dailyLimit();
        if ($limit <= 0) {
            return true; // 0 = unlimited
        }

        return (int) Cache::get($this->key($phone), 0) < $limit;
    }

    /** Admin-tunable via Settings → WhatsApp (ai_daily_limit) or env. */
    private function dailyLimit(): int
    {
        return (int) config('services.whatsapp.ai_daily_limit', 40);
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
