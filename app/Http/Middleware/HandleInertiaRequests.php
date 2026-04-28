<?php

namespace App\Http\Middleware;

use App\Models\Notification;
use Illuminate\Http\Request;
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
                    'id'           => $user->id,
                    'name'         => $user->name,
                    'email'        => $user->email,
                    'balance'      => $user->balance,
                    'role'         => $user->role,
                    'api_key'      => $user->api_key,
                    'locale'       => $user->locale,
                    'currency'     => $user->currency,
                    'phone'              => $user->phone,
                    'whatsapp_number'    => $user->whatsapp_number,
                    'company_name'       => $user->company_name,
                    'marketer_status'    => $user->marketer_status,
                    'bio'                => $user->bio,
                    'profile_image_url'  => $user->profile_image_url,
                    'account_type'       => $user->account_type,
                    'notification_prefs' => $user->notification_prefs ?? ['email' => true, 'whatsapp' => true],
                ] : null,
                'is_impersonating' => $request->session()->has('impersonator_id'),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
                'info'    => $request->session()->get('info'),
            ],
            'notifications_count' => $user
                ? Notification::unreadCountFor($user->id)
                : 0,
                'locale'       => app()->getLocale(),
                'translations' => __('messages'),
        ];
    }
}

