<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DepositService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class AdminTransactionController extends Controller
{
    public function __construct(
        private readonly DepositService $depositService,
    ) {}

    public function index(Request $request): Response
    {
        $query = Transaction::with('user:id,name,email');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('id', $search)
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $transactions = $query->latest()->paginate(25)->withQueryString();

        // Cache pending counts for 60 seconds to reduce repetitive aggregate queries
        $pendingDeposits = Cache::remember('admin:pending_deposits', 60, function () {
            return Transaction::where('type', 'deposit')->where('status', 'pending')->count();
        });

        $pendingWithdrawals = Cache::remember('admin:pending_withdrawals', 60, function () {
            return Transaction::where('type', 'withdrawal')->where('status', 'pending')->count();
        });

        return Inertia::render('Admin/Transactions/Index', [
            'transactions' => $transactions,
            'filters' => $request->only(['search', 'type', 'status']),
            'pending_deposits' => $pendingDeposits,
            'pending_withdrawals' => $pendingWithdrawals,
        ]);
    }

    public function approveDeposit(Transaction $transaction): RedirectResponse
    {
        if ($transaction->type !== 'deposit' || $transaction->status !== 'pending') {
            return back()->with('error', 'Cannot approve this transaction.');
        }

        $credited = $this->depositService->credit($transaction, 'admin_approval');

        if (! $credited) {
            return back()->with('error', 'Transaction was already processed.');
        }

        // Update admin tracking fields
        $transaction->update([
            'processed_by' => Auth::id(),
            'processed_at' => now(),
        ]);

        // Bust admin dashboard caches
        Cache::forget('admin:pending_deposits');

        $amount = (float) $transaction->amount;
        $user = $transaction->user;

        return back()->with('success', "Deposit of \${$amount} approved for {$user->name}.");
    }

    public function rejectDeposit(Transaction $transaction): RedirectResponse
    {
        if ($transaction->type !== 'deposit' || $transaction->status !== 'pending') {
            return back()->with('error', 'Cannot reject this transaction.');
        }

        $oldStatus = (string) $transaction->status;

        $transaction->update([
            'status' => 'failed', 'processed_by' => Auth::id(),
            'processed_at' => now(), 'admin_notes' => 'Rejected by admin',
        ]);

        AuditLog::dispatchLog(
            action: 'transaction.deposit_rejected',
            userId: (int) Auth::id(),
            modelType: Transaction::class,
            modelId: (int) $transaction->id,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'failed'],
        );

        NotificationService::notify($transaction->user_id, 'deposit_rejected', 'Deposit Rejected',
            'Your deposit request has been rejected.');

        // Bust admin dashboard caches
        Cache::forget('admin:pending_deposits');

        return back()->with('success', 'Deposit rejected.');
    }

    public function processWithdrawal(Transaction $transaction): RedirectResponse
    {
        if ($transaction->type !== 'withdrawal' || $transaction->status !== 'pending') {
            return back()->with('error', 'Cannot process this withdrawal.');
        }

        $oldStatus = (string) $transaction->status;

        $transaction->update([
            'status' => 'completed', 'processed_by' => Auth::id(), 'processed_at' => now(),
        ]);

        AuditLog::dispatchLog(
            action: 'transaction.withdrawal_processed',
            userId: (int) Auth::id(),
            modelType: Transaction::class,
            modelId: (int) $transaction->id,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'completed'],
        );

        NotificationService::notify($transaction->user_id, 'withdrawal_processed', 'Withdrawal Processed',
            'Your withdrawal of $'.abs((float) $transaction->amount).' has been processed.');

        // Bust admin dashboard caches
        Cache::forget('admin:pending_withdrawals');

        return back()->with('success', 'Withdrawal processed.');
    }

    public function revenue(Request $request): Response
    {
        $days = (int) ($request->query('days') ?? 30);

        $daily_revenue = Order::selectRaw('DATE(created_at) as date, SUM(charge) as total, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')->orderBy('date')->get();

        $top_services = Service::withCount('orders')
            ->withSum('orders', 'charge')
            ->orderByDesc('orders_sum_charge')->limit(10)->get();

        $new_users = User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')->orderBy('date')->get();

        $orders_by_status = Order::selectRaw('status, COUNT(*) as count, SUM(charge) as revenue')
            ->groupBy('status')->get();

        // Consolidated summary — 1 query instead of 6
        $summary = Cache::remember("admin:revenue_summary:{$days}", 120, function () {
            return [
                'total_revenue' => Order::sum('charge'),
                'month_revenue' => Order::where('created_at', '>=', now()->startOfMonth())->sum('charge'),
                'today_revenue' => Order::where('created_at', '>=', now()->startOfDay())->sum('charge'),
                'total_users' => User::count(),
                'total_orders' => Order::count(),
                'avg_order_value' => Order::avg('charge'),
            ];
        });

        return Inertia::render('Admin/Revenue', [
            'daily_revenue' => $daily_revenue,
            'top_services' => $top_services,
            'new_users' => $new_users,
            'orders_by_status' => $orders_by_status,
            'summary' => $summary,
            'days' => $days,
        ]);
    }
}
