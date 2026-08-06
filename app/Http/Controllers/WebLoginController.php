<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\WebLoginToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Consumes a one-tap login link minted by the WhatsApp 'weblogin' flow
 * (WebLoginToken::issue()). The token is single-use and short-lived —
 * WebLoginToken::consume() marks it spent the moment it's looked up, valid
 * or not, so a link can never log two different browsers in.
 */
class WebLoginController extends Controller
{
    public function consume(Request $request, string $token): RedirectResponse
    {
        $user = WebLoginToken::consume($token);

        if (! $user) {
            return redirect()->route('login')
                ->withErrors(['email' => 'That login link has expired or was already used — request a new one on WhatsApp.']);
        }

        Auth::login($user);
        $request->session()->regenerate();
        session(['locale' => $user->locale]);

        AuditLog::dispatchLog(action: 'auth.weblogin', userId: $user->id, modelType: User::class, modelId: $user->id);

        // Still on the auto-generated username/password/email from the
        // WhatsApp sign-up — walk them through replacing it before anything
        // else, so they're never locked out for good if they lose WhatsApp
        // access.
        if ($user->needsCredentialsSetup()) {
            return redirect()->route('account.setup.edit')
                ->with('success', "Logged in! Let's set up your own login details.");
        }

        return redirect()->intended(route($this->defaultDashboardRoute($user)))
            ->with('success', 'Logged in!');
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
