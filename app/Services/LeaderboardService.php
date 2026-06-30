<?php

namespace App\Services;

use App\Models\LeaderboardPrize;
use App\Models\LeaderboardSnapshot;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeaderboardService
{
    private const CATEGORIES = ['referrals', 'orders', 'deposits'];

    /**
     * Get live rankings for the current month.
     *
     * @return Collection<int, array{user_id: int, user_name: string, score: float, rank: int}>
     */
    public function getCurrentRankings(string $category, int $limit = 10): Collection
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $results = match ($category) {
            'referrals' => $this->queryReferralRankings($startOfMonth, $endOfMonth, $limit),
            'orders' => $this->queryOrderRankings($startOfMonth, $endOfMonth, $limit),
            'deposits' => $this->queryDepositRankings($startOfMonth, $endOfMonth, $limit),
            default => collect(),
        };

        return $results->values()->map(function ($row, int $index) {
            return [
                'user_id' => (int) $row->user_id,
                'user_name' => $row->user_name,
                'score' => round((float) $row->score, 4),
                'rank' => $index + 1,
            ];
        });
    }

    /**
     * Get the current user's rank and score for a specific category.
     *
     * @return array{rank: int, score: float}|null
     */
    public function getUserRank(int $userId, string $category): ?array
    {
        $rankings = $this->getCurrentRankings($category, 100);
        $entry = $rankings->firstWhere('user_id', $userId);

        return $entry ? ['rank' => $entry['rank'], 'score' => $entry['score']] : null;
    }

    /**
     * Freeze final rankings for a completed month into leaderboard_snapshots.
     *
     * @return int Number of snapshot entries created
     */
    public function snapshotMonth(int $year, int $month): int
    {
        $created = 0;

        return DB::transaction(function () use ($year, $month, &$created) {
            foreach (self::CATEGORIES as $category) {
                // Skip if already snapshotted
                if (LeaderboardSnapshot::where('year', $year)->where('month', $month)->where('category', $category)->exists()) {
                    continue;
                }

                $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfDay();
                $endOfMonth = $startOfMonth->copy()->endOfMonth();
                $topN = $this->topN();

                $results = match ($category) {
                    'referrals' => $this->queryReferralRankings($startOfMonth, $endOfMonth, $topN),
                    'orders' => $this->queryOrderRankings($startOfMonth, $endOfMonth, $topN),
                    'deposits' => $this->queryDepositRankings($startOfMonth, $endOfMonth, $topN),
                    default => collect(),
                };

                $results->values()->each(function ($row, int $index) use ($year, $month, $category, &$created) {
                    $rank = $index + 1;

                    $prize = LeaderboardPrize::active()
                        ->forCategory($category)
                        ->where('rank', $rank)
                        ->first();

                    LeaderboardSnapshot::create([
                        'year' => $year,
                        'month' => $month,
                        'category' => $category,
                        'user_id' => $row->user_id,
                        'rank' => $rank,
                        'score' => round((float) $row->score, 4),
                        'prize_id' => $prize?->id,
                    ]);

                    $created++;
                });
            }

            return $created;
        });
    }

    /**
     * Retrieve the frozen snapshots for a past month.
     */
    public function getMonthlyResults(int $year, int $month, ?string $category = null): Collection
    {
        $query = LeaderboardSnapshot::with(['user', 'prize'])
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('category')
            ->orderBy('rank');

        if ($category) {
            $query->where('category', $category);
        }

        return $query->get();
    }

    /**
     * Get all months that have been snapshotted, most recent first.
     */
    public function getAvailableMonths(): Collection
    {
        return LeaderboardSnapshot::query()
            ->select('year', 'month')
            ->distinct()
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();
    }

    // ──────────────────────────────────────────────
    // Private query builders
    // ──────────────────────────────────────────────

    private function queryReferralRankings(Carbon $start, Carbon $end, int $limit): Collection
    {
        $minReferrals = $this->minReferrals();

        return DB::table('users as referrer')
            ->join('users as referred', 'referred.referred_by', '=', 'referrer.id')
            ->select('referrer.id as user_id', 'referrer.username as user_name', DB::raw('COUNT(referred.id) as score'))
            ->whereNotNull('referred.referred_bonus_awarded_at')
            ->whereBetween('referred.created_at', [$start, $end])
            ->groupBy('referrer.id', 'referrer.username')
            ->havingRaw('COUNT(referred.id) >= ?', [$minReferrals])
            ->orderByDesc('score')
            ->limit($limit)
            ->get();
    }

    private function queryOrderRankings(Carbon $start, Carbon $end, int $limit): Collection
    {
        $minOrders = $this->minOrders();

        return DB::table('orders')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->select('orders.user_id', 'users.username as user_name', DB::raw('COUNT(orders.id) as score'))
            ->whereNotIn('orders.status', ['cancelled', 'refunded'])
            ->whereBetween('orders.created_at', [$start, $end])
            ->groupBy('orders.user_id', 'users.username')
            ->havingRaw('COUNT(orders.id) >= ?', [$minOrders])
            ->orderByDesc('score')
            ->limit($limit)
            ->get();
    }

    private function queryDepositRankings(Carbon $start, Carbon $end, int $limit): Collection
    {
        $minDeposits = $this->minDeposits();

        return DB::table('transactions')
            ->join('users', 'users.id', '=', 'transactions.user_id')
            ->select('transactions.user_id', 'users.username as user_name', DB::raw('SUM(transactions.amount) as score'))
            ->where('transactions.type', 'deposit')
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.created_at', [$start, $end])
            ->groupBy('transactions.user_id', 'users.username')
            ->havingRaw('COUNT(transactions.id) >= ?', [$minDeposits])
            ->orderByDesc('score')
            ->limit($limit)
            ->get();
    }

    // ──────────────────────────────────────────────
    // Settings helpers
    // ──────────────────────────────────────────────

    private function minReferrals(): int
    {
        return (int) Setting::get('leaderboard_min_referrals', 3);
    }

    private function minOrders(): int
    {
        return (int) Setting::get('leaderboard_min_orders', 5);
    }

    private function minDeposits(): int
    {
        return (int) Setting::get('leaderboard_min_deposits', 2);
    }

    private function topN(): int
    {
        return (int) Setting::get('leaderboard_top_n', 5);
    }
}
