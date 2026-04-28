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
