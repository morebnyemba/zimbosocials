<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Setting;
use App\Models\UpstreamProvider;
use App\Services\AI\SeoContentGenerator;
use App\Services\CurrencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class AdminSettingsController extends Controller
{
    public function index(CurrencyService $currencyService): Response
    {
        $settings = Setting::all()->groupBy('group');
        $providers = UpstreamProvider::all();

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings,
            'providers' => $providers,
            'currencyRates' => $currencyService->rates(),
            'referralDefaults' => [
                'first_deposit_reward' => (string) config('services.referral.first_deposit_reward', '1.00'),
                'order_commission_percent' => (string) config('services.referral.order_commission_percent', '2.00'),
                'order_commission_min_total' => (string) config('services.referral.order_commission_min_total', '20.00'),
                'referred_first_deposit_bonus_percent' => (string) config('services.referral.referred_first_deposit_bonus_percent', '10.00'),
            ],
            'monetizerDefaults' => [
                'threshold_usd' => number_format((float) config('services.monetizer.threshold_usd', 100.00), 2, '.', ''),
                'lookback_days' => (string) config('services.monetizer.lookback_days', 90),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string'],
            'settings.*.value' => ['nullable', 'string'],
            'settings.*.group' => ['required', 'string'],
        ]);

        foreach ($data['settings'] as $item) {
            Setting::set($item['key'], $item['value'], $item['group']);
        }

        if (collect($data['settings'])->contains(fn ($item) => $item['group'] === 'currency')) {
            Cache::forget('currency:rates');
        }

        // Optional: Clear config cache to apply changes if needed
        // Artisan::call('config:clear');

        return back()->with('success', 'Application settings updated successfully.');
    }

    public function seoGenerator(): Response
    {
        $categories = Service::active()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return Inertia::render('Admin/SeoGenerator', [
            'categories' => $categories,
        ]);
    }

    public function generateSeo(Request $request, SeoContentGenerator $generator): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:category,faq'],
            'category' => ['required_if:type,category', 'nullable', 'string', 'max:50'],
            'angle' => ['nullable', 'string', 'max:200'],
            'count' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $type = $data['type'];

        if ($type === 'category') {
            $category = (string) ($data['category'] ?? '');
            $services = Service::active()
                ->where('category', $category)
                ->select(['id', 'name', 'category'])
                ->orderBy('display_order')
                ->limit(30)
                ->get()
                ->toArray();

            $result = $generator->generateCategoryDescription($category, $services, $data['angle'] ?? null);

            return $result === null
                ? response()->json(['message' => 'AI SEO generator is not available or no services found.'], 503)
                : response()->json($result);
        }

        $services = Service::active()
            ->select(['id', 'name', 'category'])
            ->inRandomOrder()
            ->limit(20)
            ->get()
            ->toArray();

        $result = $generator->generateFaqPage($services, (int) ($data['count'] ?? 5));

        return $result === null
            ? response()->json(['message' => 'AI SEO generator is not available.'], 503)
            : response()->json(['faqs' => $result]);
    }
}
