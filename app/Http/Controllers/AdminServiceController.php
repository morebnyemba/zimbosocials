<?php

// app/Http/Controllers/AdminServiceController.php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Service;
use App\Models\UpstreamProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminServiceController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Service::withCount('orders')->with('upstreams.provider');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('name_sn', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('id', $search);
            });
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($request->query('active') === '1') {
            $query->where('is_active', true);
        } elseif ($request->query('active') === '0') {
            $query->where('is_active', false);
        }

        $services = $query->orderBy('category')
            ->orderBy('display_order')
            ->paginate(30)
            ->withQueryString();

        $categories = Service::select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        // Consolidated: 1 GROUP BY instead of 3 separate counts
        $rawCounts = Service::selectRaw('is_active, COUNT(*) as cnt')
            ->groupBy('is_active')
            ->pluck('cnt', 'is_active');

        $stats = [
            'total' => $rawCounts->sum(),
            'active' => (int) ($rawCounts[1] ?? $rawCounts['1'] ?? 0),
            'inactive' => (int) ($rawCounts[0] ?? $rawCounts['0'] ?? 0),
        ];

        return Inertia::render('Admin/Services/Index', [
            'services' => $services,
            'categories' => $categories,
            'stats' => $stats,
            'providers' => UpstreamProvider::where('is_active', true)->get(),
            'filters' => $request->only(['search', 'category', 'active']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_sn' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'description_sn' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', 'max:50'],
            'rate' => ['required', 'numeric', 'min:0'],
            'min_qty' => ['required', 'integer', 'min:1'],
            'max_qty' => ['required', 'integer', 'min:1'],
            'upstream_service_id' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'is_dripfeed' => ['nullable', 'boolean'],
            'is_refill' => ['nullable', 'boolean'],
            'refill_days' => ['nullable', 'integer', 'min:0'],
            'avg_time_minutes' => ['nullable', 'integer', 'min:0'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'upstreams' => ['nullable', 'array'],
            'upstreams.*.upstream_provider_id' => ['required', 'exists:upstream_providers,id'],
            'upstreams.*.external_service_id' => ['required', 'string', 'max:50'],
            'upstreams.*.priority' => ['required', 'integer', 'min:1'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['is_dripfeed'] = $request->boolean('is_dripfeed');
        $data['is_refill'] = $request->boolean('is_refill');

        $service = DB::transaction(function () use ($data) {
            $service = Service::create($data);

            if (isset($data['upstreams'])) {
                foreach ($data['upstreams'] as $upstream) {
                    $service->upstreams()->create([
                        'upstream_provider_id' => $upstream['upstream_provider_id'],
                        'external_service_id' => $upstream['external_service_id'],
                        'priority' => $upstream['priority'],
                        'is_active' => true,
                    ]);
                }
            }

            AuditLog::log('service.created', Auth::id(), Service::class, $service->id, null, $data);

            return $service;
        });

        return back()->with('success', "Service \"{$service->name}\" created.");
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_sn' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'description_sn' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', 'max:50'],
            'rate' => ['required', 'numeric', 'min:0'],
            'min_qty' => ['required', 'integer', 'min:1'],
            'max_qty' => ['required', 'integer', 'min:1'],
            'upstream_service_id' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'is_dripfeed' => ['nullable', 'boolean'],
            'is_refill' => ['nullable', 'boolean'],
            'refill_days' => ['nullable', 'integer', 'min:0'],
            'avg_time_minutes' => ['nullable', 'integer', 'min:0'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'upstreams' => ['nullable', 'array'],
            'upstreams.*.upstream_provider_id' => ['required', 'exists:upstream_providers,id'],
            'upstreams.*.external_service_id' => ['required', 'string', 'max:50'],
            'upstreams.*.priority' => ['required', 'integer', 'min:1'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['is_dripfeed'] = $request->boolean('is_dripfeed');
        $data['is_refill'] = $request->boolean('is_refill');

        $old = $service->toArray();

        DB::transaction(function () use ($service, $data, $old) {
            $service->update($data);

            if (isset($data['upstreams'])) {
                // Remove upstreams that are not in the new list
                $newProviderIds = collect($data['upstreams'])->pluck('upstream_provider_id')->toArray();
                $service->upstreams()->whereNotIn('upstream_provider_id', $newProviderIds)->delete();

                foreach ($data['upstreams'] as $upstream) {
                    $existing = $service->upstreams()
                        ->where('upstream_provider_id', $upstream['upstream_provider_id'])
                        ->first();

                    $values = [
                        'external_service_id' => $upstream['external_service_id'],
                        'priority' => $upstream['priority'],
                        'is_active' => true,
                    ];

                    // Repointing a route at a different provider service makes the
                    // cached provider cost belong to the old one — reset it to 0
                    // ("unknown") so the admin UI doesn't show a wrong cost until
                    // the nightly service sync fetches the real rate.
                    if ($existing && (string) $existing->external_service_id !== (string) $upstream['external_service_id']) {
                        $values['external_rate'] = 0;
                    }

                    $service->upstreams()->updateOrCreate(
                        ['upstream_provider_id' => $upstream['upstream_provider_id']],
                        $values
                    );
                }
            } else {
                $service->upstreams()->delete();
            }

            AuditLog::log('service.updated', Auth::id(), Service::class, $service->id, $old, $data);
        });

        return back()->with('success', "Service \"{$service->name}\" updated.");
    }

    public function destroy(Service $service): RedirectResponse
    {
        $name = $service->name;
        $old = $service->toArray();

        $service->update(['is_active' => false]);

        AuditLog::log('service.deactivated', Auth::id(), Service::class, $service->id, $old, ['is_active' => false]);

        return back()->with('success', "Service \"{$name}\" has been deactivated.");
    }

    /**
     * Permanently delete every inactive service that has never had an order
     * placed against it. orders.service_id is restrictOnDelete, so a service
     * with order history is physically undeletable regardless — we still
     * pre-filter on orders_count so the admin gets a clear summary instead
     * of a DB constraint exception mid-batch, and so a partial failure can't
     * leave some services deleted and others not (single transaction).
     */
    public function bulkDeleteInactive(): RedirectResponse
    {
        $candidates = Service::withCount('orders')
            ->where('is_active', false)
            ->get();

        $deletable = $candidates->where('orders_count', 0);
        $keptForHistory = $candidates->count() - $deletable->count();

        if ($deletable->isEmpty()) {
            $message = $candidates->isEmpty()
                ? 'No inactive services to delete.'
                : "All {$candidates->count()} inactive service(s) have order history and were kept — nothing was deleted.";

            return back()->with('info', $message);
        }

        DB::transaction(function () use ($deletable): void {
            foreach ($deletable as $service) {
                $old = $service->toArray();
                $service->delete();

                AuditLog::log('service.bulk_deleted', Auth::id(), Service::class, $service->id, $old, null);
            }
        });

        $message = "Permanently deleted {$deletable->count()} inactive service(s) with no order history.";
        if ($keptForHistory > 0) {
            $message .= " {$keptForHistory} other inactive service(s) have order history and were kept (deactivated only).";
        }

        return back()->with('success', $message);
    }
}
