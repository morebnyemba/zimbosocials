<?php

// app/Http/Controllers/SettingsController.php

namespace App\Http\Controllers;

use App\Services\CurrencyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings/Index');
    }

    public function update(Request $request, CurrencyService $currencyService): RedirectResponse
    {
        $user = Auth::user();
        $section = $request->input('section');

        if ($section === 'profile') {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:100'],
                'phone' => ['nullable', 'string', 'max:20'],
                'whatsapp_number' => ['nullable', 'string', 'max:20'],
                'company_name' => ['nullable', 'string', 'max:100'],
                'bio' => ['nullable', 'string', 'max:500'],
                'currency' => ['required', 'in:'.implode(',', $currencyService->supportedCodes())],
                'locale' => ['required', 'in:sn,en'],
            ]);

            $user->update($data);
            session(['locale' => $data['locale']]);

            return back()->with('success', __('messages.saved_success'));
        }

        if ($section === 'notifications') {
            $prefs = $request->validate([
                'email' => ['required', 'boolean'],
                'whatsapp' => ['required', 'boolean'],
            ]);

            $user->update(['notification_prefs' => $prefs]);

            return back()->with('success', 'Notification preferences updated.');
        }

        if ($section === 'password') {
            $data = $request->validate([
                'current_password' => ['required'],
                'password' => ['required', 'confirmed', Password::min(8)],
            ]);

            if (! Hash::check($data['current_password'], $user->password)) {
                return back()->withErrors(['current_password' => app()->getLocale() === 'sn'
                    ? 'Pasiwedhi yekare haina kukwana.'
                    : 'The current password is incorrect.']);
            }

            $user->update(['password' => Hash::make($data['password'])]);

            return back()->with('success', app()->getLocale() === 'sn'
                ? 'Pasiwedhi yachinjwa!'
                : 'Password changed successfully!');
        }

        return back();
    }

    public function regenerateApiKey(): RedirectResponse
    {
        $key = Auth::user()->generateApiKey();

        // Flash the plaintext key for a one-time reveal — only its hash is
        // stored, so this is the only moment it can ever be displayed.
        return back()
            ->with('new_api_key', $key)
            ->with('success', app()->getLocale() === 'sn'
                ? 'Kiyi itsva yeAPI yagadzirwa! Kopa izvozvi — haizooneki zvakare.'
                : 'New API key generated! Copy it now — it will not be shown again.');
    }
}
