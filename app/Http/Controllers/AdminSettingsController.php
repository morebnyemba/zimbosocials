<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\UpstreamProvider;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;

class AdminSettingsController extends Controller
{
    public function index(): Response
    {
        $settings = Setting::all()->groupBy('group');
        $providers = UpstreamProvider::all();

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings,
            'providers' => $providers,
            'referralDefaults' => [
                'first_deposit_reward' => (string) config('services.referral.first_deposit_reward', '1.00'),
                'order_commission_percent' => (string) config('services.referral.order_commission_percent', '2.00'),
                'order_commission_min_total' => (string) config('services.referral.order_commission_min_total', '20.00'),
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

        // Optional: Clear config cache to apply changes if needed
        // Artisan::call('config:clear');

        return back()->with('success', 'Application settings updated successfully.');
    }
}
