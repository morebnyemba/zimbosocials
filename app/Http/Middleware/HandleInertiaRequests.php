<?php

namespace App\Http\Middleware;

use App\Models\Notification;
use App\Services\TranslationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'balance' => $user->balance,
                    'role' => $user->role,
                    // Only the tail is ever shared — the full key is flashed
                    // once at generation and never leaves the server again.
                    'api_key_last4' => $user->api_key_last4,
                    'locale' => $user->locale,
                    'currency' => $user->currency,
                    'phone' => $user->phone,
                    'whatsapp_number' => $user->whatsapp_number,
                    'company_name' => $user->company_name,
                    'marketer_status' => $user->marketer_status,
                    'bio' => $user->bio,
                    'profile_image_url' => $user->profile_image_url,
                    'account_type' => $user->account_type,
                    'notification_prefs' => $user->notification_prefs ?? ['email' => true, 'whatsapp' => true],
                    'can_use_monetizer' => $user->hasMonetizerAccess(),
                ] : null,
                'is_impersonating' => $request->session()->has('impersonator_id'),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'info' => $request->session()->get('info'),
                // One-time reveal of a freshly generated API key
                'new_api_key' => $request->session()->get('new_api_key'),
            ],
            // Cached for 30 seconds to avoid a COUNT query on every page load
            'notifications_count' => $user
                ? Cache::remember(
                    "user:{$user->id}:unread_notifications",
                    30,
                    fn () => Notification::unreadCountFor($user->id)
                )
                : 0,
            'locale' => app()->getLocale(),
            'translations' => fn () => app(TranslationService::class)->messages(app()->getLocale()),
        ];
    }
}
