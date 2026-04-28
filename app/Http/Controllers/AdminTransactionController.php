<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\NotificationService;
use App\Services\ReferralService;
use App\Models\Order;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Models\BusinessContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AdminTransactionController extends Controller
{
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

        return Inertia::render('Admin/Transactions/Index', [
            'transactions'        => $transactions,
            'filters'             => $request->only(['search', 'type', 'status']),
            'pending_deposits'    => Transaction::where('type', 'deposit')->where('status', 'pending')->count(),
            'pending_withdrawals' => Transaction::where('type', 'withdrawal')->where('status', 'pending')->count(),
        ]);
    }

    public function approveDeposit(Transaction $transaction, ReferralService $referralService): RedirectResponse
    {
        if ($transaction->type !== 'deposit' || $transaction->status !== 'pending') {
            return back()->with('error', 'Cannot approve this transaction.');
        }

        $oldStatus = (string) $transaction->status;

        $user   = $transaction->user;
        $amount = (float) $transaction->amount;
        $before = (float) $user->balance;

        $user->increment('balance', $amount);
        $transaction->update([
            'status' => 'completed', 'balance_after' => $before + $amount,
            'processed_by' => Auth::id(), 'processed_at' => now(),
        ]);

        $referralService->rewardReferrerOnFirstDeposit($transaction->fresh());

        AuditLog::log(
            action: 'transaction.deposit_approved',
            userId: (int) Auth::id(),
            modelType: Transaction::class,
            modelId: (int) $transaction->id,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'completed'],
        );
        NotificationService::notify($user->id, 'deposit_confirmed', 'Deposit Confirmed',
            "Your deposit of \${$amount} has been confirmed.");

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

        AuditLog::log(
            action: 'transaction.deposit_rejected',
            userId: (int) Auth::id(),
            modelType: Transaction::class,
            modelId: (int) $transaction->id,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'failed'],
        );
        NotificationService::notify($transaction->user_id, 'deposit_rejected', 'Deposit Rejected',
            'Your deposit request has been rejected.');

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

        AuditLog::log(
            action: 'transaction.withdrawal_processed',
            userId: (int) Auth::id(),
            modelType: Transaction::class,
            modelId: (int) $transaction->id,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'completed'],
        );
        NotificationService::notify($transaction->user_id, 'withdrawal_processed', 'Withdrawal Processed',
            "Your withdrawal of \$" . abs((float)$transaction->amount) . " has been processed.");

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

        $summary = [
            'total_revenue'   => Order::sum('charge'),
            'month_revenue'   => Order::where('created_at', '>=', now()->startOfMonth())->sum('charge'),
            'today_revenue'   => Order::where('created_at', '>=', now()->startOfDay())->sum('charge'),
            'total_users'     => User::count(),
            'total_orders'    => Order::count(),
            'avg_order_value' => Order::avg('charge'),
        ];

        return Inertia::render('Admin/Revenue', [
            'daily_revenue'    => $daily_revenue,
            'top_services'     => $top_services,
            'new_users'        => $new_users,
            'orders_by_status' => $orders_by_status,
            'summary'          => $summary,
            'days'             => $days,
        ]);
    }
}
