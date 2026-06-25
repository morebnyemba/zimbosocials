<?php

// app/Http/Controllers/DashboardController.php

namespace App\Http\Controllers;

use App\Models\BusinessContract;
use App\Models\ContractApplication;
use App\Models\Order;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AI\ServiceRecommendationEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(ServiceRecommendationEngine $recommendations): Response|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $userId = (int) $user->getAuthIdentifier();

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->hasMarketerAccess()) {
            return redirect()->route('marketer.dashboard');
        }

        // Consolidated order stats — 1 query instead of 6
        $orderStatsRow = Order::forUser($userId)
            ->selectRaw("
                COUNT(*)                                                                     AS total_orders,
                SUM(CASE WHEN status IN ('pending','processing','in_progress') THEN 1 ELSE 0 END) AS active_orders,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END)                       AS completed_orders,
                SUM(charge)                                                                  AS total_spent,
                SUM(CASE WHEN DATE(created_at) = DATE('now') THEN 1 ELSE 0 END)              AS orders_today
            ")
            ->first();

        $stats = [
            'total_orders' => (int) ($orderStatsRow->total_orders ?? 0),
            'active_orders' => (int) ($orderStatsRow->active_orders ?? 0),
            'completed_orders' => (int) ($orderStatsRow->completed_orders ?? 0),
            'total_spent' => (float) ($orderStatsRow->total_spent ?? 0),
            'services_available' => Service::active()->count(),
            'orders_today' => (int) ($orderStatsRow->orders_today ?? 0),
        ];

        $recent_orders = Order::with('service:id,name,category')
            ->forUser($userId)
            ->latest()
            ->limit(5)
            ->get();

        $recent_transactions = Transaction::where('user_id', $userId)
            ->latest()
            ->limit(5)
            ->get();

        $category_counts = Order::forUser($userId)
            ->join('services', 'orders.service_id', '=', 'services.id')
            ->selectRaw('services.category, COUNT(*) as count')
            ->groupBy('services.category')
            ->pluck('count', 'category');

        // Consolidated contract queries — 1 query for contracts + 1 for applications
        $business_contracts = BusinessContract::where('user_id', $userId)
            ->withCount([
                'applications',
                'applications as pending_applications_count' => fn ($query) => $query->where('status', 'pending'),
            ])
            ->latest()
            ->limit(6)
            ->get();

        $incoming_contract_applications = ContractApplication::with([
            'contract:id,user_id,title,platform,status',
            'marketer:id,name,email',
            'marketer.socialLinks',
        ])
            ->where('status', 'pending')
            ->whereHas('contract', fn ($query) => $query->where('user_id', $userId))
            ->latest()
            ->limit(8)
            ->get();

        // Consolidated contract stats — 1 query instead of 3
        $contractStatsRow = BusinessContract::where('user_id', $userId)
            ->selectRaw("
                COUNT(*)                                                   AS total_contracts,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END)          AS open_contracts
            ")
            ->first();

        $contract_stats = [
            'open_contracts' => (int) ($contractStatsRow->open_contracts ?? 0),
            'total_contracts' => (int) ($contractStatsRow->total_contracts ?? 0),
            'pending_applications' => $incoming_contract_applications->count(),
        ];

        $recommendedServices = Cache::remember("user:{$userId}:recommendations", 3600, function () use ($recommendations, $user) {
            $recommendationsList = $recommendations->recommendFor($user, 4);

            if ($recommendationsList === []) {
                return [];
            }

            $idReasonMap = collect($recommendationsList)->keyBy('id');
            $idOrder = $idReasonMap->keys()->flip();

            return Service::active()
                ->whereIn('id', $idOrder->keys()->toArray())
                ->get()
                ->sortBy(fn ($service) => $idOrder[$service->id] ?? 999)
                ->values()
                ->map(fn ($service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'category' => $service->category,
                    'description' => $service->description,
                    'min_qty' => $service->min_qty,
                    'max_qty' => $service->max_qty,
                    'rate' => $service->rate,
                    'avg_time_minutes' => $service->avg_time_minutes,
                    'reason' => $idReasonMap[$service->id]['reason'] ?? null,
                ])
                ->toArray();
        });

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recent_orders' => $recent_orders,
            'recent_transactions' => $recent_transactions,
            'category_counts' => $category_counts,
            'business_contracts' => $business_contracts,
            'incoming_contract_applications' => $incoming_contract_applications,
            'contract_stats' => $contract_stats,
            'recommended_services' => $recommendedServices,
        ]);
    }
}
