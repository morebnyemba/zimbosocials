<?php
// app/Http/Controllers/AdminOrderController.php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\NotificationService;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            $query->where('created_at', '<=', $to . ' 23:59:59');
        }

        $orders = $query->latest()->paginate(25)->withQueryString();

        $status_counts = [
            'all'         => Order::count(),
            'pending'     => Order::where('status', 'pending')->count(),
            'processing'  => Order::where('status', 'processing')->count(),
            'in_progress' => Order::where('status', 'in_progress')->count(),
            'completed'   => Order::where('status', 'completed')->count(),
            'partial'     => Order::where('status', 'partial')->count(),
            'cancelled'   => Order::where('status', 'cancelled')->count(),
            'refunded'    => Order::where('status', 'refunded')->count(),
        ];

        return Inertia::render('Admin/Orders/Index', [
            'orders'        => $orders,
            'filters'       => $request->only(['search', 'status', 'service_id', 'user_id', 'from', 'to']),
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
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);

        $oldStatus = $order->status;
        $updates = ['status' => $data['status']];

        if ($data['status'] === 'completed' && !$order->completed_at) {
            $updates['completed_at'] = now();
        }
        if (in_array($data['status'], ['processing', 'in_progress']) && !$order->started_at) {
            $updates['started_at'] = now();
        }

        $order->update($updates);

        AuditLog::log(
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
            'Order #' . $order->id . ' Updated',
            "Your order status changed from {$oldStatus} to {$data['status']}.",
            ['order_id' => $order->id, 'old_status' => $oldStatus, 'new_status' => $data['status']]
        );

        return back()->with('success', "Order #{$order->id} status changed to {$data['status']}.");
    }

    public function refund(Order $order): RedirectResponse
    {
        if ($order->status === 'refunded') {
            return back()->with('error', 'Order is already refunded.');
        }

        $user = $order->user;
        $charge = (float) $order->charge;

        $order->update(['status' => 'refunded']);

        $user->creditBalance($charge, 'refund', "Admin refund for order #{$order->id}", 'refund');

        AuditLog::log(
            'order.refunded',
            Auth::id(),
            Order::class,
            $order->id,
            ['status' => $order->getOriginal('status')],
            ['status' => 'refunded', 'refund_amount' => $charge],
        );

        NotificationService::notify(
            $user->id,
            'order_refunded',
            'Order #' . $order->id . ' Refunded',
            "Your order has been refunded. \${$charge} has been credited to your account.",
            ['order_id' => $order->id, 'amount' => $charge]
        );

        return back()->with('success', "Order #{$order->id} refunded. \${$charge} credited to {$user->name}.");
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id'    => ['required', 'exists:users,id'],
            'service_id' => ['required', 'exists:services,id'],
            'link'       => ['required', 'string', 'max:500'],
            'quantity'   => ['required', 'integer', 'min:1'],
            'charge'     => ['required', 'numeric', 'min:0'],
        ]);

        $order = Order::create([
            'user_id'    => $data['user_id'],
            'service_id' => $data['service_id'],
            'link'       => $data['link'],
            'quantity'   => $data['quantity'],
            'charge'     => $data['charge'],
            'status'     => 'pending',
        ]);

        AuditLog::log(
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
