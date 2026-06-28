<?php

namespace App\Services\PlatformMetrics;

use App\Models\SocialPlatformStat;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Stores and retrieves manually-entered platform metrics for creators.
 * No official platform APIs are used to avoid compliance/litigation risk.
 */
class PlatformMetricsService
{
    /**
     * Get the latest value for a platform/metric.
     */
    public function get(User $user, string $platform, string $metricKey): int
    {
        return (int) SocialPlatformStat::where('user_id', $user->id)
            ->where('platform', $platform)
            ->where('metric_key', $metricKey)
            ->value('value');
    }

    /**
     * Get all stored stats for a user keyed by platform and metric.
     *
     * @return array<string, array<string, array{value: int, source: string, fetched_at: string|null}>>
     */
    public function allForUser(User $user): array
    {
        $stats = [];

        /** @var Collection<int, SocialPlatformStat> $rows */
        $rows = SocialPlatformStat::where('user_id', $user->id)
            ->orderBy('platform')
            ->orderBy('metric_key')
            ->get();

        foreach ($rows as $row) {
            $stats[$row->platform][$row->metric_key] = [
                'value' => (int) $row->value,
                'source' => $row->source,
                'fetched_at' => $row->fetched_at?->toDateTimeString(),
            ];
        }

        return $stats;
    }

    /**
     * Store a manual metric value.
     */
    public function setManual(User $user, string $platform, string $metricKey, int $value): void
    {
        SocialPlatformStat::updateOrCreate(
            [
                'user_id' => $user->id,
                'platform' => $platform,
                'metric_key' => $metricKey,
            ],
            [
                'value' => $value,
                'source' => 'manual',
                'fetched_at' => now(),
            ]
        );
    }
}
