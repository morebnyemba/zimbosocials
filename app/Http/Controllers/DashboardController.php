<?php
// app/Http/Controllers/DashboardController.php

namespace App\Http\Controllers;

use App\Models\BusinessContract;
use App\Models\ContractApplication;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        \Illuminate\Support\Facades\Log::info('Dashboard Hit', [
            'id' => $user->id,
            'account_type' => $user->account_type,
            'role' => $user->role
        ]);
        $userId = (int) $user->getAuthIdentifier();

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->hasMarketerAccess()) {
            return redirect()->route('marketer.dashboard');
        }

        $stats = [
            'total_orders'      => Order::forUser($userId)->count(),
            'active_orders'     => Order::forUser($userId)->active()->count(),
            'completed_orders'  => Order::forUser($userId)->byStatus('completed')->count(),
            'total_spent'       => Order::forUser($userId)->sum('charge'),
            'services_available'=> \App\Models\Service::active()->count(),
            'orders_today'      => Order::forUser($userId)->whereDate('created_at', today())->count(),
        ];

        $recent_orders = Order::with('service')
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

        $contract_stats = [
            'open_contracts' => BusinessContract::where('user_id', $userId)->where('status', 'open')->count(),
            'total_contracts' => BusinessContract::where('user_id', $userId)->count(),
            'pending_applications' => ContractApplication::where('status', 'pending')
                ->whereHas('contract', fn ($query) => $query->where('user_id', $userId))
                ->count(),
        ];

        return Inertia::render('Dashboard', [
            'stats'               => $stats,
            'recent_orders'       => $recent_orders,
            'recent_transactions' => $recent_transactions,
            'category_counts'     => $category_counts,
            'business_contracts'  => $business_contracts,
            'incoming_contract_applications' => $incoming_contract_applications,
            'contract_stats'      => $contract_stats,
        ]);
    }
}
