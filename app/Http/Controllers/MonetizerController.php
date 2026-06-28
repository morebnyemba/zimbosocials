<?php

// app/Http/Controllers/MonetizerController.php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\ManagerAssignmentService;
use App\Services\MonetizationPlatformService;
use App\Services\PlatformMetrics\PlatformMetricsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MonetizerController extends Controller
{
    public function __construct(
        private readonly PlatformMetricsService $metricsService,
        private readonly ManagerAssignmentService $managerService,
    ) {}

    public function index(): Response
    {
        $user = Auth::user();

        abort_unless($user && $user->hasMarketerAccess(), 403);

        $this->managerService->assignIfEligible($user);
        $user->refresh();

        $threshold = (float) Setting::get(
            'monetizer_threshold_usd',
            config('services.monetizer.threshold_usd', 100.00)
        );

        $lookbackDays = (int) Setting::get(
            'monetizer_lookback_days',
            config('services.monetizer.lookback_days', 90)
        );

        $activityTotal = $user->monetizerActivityTotal();
        $progress = $threshold > 0 ? min(100, round(($activityTotal / $threshold) * 100, 2)) : 0;

        return Inertia::render('Monetizer/Index', [
            'isUnlocked' => $user->monetizer_unlocked_at !== null,
            'canUnlock' => $user->qualifiesForMonetizer(),
            'threshold' => $threshold,
            'lookbackDays' => $lookbackDays,
            'activityTotal' => $activityTotal,
            'progress' => $progress,
            'platforms' => MonetizationPlatformService::getPlatformsForUser($user),
            'handles' => [
                'youtube_channel_id' => $user->youtube_channel_id,
                'facebook_page_id' => $user->facebook_page_id,
                'tiktok_username' => $user->tiktok_username,
                'instagram_username' => $user->instagram_username,
                'x_username' => $user->x_username,
            ],
            'managers' => [
                'account' => $user->accountManager ? [
                    'name' => $user->accountManager->name,
                    'email' => $user->accountManager->email,
                    'phone' => $user->accountManager->phone,
                ] : null,
                'support' => $user->supportManager ? [
                    'name' => $user->supportManager->name,
                    'email' => $user->supportManager->email,
                    'phone' => $user->supportManager->phone,
                ] : null,
            ],
            'hasReachedPlatformThreshold' => MonetizationPlatformService::hasReachedAnyPlatformThreshold($user),
        ]);
    }

    public function unlock(): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user && $user->hasMarketerAccess(), 403);
        abort_unless($user->qualifiesForMonetizer(), 403, 'You do not yet meet the monetizer threshold.');

        $user->update(['monetizer_unlocked_at' => now()]);

        return redirect()->route('monetizer.index')->with('success', 'Monetizer panel unlocked.');
    }

    public function updateProfiles(Request $request): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user && $user->hasMarketerAccess(), 403);

        $data = $request->validate([
            'youtube_channel_id' => ['nullable', 'string', 'max:255'],
            'facebook_page_id' => ['nullable', 'string', 'max:255'],
            'tiktok_username' => ['nullable', 'string', 'max:255'],
            'instagram_username' => ['nullable', 'string', 'max:255'],
            'x_username' => ['nullable', 'string', 'max:255'],
        ]);

        $user->update($data);

        return redirect()->route('monetizer.index')->with('success', 'Platform profiles updated.');
    }

    public function updateManualStats(Request $request): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user && $user->hasMarketerAccess(), 403);

        $data = $request->validate([
            'platform' => ['required', 'string', 'max:50'],
            'metric_key' => ['required', 'string', 'max:50'],
            'value' => ['required', 'integer', 'min:0'],
        ]);

        $this->metricsService->setManual(
            $user,
            (string) $data['platform'],
            (string) $data['metric_key'],
            (int) $data['value']
        );

        return redirect()->route('monetizer.index')->with('success', 'Metric updated.');
    }
}
