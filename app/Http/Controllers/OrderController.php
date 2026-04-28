<?php
// app/Http/Controllers/OrderController.php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Service;
use App\Services\Upstream\OrderDispatchService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /**
     * Show the new order form.
     */
    public function create(Request $request): Response
    {
        $services   = Service::active()->orderBy('category')->orderBy('display_order')->get();
        $categories = $services->pluck('category')->unique()->values();
        $selected   = $request->query('service_id')
            ? Service::find($request->query('service_id'))
            : null;

        return Inertia::render('Orders/Create', [
            'services'   => $services,
            'categories' => $categories,
            'selected'   => $selected,
        ]);
    }

    /**
     * Store a new order.
     */
    public function store(Request $request, OrderDispatchService $dispatchService): RedirectResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'exists:services,id'],
            'link'       => ['required', 'url', 'max:500'],
            'quantity'   => ['required', 'integer', 'min:1'],
        ]);

        $service = Service::findOrFail($data['service_id']);
        $user    = Auth::user();

        // Validate quantity range
        if ($data['quantity'] < $service->min_qty || $data['quantity'] > $service->max_qty) {
            return back()->withErrors([
                'quantity' => __('orders.qty_range', [
                    'min' => number_format($service->min_qty),
                    'max' => number_format($service->max_qty),
                ]),
            ])->withInput();
        }

        $charge = $service->calculateCharge($data['quantity']);

        // Check balance
        if ($user->balance < $charge) {
            return back()->withErrors([
                'balance' => __('orders.insufficient_balance'),
            ])->withInput();
        }

        // Create order first (pending)
        $order = Order::create([
            'user_id'       => $user->id,
            'service_id'    => $service->id,
            'link'          => $data['link'],
            'quantity'      => $data['quantity'],
            'charge'        => $charge,
            'rate_at_order' => $service->rate,
            'status'        => 'pending',
        ]);

        // Deduct balance and record transaction
        $deducted = $user->deductBalance(
            $charge,
            $order,
            "Order #{$order->id} — {$service->name}"
        );

        if (! $deducted) {
            $order->delete();
            return back()->withErrors(['balance' => __('orders.insufficient_balance')])->withInput();
        }

        $dispatchResult = $dispatchService->dispatch($order);

        if (! $dispatchResult['ok']) {
            return redirect()->route('orders.index')
                ->with('success', __('orders.placed_success', ['id' => $order->id]))
                ->with('info', app()->getLocale() === 'sn'
                    ? 'Odha yakagadzirwa asi kutumira kumupi kwatadza. Tiri kuyedza zvakare munguva pfupi.'
                    : 'Order was created but upstream push failed. The system will retry soon.');
        }

        return redirect()->route('orders.index')
            ->with('success', __('orders.placed_success', ['id' => $order->id]));
    }

    /**
     * List all orders for the authenticated user.
     */
    public function index(Request $request): Response
    {
        $query = Order::with('service')
            ->forUser(Auth::id())
            ->latest();

        if ($status = $request->query('status')) {
            $query->byStatus($status);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('link', 'like', "%{$search}%")
                  ->orWhere('id', $search);
            });
        }

        $orders = $query->paginate(20)->withQueryString();

        return Inertia::render('Orders/Index', [
            'orders'  => $orders,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    /**
     * Show a single order.
     */
    public function show(Order $order): Response
    {
        $this->authorize('view', $order);
        $order->load('service', 'transaction');

        return Inertia::render('Orders/Show', ['order' => $order]);
    }

    /**
     * Cancel a pending order and refund.
     */
    public function cancel(Order $order): RedirectResponse
    {
        $this->authorize('update', $order);

        if (! $order->canCancel()) {
            return back()->withErrors(['order' => __('orders.cannot_cancel')]);
        }

        $order->update(['status' => 'cancelled']);

        // Refund
        Auth::user()->creditBalance(
            $order->charge,
            'refund',
            '',
            'refund'
        );

        return back()->with('success', __('orders.cancelled_success'));
    }
}
