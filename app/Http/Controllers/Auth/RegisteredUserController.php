<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'whatsapp_number' => ['required', 'string', 'max:20'],
            'account_type'    => ['required', 'string', 'in:individual,business,marketer'],
            'locale'          => ['required', 'string', 'in:sn,en,nd'],
            'company_name'    => ['nullable', 'string', 'max:255'],
            'role'            => ['nullable', 'string', 'in:user,marketer'],
        ]);

        \Illuminate\Support\Facades\Log::info('Registration Attempt', $request->all());

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'whatsapp_number' => $validated['whatsapp_number'],
            'company_name'    => $validated['company_name'] ?? null,
            'role'            => ($validated['role'] ?? null) === 'marketer' ? 'marketer' : 'user',
            'account_type'    => $validated['account_type'],
            'marketer_status' => ($validated['role'] ?? null) === 'marketer' ? 'pending' : 'approved',
            'locale'          => $validated['locale'],
        ]);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->put('locale', $user->locale ?? 'sn');

        return redirect(route('dashboard', absolute: false));
    }
}
