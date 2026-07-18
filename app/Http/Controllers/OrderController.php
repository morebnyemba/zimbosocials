<?php

// app/Http/Controllers/OrderController.php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\OrderService;
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
    public function store(Request $request, OrderService $orderService, OrderDispatchService $dispatchService): RedirectResponse
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
            $field = $result['field'] ?? match ($result['code'] ?? 0) {
                402 => 'balance',
                409 => 'link',
                default => 'quantity',
            };

            return back()->withErrors([$field => $result['error']])->withInput();
        }

        $order = $result['order'];

        // Best-effort — the order is already placed; a notification hiccup must
        // never turn a successful order into a 500 for the customer.
        try {
            NotificationService::notifyAdmins(
                'admin_new_order',
                'New Order Placed',
                "Order #{$order->id} placed by {$user->name} — {$service->name} (\${$order->charge}).",
                ['order_id' => $order->id, 'user_name' => $user->name, 'service_name' => $service->name, 'amount' => "\${$order->charge}"]
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('admin_new_order notify failed', ['order_id' => $order->id, 'message' => $e->getMessage()]);
        }

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
        $order->load('service');

        // Full money trail for this order: the charge plus any refunds.
        $transactions = \App\Models\Transaction::where('order_id', $order->id)
            ->orderBy('created_at')
            ->get(['id', 'type', 'amount', 'status', 'notes', 'created_at']);

        return Inertia::render('Orders/Show', [
            'order' => $order,
            'transactions' => $transactions,
            'can_cancel' => $order->canCancel(),
            // Status can be refreshed on demand while the order is live upstream.
            'can_sync' => $order->isActive()
                && $order->pushed_to_upstream
                && $order->external_order_id !== null
                && $order->upstream_provider_id !== null,
        ]);
    }

    /**
     * Refresh this order's status from the upstream provider on demand.
     * Lets users see progress even between scheduled sync runs (and keeps
     * statuses moving if the host cron for the scheduler is down). Gated per
     * order so repeated clicks can't hammer the provider.
     */
    public function syncStatus(
        Order $order,
        \App\Services\Upstream\OrderStatusSyncService $syncService,
        \App\Services\Upstream\UpstreamProviderClient $client
    ): RedirectResponse {
        $this->authorize('view', $order);

        if (! $order->isActive() || ! $order->pushed_to_upstream) {
            return back();
        }

        // At most one upstream status call per order per 60s, shared across
        // everyone viewing it (Cache::add is atomic — first caller wins).
        if (! \Illuminate\Support\Facades\Cache::add("order:user_sync:{$order->id}", true, 60)) {
            return back()->with('info', __('Status was checked moments ago — try again shortly.'));
        }

        $result = $syncService->syncSingleOrder($order, $client);

        return back()->with(
            $result['changed'] ? 'success' : 'info',
            $result['changed'] ? __('Order status updated.') : __('Status checked — no change yet.')
        );
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
            $refundable = $lockedOrder->remainingRefundable();
            if ($refundable > 0) {
                $user->creditBalance(
                    $refundable,
                    'refund',
                    "Cancelled order #{$lockedOrder->id}",
                    'refund',
                    $lockedOrder
                );
            }
        });

        return back()->with('success', __('messages.cancelled_success'));
    }
}
