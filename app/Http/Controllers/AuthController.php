<?php

// app/Http/Controllers/AuthController.php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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

    /**
     * Shared username validation rules: 3–20 chars, letters/numbers/underscore,
     * must start with a letter, unique (case-insensitive). Pass an id to ignore
     * when updating an existing user.
     */
    private static function usernameRules(?int $ignoreId = null): array
    {
        $unique = Rule::unique('users', 'username');
        if ($ignoreId) {
            $unique->ignore($ignoreId);
        }

        return ['required', 'string', 'min:3', 'max:20', 'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/', $unique];
    }

    /**
     * Real-time username availability check for the registration form.
     * Returns { available: bool, reason?: string, suggestion?: string }.
     */
    public function checkUsername(Request $request): JsonResponse
    {
        $username = strtolower(trim((string) $request->query('username', '')));

        $validator = Validator::make(['username' => $username], ['username' => self::usernameRules()]);

        if ($validator->fails()) {
            $taken = User::where('username', $username)->exists();

            return response()->json([
                'available' => false,
                'reason' => $taken ? 'taken' : 'invalid',
                'suggestion' => $taken ? User::generateUsernameFromName($username) : null,
            ]);
        }

        return response()->json(['available' => true]);
    }

    // ─── Register ─────────────────────────────────────────────────────────────

    public function showRegister(Request $request): Response
    {
        // Capture referral code from query string into session
        if ($request->filled('ref')) {
            $ref = $request->input('ref');
            if (User::where('referral_code', $ref)->exists()) {
                $request->session()->put('referral_code', $ref);
            }
        }

        $referralCode = $request->session()->get('referral_code');
        $referrer = $referralCode
            ? User::where('referral_code', $referralCode)->select('id', 'name')->first()
            : null;

        return Inertia::render('Auth/Register', [
            'referralCode' => $referralCode,
            'referrerName' => $referrer?->name,
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => self::usernameRules(),
            'email' => ['required', 'email', 'unique:users,email'],
            'whatsapp_number' => ['required', 'string', 'min:10', 'max:20'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'referral_code' => ['nullable', 'string', 'max:32', 'exists:users,referral_code'],
        ]);

        // Normalize WhatsApp number — strip spaces, dashes, leading +
        $waNumber = preg_replace('/[^0-9]/', '', $data['whatsapp_number']);

        $referrer = null;
        if (! empty($data['referral_code'])) {
            $referrer = User::where('referral_code', $data['referral_code'])->first();
        }

        $user = User::create([
            'name' => $data['name'],
            'username' => strtolower($data['username']),
            'email' => $data['email'],
            'whatsapp_number' => $waNumber,
            'phone' => $waNumber,      // also set phone for convenience
            'password' => Hash::make($data['password']),
            'locale' => $request->get('locale', 'sn'),
            'referral_code' => User::generateReferralCode(),
            'referred_by' => $referrer?->getKey(),
        ]);

        // accept account path fields from multi-step registration form
        $user->role = in_array($request->input('role'), ['user', 'marketer']) ? $request->input('role') : 'user';
        $user->account_type = in_array($request->input('account_type'), ['individual', 'business', 'marketer']) ? $request->input('account_type') : 'individual';
        $user->company_name = $request->input('company_name') ? strip_tags($request->input('company_name')) : null;
        $user->save();
        $user->generateApiKey();

        Auth::login($user);

        // Send welcome WhatsApp notification
        NotificationService::sendWelcome($user);

        $request->session()->forget('referral_code');

        return redirect()->route($this->defaultDashboardRoute($user))
            ->with('success', __('messages.welcome', ['name' => $user->name]));
    }

    // ─── Login ────────────────────────────────────────────────────────────────

    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => true,
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required'],
        ]);

        // Accept either an email address or a username in the single login field.
        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $credentials = [
            $field => $field === 'username' ? strtolower($data['login']) : $data['login'],
            'password' => $data['password'],
        ];

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            // Set user's preferred locale
            session(['locale' => Auth::user()->locale]);

            return redirect()->intended(route($this->defaultDashboardRoute(Auth::user())));
        }

        return back()->withErrors([
            'login' => __('auth.failed'),
        ])->onlyInput('login');
    }

    // ─── Logout ───────────────────────────────────────────────────────────────

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
