<?php

// app/Http/Controllers/AuthController.php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

        NotificationService::notifyAdmins(
            'admin_new_registration',
            'New User Registered',
            "{$user->name} ({$user->email}) just signed up as a {$user->role}.",
            ['user_name' => $user->name, 'user_email' => $user->email, 'role' => $user->role]
        );

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
            $user = Auth::user();

            // Admins get a second factor: a valid password alone must not open
            // an account that controls every wallet on the platform. Log the
            // session back out and require an emailed code first.
            if ($user->isAdmin() && (bool) Setting::get('admin_2fa_enabled', '1')) {
                $userId = (int) $user->getAuthIdentifier();
                $remember = $request->boolean('remember');

                Auth::logout();

                try {
                    $this->sendAdminLoginCode($userId);
                } catch (\Throwable $e) {
                    Log::error('Admin 2FA code send failed', [
                        'user_id' => $userId,
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                    ]);

                    return back()->withErrors([
                        'login' => __('Could not send your verification code. Please try again or contact support.'),
                    ])->onlyInput('login');
                }

                $request->session()->put('2fa:user_id', $userId);
                $request->session()->put('2fa:remember', $remember);

                return redirect()->route('2fa.show');
            }

            $request->session()->regenerate();

            // Set user's preferred locale
            session(['locale' => $user->locale]);

            return redirect()->intended(route($this->defaultDashboardRoute($user)));
        }

        return back()->withErrors([
            'login' => __('auth.failed'),
        ])->onlyInput('login');
    }

    // ─── Admin two-factor (emailed code) ─────────────────────────────────────

    /** Generate, store (hashed) and email a 6-digit login code. */
    private function sendAdminLoginCode(int $userId): void
    {
        $user = User::findOrFail($userId);
        $code = (string) random_int(100000, 999999);

        Cache::put("admin2fa:code:{$userId}", hash('sha256', $code), now()->addMinutes(10));
        Cache::put("admin2fa:attempts:{$userId}", 0, now()->addMinutes(10));

        // Sent synchronously — the queue is cron-drained and a login code
        // can't wait a minute (or die with a broken cron).
        Mail::raw(
            "Your Zimbo Socials admin login code is: {$code}\n\nIt expires in 10 minutes. If you didn't try to log in, change your password immediately.",
            function ($message) use ($user): void {
                $message->to($user->email, $user->name)
                    ->subject('Your admin login code');
            }
        );
    }

    /** Show the code-entry challenge page. */
    public function show2fa(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('2fa:user_id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    /** Verify the emailed code and complete the login. */
    public function verify2fa(Request $request): RedirectResponse
    {
        $userId = (int) $request->session()->get('2fa:user_id', 0);
        if ($userId === 0) {
            return redirect()->route('login');
        }

        $request->validate(['code' => ['required', 'digits:6']]);

        $attempts = (int) Cache::get("admin2fa:attempts:{$userId}", 0);
        if ($attempts >= 5) {
            $request->session()->forget(['2fa:user_id', '2fa:remember']);
            Cache::forget("admin2fa:code:{$userId}");

            return redirect()->route('login')->withErrors([
                'login' => __('Too many incorrect codes. Please log in again.'),
            ]);
        }

        $expected = Cache::get("admin2fa:code:{$userId}");
        $provided = hash('sha256', (string) $request->input('code'));

        if ($expected === null || ! hash_equals($expected, $provided)) {
            Cache::put("admin2fa:attempts:{$userId}", $attempts + 1, now()->addMinutes(10));

            return back()->withErrors([
                'code' => $expected === null
                    ? __('This code has expired — request a new one.')
                    : __('That code is incorrect.'),
            ]);
        }

        $remember = (bool) $request->session()->pull('2fa:remember', false);
        $request->session()->forget('2fa:user_id');
        Cache::forget("admin2fa:code:{$userId}");
        Cache::forget("admin2fa:attempts:{$userId}");

        Auth::loginUsingId($userId, $remember);
        $request->session()->regenerate();
        session(['locale' => Auth::user()->locale]);

        AuditLog::dispatchLog(
            action: 'auth.admin_2fa_login',
            userId: $userId,
            modelType: User::class,
            modelId: $userId,
        );

        return redirect()->intended(route($this->defaultDashboardRoute(Auth::user())));
    }

    /** Re-send the emailed code (gated to once per minute). */
    public function resend2fa(Request $request): RedirectResponse
    {
        $userId = (int) $request->session()->get('2fa:user_id', 0);
        if ($userId === 0) {
            return redirect()->route('login');
        }

        if (! Cache::add("admin2fa:resend:{$userId}", true, 60)) {
            return back()->withErrors(['code' => __('A code was just sent — wait a minute before requesting another.')]);
        }

        try {
            $this->sendAdminLoginCode($userId);
        } catch (\Throwable $e) {
            return back()->withErrors(['code' => __('Could not send the code. Please try again.')]);
        }

        return back()->with('success', __('A new code has been sent to your email.'));
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
