<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Daily AI token usage and what it costs.
 *
 * Rates are per MILLION tokens and configurable, because they change and
 * because a wrong number here is worse than none — it would quietly
 * misrepresent spend on a dashboard someone is making decisions from.
 */
class AiUsage extends Model
{
    protected $table = 'ai_usage';

    protected $fillable = ['day', 'model', 'requests', 'prompt_tokens', 'cached_tokens', 'output_tokens', 'thinking_tokens'];

    protected function casts(): array
    {
        return [
            'day' => 'date',
            'requests' => 'integer',
            'prompt_tokens' => 'integer',
            'cached_tokens' => 'integer',
            'output_tokens' => 'integer',
            'thinking_tokens' => 'integer',
        ];
    }

    /**
     * Add one request's usage to today's row. Never allowed to disturb the
     * conversation it is measuring, so failures are logged and swallowed.
     */
    public static function record(string $model, int $promptTokens, int $cachedTokens, int $outputTokens, int $thinkingTokens = 0): void
    {
        try {
            $day = now()->toDateString();

            // Upsert then increment by PRIMARY KEY, so concurrent webhook
            // requests can't lose each other's counts. Matching on the day
            // column instead would be fragile: it is cast to a date and stored
            // as a datetime, so comparing it to a 'Y-m-d' string silently
            // matches nothing and every counter stays at zero.
            // whereDate, not a plain equality: `day` is cast to a date and
            // stored as a datetime, so matching it against 'Y-m-d' finds
            // nothing — firstOrCreate would then try to insert a duplicate,
            // hit the unique index, and lose the count to the catch below.
            $row = static::whereDate('day', $day)->where('model', $model)->first()
                ?? static::create(['day' => $day, 'model' => $model]);

            DB::table('ai_usage')
                ->where('id', $row->getKey())
                ->update([
                    'requests' => DB::raw('requests + 1'),
                    'prompt_tokens' => DB::raw('prompt_tokens + '.max(0, $promptTokens)),
                    'cached_tokens' => DB::raw('cached_tokens + '.max(0, $cachedTokens)),
                    'output_tokens' => DB::raw('output_tokens + '.max(0, $outputTokens)),
                    'thinking_tokens' => DB::raw('thinking_tokens + '.max(0, $thinkingTokens)),
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('Could not record AI usage', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Estimated cost of this row's usage, in USD.
     *
     * Thinking tokens are billed at the output rate and are reported by the
     * API separately from the reply, so they are added at that rate here.
     * Leaving them out was the difference between a dashboard that looked
     * comfortable and a bill that was not.
     *
     * Still NOT included: cache STORAGE, which is charged per hour of TTL
     * rather than per request and so has no per-row home. See
     * self::cacheStorageCostPerDay().
     */
    public function cost(): float
    {
        $rates = (array) config(
            str_contains($this->model, 'lite') ? 'services.gemini.light_rates' : 'services.gemini.rates',
            []
        );

        // Cached input is billed at a fraction of fresh input, so counting it
        // at the full rate would hide the saving we are trying to measure.
        $fresh = max(0, $this->prompt_tokens - $this->cached_tokens);

        return round(
            $fresh / 1_000_000 * (float) ($rates['input'] ?? 0)
            + $this->cached_tokens / 1_000_000 * (float) ($rates['cached_input'] ?? 0)
            + ($this->output_tokens + $this->thinking_tokens) / 1_000_000 * (float) ($rates['output'] ?? 0),
            4
        );
    }

    /** Today's spend across every model, in USD. */
    public static function costToday(): float
    {
        return round(
            static::whereDate('day', now()->toDateString())->get()->sum(fn (self $row) => $row->cost()),
            4
        );
    }

    /**
     * What the prompt cache costs to HOLD today, in USD — a real charge that
     * no token counter can see.
     *
     * Explicit caching bills $1.00 per million cached tokens per hour for the
     * whole TTL, arriving or not. We can't read Google's meter, so this is an
     * upper bound: the largest cached prefix seen today, held for every hour
     * the assistant was active. Shown next to the token cost so the TTL is a
     * visible decision rather than a silent one.
     */
    public static function cacheStorageCostPerDay(int $activeHours = 12): float
    {
        $peak = (int) static::whereDate('day', now()->toDateString())->max('cached_tokens');
        if ($peak <= 0) {
            return 0.0;
        }

        $ttlHours = max(1 / 60, (int) config('services.gemini.cache_ttl_seconds', 900) / 3600);
        $rate = (float) config('services.gemini.rates.cache_storage_hour', 1.00);

        // cached_tokens is a running daily total, not a prefix size — divide
        // back out by the requests that read it to recover roughly one cache.
        $requests = max(1, (int) static::whereDate('day', now()->toDateString())->sum('requests'));
        $prefix = $peak / $requests;

        return round($prefix / 1_000_000 * $rate * $ttlHours * $activeHours, 4);
    }
}
