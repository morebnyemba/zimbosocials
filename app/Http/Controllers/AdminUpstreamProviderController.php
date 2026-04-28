<?php

namespace App\Http\Controllers;

use App\Models\UpstreamProvider;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class AdminUpstreamProviderController extends Controller
{
    public function index(): Response
    {
        $providers = UpstreamProvider::all();
        return Inertia::render('Admin/UpstreamProviders/Index', ['providers' => $providers]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:255',
            'api_key' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        UpstreamProvider::create($data);

        return back()->with('success', 'Upstream Provider created successfully.');
    }

    public function update(Request $request, UpstreamProvider $upstreamProvider): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:255',
            'api_key' => 'required|string|max:255',
            'is_active' => 'boolean',
        ]);

        $upstreamProvider->update($data);

        return back()->with('success', 'Upstream Provider updated successfully.');
    }

    public function destroy(UpstreamProvider $upstreamProvider): RedirectResponse
    {
        $upstreamProvider->delete();
        return back()->with('success', 'Upstream Provider deleted successfully.');
    }
}
