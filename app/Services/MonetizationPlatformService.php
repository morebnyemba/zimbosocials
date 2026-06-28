<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Services\PlatformMetrics\PlatformMetricsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Tracks creator progress toward external platform monetization thresholds
 * by combining ordered SMM services with real-time platform metrics.
 */
class MonetizationPlatformService
{
    /**
     * @return array<string, mixed>
     */
    public static function getPlatformsForUser(User $user): array
    {
        $metricsService = app(PlatformMetricsService::class);
        $platforms = [];

        foreach (config('services.monetizer.platforms', []) as $key => $config) {
            $requirements = [];

            foreach ($config['requirements'] ?? [] as $reqKey => $reqConfig) {
                $services = self::matchingServices($reqConfig['match'] ?? []);
                $serviceIds = $services->pluck('id')->all();
                $orderQuantity = self::progressForServices($user, $serviceIds);
                $statQuantity = $reqConfig['metric'] ?? null
                    ? $metricsService->get($user, $key, $reqConfig['metric'])
                    : 0;

                $current = $orderQuantity + $statQuantity;
                $target = $reqConfig['target'] ?? null;

                $requirements[$reqKey] = [
                    'key' => $reqKey,
                    'label' => $reqConfig['label'],
                    'target' => $target,
                    'current' => $current,
                    'order_quantity' => $orderQuantity,
                    'stat_quantity' => $statQuantity,
                    'progress' => $target > 0 ? min(100, round(($current / $target) * 100, 2)) : 0,
                    'gap' => $target !== null ? max(0, $target - $current) : null,
                    'services' => $services->map(fn (Service $s) => [
                        'id' => $s->id,
                        'name' => $s->name,
                        'min_qty' => $s->min_qty,
                        'max_qty' => $s->max_qty,
                        'rate' => $s->rate,
                    ])->values()->all(),
                ];
            }

            $platforms[$key] = [
                'key' => $key,
                'label' => $config['label'],
                'requirements' => $requirements,
            ];
        }

        return $platforms;
    }

    /**
     * Check whether the user has met all requirements for any configured platform.
     */
    public static function hasReachedAnyPlatformThreshold(User $user): bool
    {
        foreach (config('services.monetizer.platforms', []) as $key => $config) {
            if (self::isPlatformThresholdMet($user, $key, $config)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function isPlatformThresholdMet(User $user, string $platformKey, array $config): bool
    {
        $platforms = self::getPlatformsForUser($user);
        $platform = $platforms[$platformKey] ?? null;

        if (! $platform) {
            return false;
        }

        foreach ($platform['requirements'] as $requirement) {
            if ($requirement['target'] === null) {
                continue;
            }

            if ($requirement['current'] < $requirement['target']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $match
     * @return Collection<int, Service>
     */
    public static function matchingServices(array $match): Collection
    {
        $category = $match['category'] ?? null;
        $keywords = $match['name_contains'] ?? [];

        return Service::active()
            ->when($category, fn (Builder $query) => $query->whereRaw('LOWER(category) = ?', [strtolower($category)]))
            ->when($keywords, function (Builder $query) use ($keywords): void {
                $query->where(function (Builder $q) use ($keywords): void {
                    foreach ((array) $keywords as $keyword) {
                        $q->orWhere('name', 'like', "%{$keyword}%");
                    }
                });
            })
            ->orderBy('display_order')
            ->get();
    }

    /**
     * @param  array<int, int>  $serviceIds
     */
    public static function progressForServices(User $user, array $serviceIds): int
    {
        if ($serviceIds === []) {
            return 0;
        }

        return (int) Order::where('user_id', $user->id)
            ->whereIn('service_id', $serviceIds)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->sum('quantity');
    }
}
