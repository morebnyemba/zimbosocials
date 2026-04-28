<?php

namespace App\Http\Controllers;

use App\Models\BusinessContract;
use App\Models\ContractProofSubmission;
use App\Models\Order;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function index(): Response
    {
        $stats = [
            'users'           => User::count(),
            'active_users'    => User::where('is_active', true)->count(),
            'marketers'       => User::whereIn('role', ['marketer', 'reseller'])->count(),
            'admins'          => User::where('role', 'admin')->count(),
            'services'        => Service::active()->count(),
            'orders'          => Order::count(),
            'active_orders'   => Order::active()->count(),
            'open_tickets'    => Ticket::whereIn('status', ['open', 'pending'])->count(),
            'revenue'         => Order::sum('charge'),
            'today_revenue'   => Order::where('created_at', '>=', now()->startOfDay())->sum('charge'),
            'month_revenue'   => Order::where('created_at', '>=', now()->startOfMonth())->sum('charge'),
            'pending_deposits'    => Transaction::where('type', 'deposit')->where('status', 'pending')->count(),
            'pending_withdrawals' => Transaction::where('type', 'withdrawal')->where('status', 'pending')->count(),
            'pending_marketers'   => User::whereIn('role', ['marketer', 'reseller'])->where('marketer_status', 'pending')->count(),
            'total_contracts'     => BusinessContract::count(),
        ];

        $recent_orders = Order::with(['user:id,name,email', 'service:id,name,name_sn,category'])
            ->latest()
            ->limit(10)
            ->get();

        $pending_proofs = ContractProofSubmission::with([
            'marketer:id,name,email',
            'contractApplication.contract:id,title,platform',
        ])
            ->where('status', 'pending')
            ->latest()
            ->limit(20)
            ->get();

        $daily_revenue = Order::selectRaw('DATE(created_at) as date, SUM(charge) as total, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(14))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $orders_by_status = Order::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $new_users_weekly = User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(14))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $recent_users = User::latest()->limit(5)->get(['id', 'name', 'email', 'role', 'created_at']);

        return Inertia::render('Admin/Dashboard', compact(
            'stats', 'recent_orders', 'pending_proofs',
            'daily_revenue', 'orders_by_status', 'new_users_weekly', 'recent_users'
        ));
    }
}
