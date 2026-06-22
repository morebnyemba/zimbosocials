<?php

namespace App\Http\Controllers;

use App\Models\UpstreamProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

use App\Models\Service;
use App\Models\ServiceUpstream;
use App\Services\AI\ServiceEnricher;
use App\Services\Upstream\UpstreamProviderClient;

class AdminUpstreamProviderController extends Controller
{
    public function index(): Response
    {
        $providers = UpstreamProvider::all();
        return Inertia::render('Admin/UpstreamProviders/Index', [
            'providers' => $providers,
            'aiEnrichmentEnabled' => filled(config('services.gemini.api_key')),
        ]);
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

    public function availableServices(UpstreamProvider $upstreamProvider, UpstreamProviderClient $client): JsonResponse
    {
        $providerServices = $this->fetchProviderServices($upstreamProvider, $client);

        if ($providerServices === []) {
            return response()->json([
                'message' => 'Failed to fetch services from provider.',
            ], 422);
        }

        $existingMappings = ServiceUpstream::query()
            ->where('upstream_provider_id', $upstreamProvider->id)
            ->pluck('service_id', 'external_service_id');

        $services = collect($providerServices)
            ->map(function (array $service) use ($existingMappings): array {
                $externalServiceId = (string) $service['service'];
                $mappedServiceId = $existingMappings[$externalServiceId] ?? null;
                $mappedService = $mappedServiceId ? Service::find($mappedServiceId) : null;

                return [
                    'external_service_id' => $externalServiceId,
                    'name' => (string) ($service['name'] ?? 'Unnamed service'),
                    'description' => (string) ($service['desc'] ?? ''),
                    'category' => (string) ($service['category'] ?? 'Default'),
                    'type' => (string) ($service['type'] ?? 'Default'),
                    'external_rate' => round((float) ($service['rate'] ?? 0), 4),
                    'min_qty' => (int) ($service['min'] ?? 0),
                    'max_qty' => (int) ($service['max'] ?? 0),
                    'is_dripfeed' => (bool) ($service['dripfeed'] ?? false),
                    'is_refill' => (bool) ($service['refill'] ?? false),
                    'already_imported' => $mappedServiceId !== null,
                    'existing_service_name' => $mappedService?->name,
                    'default_markup_percentage' => $this->defaultMarkupPercentage(),
                ];
            })
            ->values();

        return response()->json([
            'services' => $services,
        ]);
    }

    public function importServices(Request $request, UpstreamProvider $upstreamProvider, UpstreamProviderClient $client, ServiceEnricher $enricher): RedirectResponse
    {
        $validated = $request->validate([
            'services' => ['required', 'array', 'min:1'],
            'services.*.external_service_id' => ['required', 'string'],
            'services.*.markup_percentage' => ['required', 'numeric', 'min:0', 'max:1000'],
            'enrich_with_ai' => ['nullable', 'boolean'],
        ]);

        $selectedServices = collect($validated['services'])
            ->keyBy(fn (array $service): string => (string) $service['external_service_id']);

        $providerServices = $this->fetchProviderServices($upstreamProvider, $client);

        if ($providerServices === []) {
            return back()->with('error', 'Failed to fetch services from provider.');
        }

        $requestedServices = collect($providerServices)
            ->filter(fn (array $service): bool => $selectedServices->has((string) $service['service']))
            ->values();

        if ($requestedServices->isEmpty()) {
            return back()->with('error', 'None of the selected services were found on the provider.');
        }

        // Optional AI cleanup + Shona/Ndebele translation, keyed by external_service_id.
        $enrichment = $request->boolean('enrich_with_ai')
            ? $enricher->enrich($requestedServices->all())
            : [];

        $imported = 0;
        $skipped = 0;

        foreach ($requestedServices as $s) {
            $existingPivot = ServiceUpstream::where('upstream_provider_id', $upstreamProvider->id)
                ->where('external_service_id', (string) $s['service'])
                ->first();

            if ($existingPivot) {
                $skipped++;
                continue;
            }

            $markupPercentage = (float) $selectedServices[(string) $s['service']]['markup_percentage'];
            $service = $this->findOrCreateService($upstreamProvider, $s, $markupPercentage, $enrichment[(string) $s['service']] ?? null);

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

        $message = "Imported {$imported} new services as inactive. Review the markup and activate them from the Services page.";

        if ($skipped > 0) {
            $message .= " Skipped {$skipped} already linked services.";
        }

        return back()->with('success', $message);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchProviderServices(UpstreamProvider $upstreamProvider, UpstreamProviderClient $client): array
    {
        $client->setProvider($upstreamProvider);
        $services = $client->getServices();

        return collect($services)
            ->filter(fn (mixed $service): bool => is_array($service) && $this->isImportableProviderService($service))
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $providerService
     */
    private function isImportableProviderService(array $providerService): bool
    {
        if (!isset($providerService['service'])) {
            return false;
        }

        if ((float) ($providerService['rate'] ?? 0) > 999999) {
            return false;
        }

        return !preg_match('/^[-=\s]+$/', (string) ($providerService['name'] ?? ''));
    }

    /**
     * @param array<string, mixed> $providerService
     * @param array<string, string>|null $enriched Optional AI-cleaned name/description + sn/nd translations.
     */
    private function findOrCreateService(UpstreamProvider $upstreamProvider, array $providerService, float $markupPercentage, ?array $enriched = null): Service
    {
        // Prefer the AI-cleaned English name when available; fall back to the raw upstream name.
        $serviceName = $enriched['name'] ?? (string) $providerService['name'];
        $service = Service::where('name', $serviceName)->first();

        if (
            $service
            && ServiceUpstream::where('service_id', $service->id)
                ->where('upstream_provider_id', $upstreamProvider->id)
                ->exists()
        ) {
            // Duplicate upstream names are common, so append the external ID for a stable unique fallback.
            $serviceName .= ' [' . $providerService['service'] . ']';
            $service = Service::where('name', $serviceName)->first();
        }

        if ($service) {
            return $service;
        }

        $externalRate = (float) ($providerService['rate'] ?? 0);
        $rawDescription = (string) ($providerService['desc'] ?? '');
        $description = $enriched['description'] ?? $rawDescription;

        return Service::create([
            'name' => $serviceName,
            'name_sn' => $enriched['name_sn'] ?? $serviceName,
            'name_nd' => $enriched['name_nd'] ?? $serviceName,
            'description' => $description,
            'description_sn' => $enriched['description_sn'] ?? $description,
            'description_nd' => $enriched['description_nd'] ?? $description,
            'category' => $providerService['category'] ?? 'Default',
            'type' => $providerService['type'] ?? 'Default',
            'rate' => round($externalRate * (1 + ($markupPercentage / 100)), 4),
            'min_qty' => (int) ($providerService['min'] ?? 0),
            'max_qty' => (int) ($providerService['max'] ?? 0),
            // Imported services are created inactive. An admin reviews the markup/rate
            // and explicitly activates them from the Services page before they go live.
            'is_active' => false,
            'is_dripfeed' => (bool) ($providerService['dripfeed'] ?? false),
            'is_refill' => (bool) ($providerService['refill'] ?? false),
        ]);
    }

    private function defaultMarkupPercentage(): float
    {
        $profitMargin = (float) config('upstream.profit_margin', 1.20);

        if ($profitMargin >= 10) {
            return round($profitMargin, 2);
        }

        return round(max($profitMargin - 1, 0) * 100, 2);
    }
}
