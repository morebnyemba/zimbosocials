<?php
// app/Http/Controllers/ApiController.php
// REST API for resellers — authenticate via ?key=YOUR_API_KEY

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Services\Upstream\OrderDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    // ─── Auth helper ──────────────────────────────────────────────────────────

    private function resolveUser(Request $request): ?User
    {
        $key = $request->input('key') ?? $request->bearerToken();
        if (! $key) return null;
        return User::where('api_key', $key)->where('is_active', true)->first();
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json([
            'error' => 'Invalid or missing API key.',
        ], 401);
    }

    // ─── GET /api/v1/services ─────────────────────────────────────────────────

    public function services(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (! $user) return $this->unauthorized();

        $services = Service::active()
            ->orderBy('category')
            ->orderBy('display_order')
            ->get()
            ->map(fn($s) => [
                'service'  => $s->id,
                'name'     => $s->name,
                'name_sn'  => $s->name_sn,
                'category' => $s->category,
                'type'     => $s->type,
                'rate'     => (float) $s->rate,
                'min'      => $s->min_qty,
                'max'      => $s->max_qty,
                'dripfeed' => $s->is_dripfeed,
                'refill'   => $s->is_refill,
            ]);

        return response()->json($services);
    }

    // ─── POST /api/v1/order ───────────────────────────────────────────────────

    public function placeOrder(Request $request, OrderDispatchService $dispatchService): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate([
            'service'  => ['required', 'exists:services,id'],
            'link'     => ['required', 'url'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $service = Service::active()->findOrFail($data['service']);

        if ($data['quantity'] < $service->min_qty || $data['quantity'] > $service->max_qty) {
            return response()->json([
                'error' => "Quantity must be between {$service->min_qty} and {$service->max_qty}.",
            ], 422);
        }

        $charge = $service->calculateCharge($data['quantity']);

        if ($user->balance < $charge) {
            return response()->json([
                'error' => 'Insufficient balance.',
                'balance' => (float) $user->balance,
                'required' => $charge,
            ], 402);
        }

        $order = Order::create([
            'user_id'       => $user->id,
            'service_id'    => $service->id,
            'link'          => $data['link'],
            'quantity'      => $data['quantity'],
            'charge'        => $charge,
            'rate_at_order' => $service->rate,
            'status'        => 'pending',
        ]);

        $user->deductBalance($charge, $order, "API Order #{$order->id}");

        $dispatch = $dispatchService->dispatch($order);

        return response()->json([
            'order' => $order->id,
            'external_order_id' => $order->external_order_id,
            'upstream_pushed' => (bool) $dispatch['ok'],
            'upstream_message' => $dispatch['message'],
        ]);
    }

    // ─── GET /api/v1/status ───────────────────────────────────────────────────

    public function orderStatus(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (! $user) return $this->unauthorized();

        $orderId = $request->input('order');
        if (! $orderId) {
            return response()->json(['error' => 'Missing order ID.'], 422);
        }

        $order = Order::forUser($user->id)->find($orderId);
        if (! $order) {
            return response()->json(['error' => 'Order not found.'], 404);
        }

        return response()->json([
            'order'       => $order->id,
            'charge'      => (float) $order->charge,
            'start_count' => $order->start_count,
            'status'      => $order->status,
            'remains'     => $order->remains,
            'currency'    => 'USD',
        ]);
    }

    // ─── GET /api/v1/balance ──────────────────────────────────────────────────

    public function balance(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (! $user) return $this->unauthorized();

        return response()->json([
            'balance'  => (float) $user->balance,
            'currency' => 'USD',
        ]);
    }

    // ─── POST /api/v1/refill ──────────────────────────────────────────────────

    public function refill(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate(['order' => ['required', 'integer']]);
        $order = Order::forUser($user->id)->find($data['order']);

        if (! $order) {
            return response()->json(['error' => 'Order not found.'], 404);
        }

        if (! $order->service->is_refill) {
            return response()->json(['error' => 'This service does not support refills.'], 422);
        }

        // TODO: trigger refill with upstream provider
        return response()->json(['refill' => $order->id]);
    }

    // ─── POST /api/v1/cancel ──────────────────────────────────────────────────

    public function cancel(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate(['order' => ['required', 'integer']]);
        $order = Order::forUser($user->id)->find($data['order']);

        if (! $order || ! $order->canCancel()) {
            return response()->json(['error' => 'Order cannot be cancelled.'], 422);
        }

        $order->update(['status' => 'cancelled']);
        $user->creditBalance($order->charge, 'refund', '', 'refund');

        return response()->json(['cancel' => $order->id]);
    }
}
