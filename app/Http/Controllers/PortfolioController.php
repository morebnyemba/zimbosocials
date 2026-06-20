<?php

namespace App\Http\Controllers;

use App\Models\MarketerPortfolio;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PortfolioController extends Controller
{
    /** Public portfolio page */
    public function show(User $user): Response
    {
        if (!$user->hasMarketerAccess()) {
            abort(404);
        }

        $user->load(['socialLinks', 'portfolios' => fn ($q) => $q->orderBy('sort_order')]);

        $stats = [
            'completed_contracts' => $user->contractApplications()->where('status', 'completed')->count(),
            'social_accounts'    => $user->socialLinks()->count(),
        ];

        return Inertia::render('Marketer/Portfolio', [
            'marketer'  => $user->only(['id', 'name', 'company_name', 'bio', 'profile_image_url']),
            'portfolio' => $user->portfolios,
            'socials'   => $user->socialLinks,
            'stats'     => $stats,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title'       => ['required', 'string', 'max:140'],
            'platform'    => ['required', 'string', 'max:50'],
            'url'         => ['required', 'url', 'max:1000'],
            'thumbnail_url'=> ['nullable', 'url', 'max:1000'],
            'description' => ['nullable', 'string', 'max:1000'],
            'metrics'     => ['nullable', 'array'],
        ]);

        $data['user_id'] = Auth::id();

        MarketerPortfolio::create($data);

        return back()->with('success', 'Portfolio item added.');
    }

    public function destroy(MarketerPortfolio $portfolio): RedirectResponse
    {
        if ((int)$portfolio->user_id !== (int)Auth::id()) {
            abort(403);
        }

        $portfolio->delete();

        return back()->with('success', 'Portfolio item removed.');
    }
}
