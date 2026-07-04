<?php

// app/Http/Controllers/MarketingController.php

namespace App\Http\Controllers;

use App\Models\ManualPaymentDetail;
use App\Models\Service;
use App\Services\ReferralService;
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
        $services = Service::active()
            ->orderBy('category')
            ->orderBy('display_order')
            ->get()
            ->groupBy('category');

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
