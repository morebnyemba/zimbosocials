<?php

namespace App\WhatsApp\AI;

use App\Models\AiUsage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Caps AI usage per phone per day so a chatty user can't run up the Gemini
 * bill. Deterministic handlers (commands, flows, KB) are unaffected — only the
 * free-text AI fallback consults this.
 *
 * Call counts alone were never a budget: a call's cost swings by an order of
 * magnitude depending on the catalogue size, whether media rode along, and how
 * much the model chose to think. The USD ceiling below is the one that
 * actually holds, because it reads the tokens that were really spent.
 */
class AIGuard
{
    public function allow(string $phone): bool
    {
        $perPhone = $this->dailyLimit();
        if ($perPhone > 0 && (int) Cache::get($this->key($phone), 0) >= $perPhone) {
            return false;
        }

        // Global ceiling: N new users × per-phone limit is otherwise unbounded.
        $global = $this->globalDailyLimit();
        if ($global > 0 && (int) Cache::get($this->globalKey(), 0) >= $global) {
            Log::warning('WhatsApp AI global daily limit reached — falling back to deterministic replies');

            return false;
        }

        return $this->withinBudget();
    }

    /**
     * The spend ceiling, in real money. Reads today's banked tokens (thinking
     * included) and stops calling the model once they are worth more than the
     * configured daily budget.
     *
     * Cached for a minute: a webhook is an inline request and this must not add
     * a sum-and-round over ai_usage to every inbound message. A minute of drift
     * is a fraction of a cent at any volume this guard exists to catch.
     */
    private function withinBudget(): bool
    {
        $budget = (float) config('services.gemini.daily_budget_usd', 0);
        if ($budget <= 0) {
            return true;
        }

        $spent = Cache::remember(
            'wa:ai:spend:'.now()->format('Y-m-d'),
            now()->addMinute(),
            fn () => AiUsage::costToday()
        );

        if ($spent >= $budget) {
            Log::warning('WhatsApp AI daily budget reached — falling back to deterministic replies', [
                'spent_usd' => $spent,
                'budget_usd' => $budget,
            ]);

            return false;
        }

        return true;
    }

    /** Admin-tunable via Settings → WhatsApp (ai_daily_limit) or env. 0 = unlimited. */
    private function dailyLimit(): int
    {
        return (int) config('services.whatsapp.ai_daily_limit', 40);
    }

    /** Total AI calls per day across ALL users. 0 = unlimited. */
    private function globalDailyLimit(): int
    {
        return (int) config('services.whatsapp.ai_global_daily_limit', 0);
    }

    public function record(string $phone): void
    {
        foreach ([$this->key($phone), $this->globalKey()] as $key) {
            $count = (int) Cache::get($key, 0) + 1;
            Cache::put($key, $count, now()->endOfDay());
        }
    }

    private function key(string $phone): string
    {
        return 'wa:ai:'.$phone.':'.now()->format('Y-m-d');
    }

    private function globalKey(): string
    {
        return 'wa:ai:global:'.now()->format('Y-m-d');
    }
}
