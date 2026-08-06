<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\WhatsApp\Auth\WhatsAppRegistrar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets a WhatsApp-registered user replace their auto-generated username,
 * password and (if they were silently auto-registered) placeholder email
 * with their own. No 'current_password' check — the whole point is they were
 * never told the generated one. See User::needsCredentialsSetup().
 */
class AccountSetupController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Account/Setup', [
            'current' => [
                'username' => $user->username,
                'email' => $user->email,
            ],
            'is_placeholder_email' => WhatsAppRegistrar::isAutoEmail($user->email),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'username' => [
                'required', 'string', 'min:3', 'max:20', 'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->username = strtolower($data['username']);
        $user->email = mb_strtolower($data['email']);
        $user->password = Hash::make($data['password']);
        $user->credentials_set_at = now();
        $user->save();

        return redirect()->route($this->defaultDashboardRoute($user))
            ->with('success', "You're all set — your account now has its own username, password and email.");
    }

    /** Skip for now — reachable again any time from the dashboard banner or another weblogin. */
    public function skip(Request $request): RedirectResponse
    {
        return redirect()->route($this->defaultDashboardRoute($request->user()));
    }

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
}
