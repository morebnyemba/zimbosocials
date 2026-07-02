<?php

// app/Http/Controllers/AdminOrderController.php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\Upstream\OrderStatusSyncService;
use App\Services\Upstream\UpstreamProviderClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Order::with(['user:id,name,email', 'service:id,name,category']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('id', $search)
                    ->orWhere('link', 'like', "%{$search}%")
                    ->orWhere('external_order_id', $search)
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($serviceId = $request->query('service_id')) {
            $query->where('service_id', $serviceId);
        }

        if ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($from = $request->query('from')) {
            $query->where('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->where('created_at', '<=', $to.' 23:59:59');
        }

        $orders = $query->latest()->paginate(25)->withQueryString();

        // Consolidated: 1 GROUP BY query instead of 8 separate counts
        $rawCounts = Order::selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $status_counts = [
            'all' => $rawCounts->sum(),
            'pending' => (int) ($rawCounts['pending'] ?? 0),
            'processing' => (int) ($rawCounts['processing'] ?? 0),
            'in_progress' => (int) ($rawCounts['in_progress'] ?? 0),
            'completed' => (int) ($rawCounts['completed'] ?? 0),
            'partial' => (int) ($rawCounts['partial'] ?? 0),
            'cancelled' => (int) ($rawCounts['cancelled'] ?? 0),
            'refunded' => (int) ($rawCounts['refunded'] ?? 0),
        ];

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'filters' => $request->only(['search', 'status', 'service_id', 'user_id', 'from', 'to']),
            'status_counts' => $status_counts,
        ]);
    }

    public function show(Order $order): Response
    {
        $order->load(['user', 'service', 'transaction']);

        return Inertia::render('Admin/Orders/Show', [
            'order' => $order,
        ]);
    }

    public function updateStatus(Order $order, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,processing,in_progress,completed,partial,cancelled,refunded'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $oldStatus = $order->status;
        $updates = ['status' => $data['status']];

        if ($data['status'] === 'completed' && ! $order->completed_at) {
            $updates['completed_at'] = now();
        }
        if (in_array($data['status'], ['processing', 'in_progress']) && ! $order->started_at) {
            $updates['started_at'] = now();
        }

        $order->update($updates);

        AuditLog::dispatchLog(
            'order.status_changed',
            Auth::id(),
            Order::class,
            $order->id,
            ['status' => $oldStatus],
            ['status' => $data['status'], 'notes' => $data['notes'] ?? null],
        );

        NotificationService::notify(
            $order->user_id,
            'order_status_changed',
            'Order #'.$order->id.' Updated',
            "Your order status changed from {$oldStatus} to {$data['status']}.",
            ['order_id' => $order->id, 'old_status' => $oldStatus, 'new_status' => $data['status']]
        );

        return back()->with('success', "Order #{$order->id} status changed to {$data['status']}.");
    }

    /**
     * Immediately re-check this order's status against its upstream provider
     * and apply the result — the same resolution logic the scheduled sync
     * uses (remains=0 is trusted as "fully delivered" even if the provider's
     * own status string never flips to "Completed").
     */
    public function forceSync(Order $order, OrderStatusSyncService $syncService, UpstreamProviderClient $client): RedirectResponse
    {
        $result = $syncService->syncSingleOrder($order, $client);

        AuditLog::dispatchLog(
            'order.force_synced',
            Auth::id(),
            Order::class,
            $order->id,
            null,
            ['changed' => $result['changed'], 'message' => $result['message']],
        );

        return back()->with($result['changed'] ? 'success' : 'info', $result['message']);
    }

    /**
     * Refund an order — atomic with row locking to prevent double-refunds.
     */
    public function refund(Order $order): RedirectResponse
    {
        if ($order->status === 'refunded') {
            return back()->with('error', 'Order is already refunded.');
        }

        try {
            DB::transaction(function () use ($order): void {
                $lockedOrder = Order::lockForUpdate()->findOrFail($order->id);

                if ($lockedOrder->status === 'refunded') {
                    throw new \RuntimeException('Order is already refunded.');
                }

                $lockedOrder->update(['status' => 'refunded']);

                $user = User::lockForUpdate()->findOrFail($lockedOrder->user_id);
                $charge = (float) $lockedOrder->charge;

                $user->creditBalance($charge, 'refund', "Admin refund for order #{$lockedOrder->id}", 'refund');

                AuditLog::log(
                    'order.refunded',
                    Auth::id(),
                    Order::class,
                    $lockedOrder->id,
                    ['status' => $lockedOrder->getOriginal('status')],
                    ['status' => 'refunded', 'refund_amount' => $charge],
                );

                NotificationService::notify(
                    $user->id,
                    'order_refunded',
                    'Order #'.$lockedOrder->id.' Refunded',
                    "Your order has been refunded. \${$charge} has been credited to your account.",
                    ['order_id' => $lockedOrder->id, 'amount' => $charge]
                );
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $charge = (float) $order->charge;
        $user = $order->user;

        return back()->with('success', "Order #{$order->id} refunded. \${$charge} credited to {$user->name}.");
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'service_id' => ['required', 'exists:services,id'],
            'link' => ['required', 'string', 'max:500'],
            'quantity' => ['required', 'integer', 'min:1'],
            'charge' => ['required', 'numeric', 'min:0'],
        ]);

        $order = Order::create([
            'user_id' => $data['user_id'],
            'service_id' => $data['service_id'],
            'link' => $data['link'],
            'quantity' => $data['quantity'],
            'charge' => $data['charge'],
            'status' => 'pending',
        ]);

        AuditLog::dispatchLog(
            'order.created_by_admin',
            Auth::id(),
            Order::class,
            $order->id,
            null,
            $data
        );

        NotificationService::notify(
            $data['user_id'],
            'order_placed',
            'Manual Order Placed',
            "An admin has placed an order (#{$order->id}) on your behalf."
        );

        return back()->with('success', "Order #{$order->id} created successfully.");
    }
}
