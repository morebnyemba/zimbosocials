<?php

namespace App\Http\Controllers;

use App\Models\MarketerSocialLink;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class MarketerSocialLinkController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $role = (string) $user->getAttribute('role');

        if (!in_array($role, ['marketer', 'reseller'], true)) {
            return back()->with('error', 'Only marketer accounts can add social links.');
        }

        $data = $request->validate([
            'platform'       => ['required', Rule::in(MarketerSocialLink::platforms())],
            'handle'         => ['required', 'string', 'max:120'],
            'profile_url'    => ['nullable', 'url', 'max:500'],
            'follower_count' => ['nullable', 'integer', 'min:0'],
        ]);

        MarketerSocialLink::updateOrCreate(
            [
                'user_id'  => (int) $user->getAuthIdentifier(),
                'platform' => $data['platform'],
            ],
            [
                'handle'         => $data['handle'],
                'profile_url'    => $data['profile_url'] ?? null,
                'follower_count' => $data['follower_count'] ?? 0,
                'verified'       => false, // reset verification on update
            ]
        );

        return back()->with('success', 'Social link saved.');
    }

    public function destroy(MarketerSocialLink $socialLink): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ((int) $socialLink->getAttribute('user_id') !== (int) $user->getAuthIdentifier()) {
            abort(403);
        }

        $socialLink->delete();

        return back()->with('success', 'Social link removed.');
    }
}
