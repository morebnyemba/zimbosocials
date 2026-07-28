<?php

// app/Http/Controllers/MarketingController.php

namespace App\Http\Controllers;

use App\Models\ManualPaymentDetail;
use App\Models\Service;
use App\Services\ReferralService;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class MarketingController extends Controller
{
    public function home(): Response
    {
        $activityServices = Service::active()
            ->select(['id', 'name', 'name_sn', 'category'])
            ->inRandomOrder()
            ->limit(80)
            ->get();

        $categories = Service::active()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        // Real, self-updating headline numbers so the homepage never advertises
        // figures that drift away from the actual catalog.
        $stats = [
            'services' => Service::active()->count(),
            'categories' => $categories->count(),
        ];

        return Inertia::render('Marketing/Home', [
            'activityServices' => $activityServices,
            'categories' => $categories,
            'stats' => $stats,
        ]);
    }

    public function services(): Response
    {
        // Only the columns the page actually renders. Loading whole models here
        // meant every visit pulled all 21 columns — including six name and
        // description fields across three languages — for the ENTIRE active
        // catalogue, then serialised them into the page props. Against a fully
        // synced upstream catalogue that exhausts memory (a 500) or builds a
        // payload too big to render, which is why it failed only sometimes:
        // it depended on how much memory was free at that moment.
        //
        // Cached because the catalogue only changes when the nightly
        // upstream:sync-services run updates it.
        $services = Cache::remember('marketing:services:v1', now()->addHour(), function () {
            return Service::active()
                ->select(['id', 'name', 'category'])
                ->orderBy('category')
                ->orderBy('display_order')
                ->get()
                ->groupBy('category')
                ->map(fn ($group) => $group->map(fn (Service $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                ])->values())
                ->toArray();
        });

        return Inertia::render('Marketing/Services', [
            'services' => $services,
        ]);
    }

    public function contact(): Response
    {
        // Contact page not cached — contains live payment details
        $paymentDetails = ManualPaymentDetail::active()
            ->ordered()
            ->get();

        return Inertia::render('Marketing/Contact', [
            'paymentDetails' => $paymentDetails,
        ]);
    }

    public function about(): Response
    {
        return Inertia::render('Marketing/About');
    }

    /**
     * Public explainer for the referral program + leaderboard. Pulls live
     * rates from ReferralService rather than hardcoding numbers in the page,
     * so it never drifts out of sync when an admin changes a setting.
     */
    public function referralProgram(ReferralService $referralService): Response
    {
        return Inertia::render('Marketing/ReferralProgram', [
            'rates' => $referralService->programRates(),
        ]);
    }

    public function help(): Response
    {
        return Inertia::render('Marketing/Help');
    }

    public function privacy(): Response
    {
        return Inertia::render('Marketing/Privacy');
    }

    public function terms(): Response
    {
        return Inertia::render('Marketing/Terms');
    }
}
