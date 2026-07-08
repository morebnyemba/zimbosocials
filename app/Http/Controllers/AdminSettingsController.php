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

        // Boot-time overrides (mail, whatsapp, app, tawk) read from this
        // cache — without forgetting it, saved changes wouldn't take effect
        // for up to 5 minutes.
        Cache::forget('app:boot_settings');

        return back()->with('success', 'Application settings updated successfully.');
    }

    /**
     * Send a test email using the SMTP values currently in the form (not the
     * saved ones), so an admin can verify the connection works before saving
     * — and before enabling anything that depends on mail, like admin 2FA.
     */
    public function testMail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'host' => ['required', 'string'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['nullable', 'string'],
            'password' => ['nullable', 'string'],
            'encryption' => ['nullable', 'string'],
            'from_address' => ['nullable', 'email'],
            'from_name' => ['nullable', 'string'],
        ]);

        $encryption = strtolower((string) ($data['encryption'] ?? ''));
        $useSmtps = $encryption === 'ssl' || (int) $data['port'] === 465;

        $recipient = $request->user()->email;

        try {
            $mailer = \Illuminate\Support\Facades\Mail::build([
                'transport' => 'smtp',
                'host' => $data['host'],
                'port' => (int) $data['port'],
                'username' => $data['username'] ?: null,
                'password' => $data['password'] ?: null,
                'scheme' => $useSmtps ? 'smtps' : null,
                'timeout' => 15,
            ]);

            $fromAddress = $data['from_address'] ?: (string) config('mail.from.address');
            $fromName = $data['from_name'] ?: (string) config('mail.from.name');

            $mailer->raw(
                "This is a test email from Zimbo Socials.\n\nIf you're reading this, your SMTP settings are working — save them, then you can safely enable admin 2FA.",
                function ($message) use ($recipient, $fromAddress, $fromName): void {
                    $message->to($recipient)
                        ->from($fromAddress, $fromName)
                        ->subject('Zimbo Socials — SMTP test');
                }
            );
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Send failed: '.$e->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => "Test email sent to {$recipient} — check the inbox (and spam folder).",
        ]);
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
