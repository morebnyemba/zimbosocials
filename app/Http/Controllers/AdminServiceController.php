<?php

// app/Http/Controllers/AdminServiceController.php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Service;
use App\Models\UpstreamProvider;
use App\Services\AI\ServiceEnricher;
use App\Services\AI\ServiceListFormatter;
use Illuminate\Http\JsonResponse;
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
        $query = Service::withCount('orders')->with(['upstreams.provider', 'promoBundles']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('name_sn', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('id', $search)
                    // Also match the upstream/provider service id an admin pastes
                    // from the provider panel (exact, plus a loose contains).
                    ->orWhereHas('upstreams', function ($u) use ($search) {
                        $u->where('external_service_id', $search)
                            ->orWhere('external_service_id', 'like', "%{$search}%");
                    });
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

        // Category -> service count, for the "Merge Categories" tool — lets an
        // admin see at a glance which raw category strings likely represent
        // the same platform (e.g. "Instagram" (40) and "instagram" (3)) and
        // unify them, independent of the automatic keyword-based normalizer
        // (which only recognizes a fixed list of platforms and can't handle
        // arbitrary/custom categories the admin wants merged).
        $categoryCounts = Service::selectRaw('category, COUNT(*) as cnt')
            ->groupBy('category')
            ->orderBy('category')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->category => $row->cnt]);

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
            'categoryCounts' => $categoryCounts,
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
            'upstreams.*.link_type' => ['nullable', 'in:url,username'],
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
                        'link_type' => $upstream['link_type'] ?? 'url',
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
            'upstreams.*.link_type' => ['nullable', 'in:url,username'],
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
                        'link_type' => $upstream['link_type'] ?? 'url',
                        'priority' => $upstream['priority'],
                        'is_active' => true,
                    ];

                    // Repointing a route at a different provider service makes the
                    // cached provider cost belong to the old one — reset it to 0
                    // ("unknown") so the admin UI doesn't show a wrong cost until
                    // the nightly service sync fetches the real rate. The margin is
                    // unknown too (NULL): the sync derives it from the current
                    // price on first fetch, so the price never resets to a default.
                    if ($existing && (string) $existing->external_service_id !== (string) $upstream['external_service_id']) {
                        $values['external_rate'] = 0;
                        $values['markup_value'] = null;
                    }
                    if (! $existing) {
                        $values['markup_value'] = null;
                    }

                    $service->upstreams()->updateOrCreate(
                        ['upstream_provider_id' => $upstream['upstream_provider_id']],
                        $values
                    );
                }
            } else {
                $service->upstreams()->delete();
            }

            // Make the manually entered rate durable: recompute the primary upstream's
            // stored markup from it so the nightly sync reproduces this price instead
            // of resetting it. Skipped when the provider cost is unknown (repointed → 0).
            $primary = $service->upstreams()->first();
            if ($primary && (float) $primary->external_rate > 0 && (float) $data['rate'] > 0) {
                $markup = round((((float) $data['rate'] / (float) $primary->external_rate) - 1) * 100, 4);
                $primary->update([
                    'markup_type' => 'percentage',
                    'markup_value' => max($markup, 0),
                ]);
            }

            AuditLog::log('service.updated', Auth::id(), Service::class, $service->id, $old, $data);
        });

        return back()->with('success', "Service \"{$service->name}\" updated.");
    }

    /**
     * Create or update a flat-price promo bundle ("3,000 followers for $12").
     * Keyed on service + exact quantity, so re-saving the same pair edits it.
     */
    public function storeBundle(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'exists:services,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'label' => ['nullable', 'string', 'max:60'],
        ]);

        $service = Service::findOrFail($data['service_id']);

        // A "promo" that costs more than the normal rate is a pricing mistake.
        $normal = round(((int) $data['quantity'] / 1000) * (float) $service->rate, 2);
        if ((float) $data['price'] >= $normal && $normal > 0) {
            return back()->with('error',
                "That's not a discount — {$data['quantity']} normally costs {$normal}. Set a lower price.");
        }

        \App\Models\PromoBundle::updateOrCreate(
            ['service_id' => $data['service_id'], 'quantity' => $data['quantity']],
            ['price' => $data['price'], 'label' => $data['label'] ?? null, 'is_active' => true],
        );

        return back()->with('success', 'Promo bundle saved.');
    }

    public function destroyBundle(\App\Models\PromoBundle $bundle): RedirectResponse
    {
        $bundle->delete();

        return back()->with('success', 'Promo bundle removed.');
    }

    /**
     * AI-enhance one or more service NAMES (plus Shona/Ndebele translations):
     * cleans ALL-CAPS, strips provider junk/codes/emojis, and localizes. Batched
     * into a single Gemini call. Descriptions are left untouched — this only
     * polishes the customer-facing names. Degrades gracefully when AI is off.
     */
    public function enhanceNames(Request $request, ServiceEnricher $enricher): RedirectResponse
    {
        $data = $request->validate([
            'service_ids' => ['required', 'array', 'min:1', 'max:100'],
            'service_ids.*' => ['integer', 'exists:services,id'],
        ]);

        if (! $enricher->isAvailable()) {
            return back()->with('error', 'AI enhancer is unavailable (Gemini not configured).');
        }

        $services = Service::whereIn('id', $data['service_ids'])->get();
        if ($services->isEmpty()) {
            return back()->with('info', 'No services to enhance.');
        }

        // enrich() keys by the 'service' field — use the local id so we can map back.
        $map = $enricher->enrich($services->map(fn (Service $s): array => [
            'service' => (string) $s->id,
            'name' => $s->name,
            'category' => $s->category,
        ])->all());

        $updated = 0;
        $collided = 0;
        // Guard the catalogue: an over-eager model can strip the very details
        // that tell sibling services apart and hand six of them the same title.
        // Any name that would collide with another service is refused outright —
        // that service simply keeps its original name.
        $claimed = [];

        foreach ($services as $service) {
            $entry = $map[(string) $service->id] ?? null;
            if (! $entry || empty($entry['name'])) {
                continue;
            }

            $newName = (string) $entry['name'];
            $key = mb_strtolower(trim($newName));

            $collides = isset($claimed[$key])
                || Service::where('name', $newName)->where('id', '!=', $service->id)->exists();

            if ($collides) {
                $collided++;

                continue;
            }
            $claimed[$key] = true;

            // Names only — keep any curated descriptions.
            $updates = array_filter([
                'name' => $newName,
                'name_sn' => $entry['name_sn'] ?? null,
                'name_nd' => $entry['name_nd'] ?? null,
            ], fn ($v) => is_string($v) && $v !== '');

            if ($updates === []) {
                continue;
            }

            $old = $service->name;
            $service->update($updates);
            $updated++;

            AuditLog::log('service.ai_enhanced', Auth::id(), Service::class, $service->id,
                ['name' => $old], ['name' => $service->name]);
        }

        $kept = $collided > 0
            ? " {$collided} kept their original name (the suggestion clashed with another service)."
            : '';

        if ($updated === 0) {
            return back()->with('info', 'No names changed — the AI\'s suggestions were unusable or clashed with existing services.');
        }

        return back()->with('success', ($updated === 1
            ? 'AI enhanced 1 service name.'
            : "AI enhanced {$updated} service names.").$kept);
    }

    /**
     * Merge two or more raw category strings into one canonical category —
     * for cases the automatic import-time normalizer can't handle (custom,
     * non-platform categories), or to clean up data imported before that
     * normalizer existed without needing shell access to run the CLI backfill.
     */
    public function mergeCategories(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['required', 'string'],
            'target' => ['required', 'string', 'max:100'],
        ]);

        $target = trim($data['target']);
        // Merging into a category that isn't itself one of the selected
        // sources still counts as a rename of every selected source.
        $sources = array_unique($data['categories']);

        $affected = Service::whereIn('category', $sources)->count();

        if ($affected === 0) {
            return back()->with('info', 'No services matched the selected categories.');
        }

        DB::transaction(function () use ($sources, $target): void {
            Service::whereIn('category', $sources)->update(['category' => $target]);
        });

        AuditLog::log(
            'service.categories_merged',
            Auth::id(),
            Service::class,
            null,
            ['categories' => $sources],
            ['target' => $target, 'services_affected' => $affected],
        );

        $sourceList = implode(', ', $sources);

        return back()->with('success', "Merged {$affected} service(s) from [{$sourceList}] into \"{$target}\".");
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

    /**
     * Plain-text, WhatsApp-ready service catalog (name, price per 1000, minimum
     * order), grouped by category. Deliberately mechanical rather than AI-
     * generated — rate/min_qty are financial data pulled straight from the
     * services table, so there's nothing for an LLM to "clean up" and every
     * reason not to risk it paraphrasing a price.
     */
    public function exportList(Request $request): JsonResponse
    {
        return response()->json(['text' => $this->buildPlainServiceList($request->query('category'))]);
    }

    /**
     * Same data as exportList(), restyled for a specific platform by
     * ServiceListFormatter. The mechanical list is always built here (never
     * trusted from the client) and handed to Gemini as fixed source-of-truth
     * text — the model only changes tone/formatting, never the figures.
     */
    public function exportListAi(Request $request, ServiceListFormatter $formatter): JsonResponse
    {
        $data = $request->validate([
            'platform' => ['required', 'string', 'max:40'],
            'category' => ['nullable', 'string'],
        ]);

        $raw = $this->buildPlainServiceList($data['category'] ?? null);

        if (! $formatter->isAvailable()) {
            return response()->json(['text' => $raw, 'ai_used' => false]);
        }

        $enhanced = $formatter->format($raw, $data['platform']);

        return response()->json([
            'text' => $enhanced ?? $raw,
            'ai_used' => $enhanced !== null,
        ]);
    }

    private function buildPlainServiceList(?string $category): string
    {
        $query = Service::active()->orderBy('category')->orderBy('name');

        if ($category) {
            $query->where('category', $category);
        }

        $services = $query->get(['name', 'category', 'rate', 'min_qty']);

        $lines = ['*Zimbo Socials — Service List*', ''];

        foreach ($services->groupBy('category') as $groupCategory => $items) {
            $lines[] = "*{$groupCategory}*";
            foreach ($items as $service) {
                $rate = number_format((float) $service->rate, 2);
                $lines[] = "• {$service->name} — \${$rate}/1000 (min: {$service->min_qty})";
            }
            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }
}
