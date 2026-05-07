<?php

namespace App\Http\Controllers;

use App\Models\UpstreamProvider;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

use App\Models\Service;
use App\Models\ServiceUpstream;
use App\Services\Upstream\UpstreamProviderClient;

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

    public function syncBalance(UpstreamProvider $upstreamProvider, UpstreamProviderClient $client): RedirectResponse
    {
        $client->setProvider($upstreamProvider);
        $balance = $client->getBalance();

        if ($balance !== null) {
            $upstreamProvider->update(['balance' => $balance]);
            return back()->with('success', "Balance synced successfully. Current Balance: {$balance}");
        }

        return back()->with('error', 'Failed to sync balance. Check credentials or provider status.');
    }

    public function importServices(UpstreamProvider $upstreamProvider, UpstreamProviderClient $client): RedirectResponse
    {
        $client->setProvider($upstreamProvider);
        $services = $client->getServices();

        if (empty($services)) {
            return back()->with('error', 'Failed to fetch services from provider.');
        }

        $profitMargin = (float) config('upstream.profit_margin', 1.20);
        $imported = 0;

        foreach ($services as $s) {
            if (!isset($s['service'])) continue;

            $existingPivot = ServiceUpstream::where('upstream_provider_id', $upstreamProvider->id)
                ->where('external_service_id', (string) $s['service'])
                ->first();

            if (!$existingPivot) {
                // Check if a service with the exact name exists already
                $service = Service::where('name', $s['name'])->first();

                if (!$service) {
                    $service = Service::create([
                        'name' => $s['name'],
                        'name_sn' => $s['name'],
                        'description' => $s['desc'] ?? '',
                        'description_sn' => $s['desc'] ?? '',
                        'category' => $s['category'] ?? 'Default',
                        'type' => $s['type'] ?? 'Default',
                        'rate' => round(((float)$s['rate']) * $profitMargin, 4),
                        'min_qty' => (int)($s['min'] ?? 0),
                        'max_qty' => (int)($s['max'] ?? 0),
                        'is_active' => true,
                        'is_dripfeed' => (bool)($s['dripfeed'] ?? false),
                        'is_refill' => (bool)($s['refill'] ?? false),
                    ]);
                }

                ServiceUpstream::create([
                    'service_id' => $service->id,
                    'upstream_provider_id' => $upstreamProvider->id,
                    'external_service_id' => (string) $s['service'],
                    'external_rate' => (float) $s['rate'],
                    'priority' => 1,
                    'is_active' => true,
                ]);

                $imported++;
            }
        }

        return back()->with('success', "Successfully imported {$imported} new services.");
    }
}
