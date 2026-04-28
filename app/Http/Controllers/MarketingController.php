<?php
// app/Http/Controllers/MarketingController.php

namespace App\Http\Controllers;

use App\Models\ManualPaymentDetail;
use App\Models\Service;
use Inertia\Inertia;
use Inertia\Response;

class MarketingController extends Controller
{
    public function home(): Response
    {
        $featuredServices = Service::active()
            ->orderBy('display_order')
            ->limit(8)
            ->get();

        $categories = Service::active()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return Inertia::render('Marketing/Home', [
            'featuredServices' => $featuredServices,
            'categories' => $categories,
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
