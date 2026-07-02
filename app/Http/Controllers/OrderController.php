<?php

// app/Http/Controllers/OrderController.php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\OrderService;
use App\Services\ReferralService;
use App\Services\Upstream\OrderDispatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /**
     * Show the new order form.
     */
    public function create(Request $request): Response
    {
        $services = Service::active()->orderBy('category')->orderBy('display_order')->get();
        $categories = $services->pluck('category')->unique()->values();
        $selected = $request->query('service_id')
            ? Service::find($request->query('service_id'))
            : null;

        return Inertia::render('Orders/Create', [
            'services' => $services,
            'categories' => $categories,
            'selected' => $selected,
        ]);
    }

    /**
     * Store a new order.
     */
    public function store(Request $request, OrderService $orderService, OrderDispatchService $dispatchService, ReferralService $referralService): RedirectResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'exists:services,id'],
            'link' => ['required', 'url', 'max:500'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $service = Service::findOrFail($data['service_id']);
        $user = Auth::user();

        // Hard guard: users with empty balance cannot place orders.
        if ((float) $user->balance <= 0) {
            return back()->withErrors(['balance' => __('messages.insufficient_balance')])->withInput();
        }

        $result = $orderService->placeOrder(
            $user,
            $service,
            $data['link'],
            (int) $data['quantity'],
            $dispatchService,
            'Order'
        );

        if (! $result['ok']) {
            $field = match ($result['code'] ?? 0) {
                402 => 'balance',
                409 => 'link',
                default => 'quantity',
            };

            return back()->withErrors([$field => $result['error']])->withInput();
        }

        $order = $result['order'];
        $referralService->rewardReferrerOnReferredOrder($order);

        NotificationService::notifyAdmins(
            'admin_new_order',
            'New Order Placed',
            "Order #{$order->id} placed by {$user->name} — {$service->name} (\${$order->charge}).",
            ['order_id' => $order->id, 'user_name' => $user->name, 'service_name' => $service->name, 'amount' => "\${$order->charge}"]
        );

        if (! $result['dispatch']['ok']) {
            return redirect()->route('orders.index')
                ->with('success', __('messages.placed_success', ['id' => $order->id]))
                ->with('info', __('messages.dispatch_retry'));
        }

        return redirect()->route('orders.index')
            ->with('success', __('messages.placed_success', ['id' => $order->id]));
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
            'orders' => $orders,
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
            return back()->withErrors(['order' => __('messages.cannot_cancel')]);
        }

        DB::transaction(function () use ($order): void {
            $lockedOrder = Order::lockForUpdate()->findOrFail($order->id);

            if (! $lockedOrder->canCancel()) {
                throw new \RuntimeException(__('messages.cannot_cancel'));
            }

            $lockedOrder->update(['status' => 'cancelled']);

            $user = User::lockForUpdate()->findOrFail(Auth::id());
            $user->creditBalance(
                $lockedOrder->charge,
                'refund',
                "Cancelled order #{$lockedOrder->id}",
                'refund'
            );
        });

        return back()->with('success', __('messages.cancelled_success'));
    }
}
