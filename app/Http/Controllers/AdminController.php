<?php

namespace App\Http\Controllers;

use App\Models\BusinessContract;
use App\Models\ContractProofSubmission;
use App\Models\Order;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AI\AnalyticsSummarizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function index(): Response
    {
        // Cache admin dashboard stats for 60 seconds to avoid query storm on refresh
        $stats = Cache::remember('admin:dashboard_stats', 60, function () {
            // User counts — 1 query instead of 4
            $userCounts = User::selectRaw("
                COUNT(*)                                                             AS total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END)                      AS active,
                SUM(CASE WHEN role IN ('marketer','reseller') THEN 1 ELSE 0 END)     AS marketers,
                SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END)                     AS admins,
                SUM(CASE WHEN role IN ('marketer','reseller') AND marketer_status = 'pending' THEN 1 ELSE 0 END) AS pending_marketers
            ")->first();

            // Order counts + revenue — 1 query instead of 5
            $orderStats = Order::selectRaw("
                COUNT(*)                                                                            AS total,
                SUM(CASE WHEN status IN ('pending','processing','in_progress') THEN 1 ELSE 0 END)   AS active,
                SUM(charge)                                                                         AS revenue,
                SUM(CASE WHEN created_at >= ? THEN charge ELSE 0 END)                               AS today_revenue,
                SUM(CASE WHEN created_at >= ? THEN charge ELSE 0 END)                               AS month_revenue
            ", [now()->startOfDay(), now()->startOfMonth()])->first();

            // Transaction pending counts — 1 query instead of 2
            $txPending = Transaction::where('status', 'pending')
                ->selectRaw("
                    SUM(CASE WHEN type = 'deposit' THEN 1 ELSE 0 END)    AS pending_deposits,
                    SUM(CASE WHEN type = 'withdrawal' THEN 1 ELSE 0 END) AS pending_withdrawals
                ")
                ->first();

            return [
                'users' => (int) ($userCounts->total ?? 0),
                'active_users' => (int) ($userCounts->active ?? 0),
                'marketers' => (int) ($userCounts->marketers ?? 0),
                'admins' => (int) ($userCounts->admins ?? 0),
                'pending_marketers' => (int) ($userCounts->pending_marketers ?? 0),
                'services' => Service::active()->count(),
                'orders' => (int) ($orderStats->total ?? 0),
                'active_orders' => (int) ($orderStats->active ?? 0),
                'open_tickets' => Ticket::whereIn('status', ['open', 'pending'])->count(),
                'revenue' => (float) ($orderStats->revenue ?? 0),
                'today_revenue' => (float) ($orderStats->today_revenue ?? 0),
                'month_revenue' => (float) ($orderStats->month_revenue ?? 0),
                'pending_deposits' => (int) ($txPending->pending_deposits ?? 0),
                'pending_withdrawals' => (int) ($txPending->pending_withdrawals ?? 0),
                'total_contracts' => BusinessContract::count(),
            ];
        });

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

    public function aiSummary(Request $request, AnalyticsSummarizer $summarizer): JsonResponse
    {
        $days = (int) $request->query('days', 7);
        $days = in_array($days, [7, 14, 30, 90], true) ? $days : 7;

        $cacheKey = "admin:ai_summary:{$days}";

        $summary = Cache::remember($cacheKey, 3600, function () use ($summarizer, $days) {
            $stats = Cache::get('admin:dashboard_stats') ?? [];

            $dailyRevenue = Order::selectRaw('DATE(created_at) as date, SUM(charge) as total, COUNT(*) as count')
                ->where('created_at', '>=', now()->subDays($days))
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->toArray();

            $ordersByStatus = Order::selectRaw('status, COUNT(*) as count')
                ->where('created_at', '>=', now()->subDays($days))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            return $summarizer->summarize($stats, $dailyRevenue, $ordersByStatus, $days);
        });

        if ($summary === null) {
            return response()->json(['summary' => null, 'message' => 'AI summarizer is not available.'], 503);
        }

        return response()->json(['summary' => $summary, 'days' => $days]);
    }
}
