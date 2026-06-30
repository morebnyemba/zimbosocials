<?php

namespace App\Http\Controllers;

use App\Models\LeaderboardPrize;
use App\Models\LeaderboardSnapshot;
use App\Models\Service;
use App\Services\LeaderboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminLeaderboardController extends Controller
{
    public function __construct(
        private readonly LeaderboardService $leaderboardService,
    ) {}

    public function prizes(): Response
    {
        $prizes = LeaderboardPrize::orderBy('category')
            ->orderBy('rank')
            ->get();

        $services = Service::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Admin/Leaderboard/Prizes', [
            'prizes' => $prizes,
            'services' => $services,
        ]);
    }

    public function storePrize(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category' => 'required|in:referrals,orders,deposits',
            'rank' => 'required|integer|min:1|max:10',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'service_id' => 'nullable|exists:services,id',
            'service_quantity' => 'nullable|integer|min:1',
            'bonus_amount' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        LeaderboardPrize::create($data);

        return back()->with('success', 'Prize created successfully.');
    }

    public function updatePrize(Request $request, LeaderboardPrize $prize): RedirectResponse
    {
        $data = $request->validate([
            'category' => 'required|in:referrals,orders,deposits',
            'rank' => 'required|integer|min:1|max:10',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'service_id' => 'nullable|exists:services,id',
            'service_quantity' => 'nullable|integer|min:1',
            'bonus_amount' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $prize->update($data);

        return back()->with('success', 'Prize updated successfully.');
    }

    public function destroyPrize(LeaderboardPrize $prize): RedirectResponse
    {
        $prize->delete();

        return back()->with('success', 'Prize deleted successfully.');
    }

    public function results(Request $request, int $year, int $month): Response
    {
        $results = $this->leaderboardService->getMonthlyResults($year, $month)
            ->map(fn ($snapshot) => [
                'id' => $snapshot->id,
                'category' => $snapshot->category,
                'rank' => $snapshot->rank,
                'score' => $snapshot->score,
                'user_name' => $snapshot->user?->name ?? 'Deleted User',
                'user_email' => $snapshot->user?->email,
                'prize_title' => $snapshot->prize?->title,
                'is_awarded' => $snapshot->is_awarded,
                'awarded_at' => $snapshot->awarded_at?->toDateTimeString(),
                'notes' => $snapshot->notes,
            ]);

        $availableMonths = $this->leaderboardService->getAvailableMonths();

        return Inertia::render('Admin/Leaderboard/Results', [
            'results' => $results,
            'year' => $year,
            'month' => $month,
            'availableMonths' => $availableMonths,
        ]);
    }

    public function awardPrize(Request $request, LeaderboardSnapshot $snapshot): RedirectResponse
    {
        $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $snapshot->update([
            'is_awarded' => true,
            'awarded_at' => now(),
            'notes' => $request->input('notes', 'Manually awarded by admin.'),
        ]);

        return back()->with('success', "Prize awarded to rank #{$snapshot->rank} in {$snapshot->category}.");
    }
}
