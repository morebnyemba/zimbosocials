<?php
// app/Http/Controllers/AuthController.php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    private function defaultDashboardRoute(User $user): string
    {
        if ($user->isAdmin()) {
            return 'admin.dashboard';
        }

        if ($user->isMarketer()) {
            return 'marketer.dashboard';
        }

        return 'dashboard';
    }

    // ─── Register ─────────────────────────────────────────────────────────────

    public function showRegister(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'             => ['required', 'string', 'max:100'],
            'email'            => ['required', 'email', 'unique:users,email'],
            'whatsapp_number'  => ['required', 'string', 'min:10', 'max:20'],
            'password'         => ['required', 'confirmed', Password::min(8)],
            'referral_code'    => ['nullable', 'string', 'max:32', 'exists:users,referral_code'],
        ]);

        // Normalize WhatsApp number — strip spaces, dashes, leading +
        $waNumber = preg_replace('/[^0-9]/', '', $data['whatsapp_number']);

        $referrer = null;
        if (!empty($data['referral_code'])) {
            $referrer = User::where('referral_code', $data['referral_code'])->first();
        }

        $user = User::create([
            'name'            => $data['name'],
            'email'           => $data['email'],
            'whatsapp_number' => $waNumber,
            'phone'           => $waNumber,      // also set phone for convenience
            'password'        => Hash::make($data['password']),
            'locale'          => $request->get('locale', 'sn'),
            'referral_code'   => User::generateReferralCode(),
            'referred_by'     => $referrer?->getKey(),
        ]);

        // accept account path fields from multi-step registration form
        $user->role         = in_array($request->input('role'), ['user', 'marketer']) ? $request->input('role') : 'user';
        $user->company_name = $request->input('company_name') ? strip_tags($request->input('company_name')) : null;
        $user->save();
        $user->generateApiKey();

        Auth::login($user);

        // Send welcome WhatsApp notification
        NotificationService::sendWelcome($user);

        return redirect()->route($this->defaultDashboardRoute($user))
            ->with('success', __('messages.welcome', ['name' => $user->name]));
    }

    // ─── Login ────────────────────────────────────────────────────────────────

    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => false,
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            // Set user's preferred locale
            session(['locale' => Auth::user()->locale]);

            return redirect()->intended(route($this->defaultDashboardRoute(Auth::user())));
        }

        return back()->withErrors([
            'email' => __('auth.failed'),
        ])->onlyInput('email');
    }

    // ─── Logout ───────────────────────────────────────────────────────────────

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
