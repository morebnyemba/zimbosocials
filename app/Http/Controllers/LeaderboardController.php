<?php

namespace App\Http\Controllers;

use App\Models\LeaderboardPrize;
use App\Services\LeaderboardService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardController extends Controller
{
    public function __construct(
        private readonly LeaderboardService $leaderboardService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $topN = 10;

        // Live rankings for the current month
        $rankings = [
            'referrals' => $this->leaderboardService->getCurrentRankings('referrals', $topN),
            'orders' => $this->leaderboardService->getCurrentRankings('orders', $topN),
            'deposits' => $this->leaderboardService->getCurrentRankings('deposits', $topN),
        ];

        // Current user's rank in each category
        $myRanks = [
            'referrals' => $this->leaderboardService->getUserRank($user->id, 'referrals'),
            'orders' => $this->leaderboardService->getUserRank($user->id, 'orders'),
            'deposits' => $this->leaderboardService->getUserRank($user->id, 'deposits'),
        ];

        // Previous month's winners
        $prevMonth = Carbon::now()->subMonth();
        $previousWinners = $this->leaderboardService->getMonthlyResults(
            $prevMonth->year,
            $prevMonth->month
        )->groupBy('category')->map(fn ($items) => $items->map(fn ($s) => [
            'rank' => $s->rank,
            'user_name' => $s->user?->name ?? 'Deleted User',
            'score' => $s->score,
            'prize_title' => $s->prize?->title,
            'is_awarded' => $s->is_awarded,
        ]));

        // Prizes available this month
        $prizes = LeaderboardPrize::active()
            ->orderBy('category')
            ->orderBy('rank')
            ->get()
            ->groupBy('category')
            ->map(fn ($items) => $items->map(fn ($p) => [
                'rank' => $p->rank,
                'title' => $p->title,
                'description' => $p->description,
                'bonus_amount' => $p->bonus_amount,
            ]));

        // Month metadata
        $monthLabel = Carbon::now()->format('F Y');
        $daysRemaining = Carbon::now()->daysUntil(Carbon::now()->endOfMonth())->count();
        $previousMonthLabel = $prevMonth->format('F Y');

        return Inertia::render('Leaderboard/Index', [
            'rankings' => $rankings,
            'myRanks' => $myRanks,
            'previousWinners' => $previousWinners,
            'prizes' => $prizes,
            'monthLabel' => $monthLabel,
            'daysRemaining' => $daysRemaining,
            'previousMonthLabel' => $previousMonthLabel,
        ]);
    }
}
