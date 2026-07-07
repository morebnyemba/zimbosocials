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

    public function updateStatus(Order $order, Request $request, \App\Services\ReferralService $referralService): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,processing,in_progress,completed,partial,cancelled,refunded'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        // 'refunded' set through here never credits the wallet — the order
        // would claim refunded while the user's money stayed gone. Force the
        // dedicated refund action, which pays the un-refunded remainder.
        if ($data['status'] === 'refunded') {
            return back()->with('error', 'Use the Refund action to refund an order — it credits the wallet and sets the status together.');
        }

        $oldStatus = $order->status;
        $updates = ['status' => $data['status']];

        if ($data['status'] === 'completed' && ! $order->completed_at) {
            $updates['completed_at'] = now();
        }
        if (in_array($data['status'], ['processing', 'in_progress']) && ! $order->started_at) {
            $updates['started_at'] = now();
        }

        $order->update($updates);

        // Same completion-time referral commission as the automated sync path.
        if ($data['status'] === 'completed') {
            $referralService->rewardReferrerOnReferredOrder($order);
        }

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
     * Refund an order — atomic with row locking, and capped at the amount not
     * already refunded. An order that was auto-refunded (cancelled/partial by
     * the upstream sync) or whose status was set to 'refunded' without a
     * wallet credit is handled correctly: only the remainder is paid out.
     */
    public function refund(Order $order): RedirectResponse
    {
        $refunded = 0.0;

        try {
            DB::transaction(function () use ($order, &$refunded): void {
                $lockedOrder = Order::lockForUpdate()->findOrFail($order->id);

                $remaining = $lockedOrder->remainingRefundable();

                if ($remaining <= 0) {
                    throw new \RuntimeException('Order has already been fully refunded.');
                }

                $oldStatus = (string) $lockedOrder->status;
                $lockedOrder->update(['status' => 'refunded']);

                $user = User::lockForUpdate()->findOrFail($lockedOrder->user_id);

                $user->creditBalance($remaining, 'refund', "Admin refund for order #{$lockedOrder->id}", 'refund', $lockedOrder);
                $refunded = $remaining;

                // If this order paid a referral commission, reverse it —
                // the referrer keeps no cut of refunded money.
                app(\App\Services\ReferralService::class)->clawbackOrderCommission($lockedOrder);

                AuditLog::log(
                    'order.refunded',
                    Auth::id(),
                    Order::class,
                    $lockedOrder->id,
                    ['status' => $oldStatus],
                    ['status' => 'refunded', 'refund_amount' => $remaining],
                );

                NotificationService::notify(
                    $user->id,
                    'order_refunded',
                    'Order #'.$lockedOrder->id.' Refunded',
                    "Your order has been refunded. \${$remaining} has been credited to your account.",
                    ['order_id' => $lockedOrder->id, 'amount' => $remaining]
                );
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $user = $order->user;

        return back()->with('success', "Order #{$order->id} refunded. \${$refunded} credited to {$user->name}.");
    }

    /**
     * Place a manual order on a user's behalf. By default the user's wallet
     * is charged like any normal order (with a balance check + transaction);
     * unticking charge_user creates an explicit comp order, which is never
     * refundable because nothing was ever charged.
     */
    public function store(Request $request, \App\Services\Upstream\OrderDispatchService $dispatchService): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'service_id' => ['required', 'exists:services,id'],
            'link' => ['required', 'string', 'max:500'],
            'quantity' => ['required', 'integer', 'min:1'],
            'charge' => ['required', 'numeric', 'min:0'],
            'charge_user' => ['nullable', 'boolean'],
        ]);

        $chargeUser = $request->boolean('charge_user', true);
        $service = \App\Models\Service::findOrFail($data['service_id']);

        try {
            $order = DB::transaction(function () use ($data, $chargeUser, $service): Order {
                $user = User::lockForUpdate()->findOrFail($data['user_id']);
                $charge = round((float) $data['charge'], 4);

                if ($chargeUser && (float) $user->balance < $charge) {
                    throw new \RuntimeException(
                        "Insufficient balance: {$user->name} has \$".number_format((float) $user->balance, 2)
                        .' but the order costs $'.number_format($charge, 2)
                        .'. Top up their wallet first or place it as a comp order.'
                    );
                }

                $order = Order::create([
                    'user_id' => $user->id,
                    'service_id' => $service->id,
                    'link' => trim($data['link']),
                    'quantity' => (int) $data['quantity'],
                    'charge' => $charge,
                    'rate_at_order' => $service->rate,
                    'status' => 'pending',
                    'notes' => $chargeUser ? 'Placed by admin' : 'Placed by admin (comp — no charge)',
                ]);

                if ($chargeUser && $charge > 0) {
                    $user->deductBalance($charge, $order, "Manual order #{$order->id} placed by admin — {$service->name}");
                }

                return $order;
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::dispatchLog(
            'order.created_by_admin',
            Auth::id(),
            Order::class,
            $order->id,
            null,
            $data + ['charged_user' => $chargeUser]
        );

        NotificationService::notify(
            (int) $data['user_id'],
            'order_placed',
            'Manual Order Placed',
            "An admin has placed an order (#{$order->id}) on your behalf."
        );

        // Push upstream like any normal order; failed pushes get queued
        // retries with backoff (skipped on the sync driver, which can't defer).
        $dispatch = $dispatchService->dispatch($order);
        if (! $dispatch['ok'] && config('queue.default') !== 'sync') {
            \App\Jobs\DispatchOrderUpstream::dispatch($order->id)->delay(now()->addSeconds(15));
        }

        return back()->with('success', "Order #{$order->id} created".($chargeUser ? ' and charged to the user.' : ' as a comp (no charge).'));
    }
}
