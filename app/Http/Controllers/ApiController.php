<?php

// app/Http/Controllers/ApiController.php
// REST API for resellers — authenticate via: Authorization: Bearer YOUR_API_KEY

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Upstream\OrderDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiController extends Controller
{
    // ─── Auth helper ──────────────────────────────────────────────────────────

    private function resolveUser(Request $request): ?User
    {
        $key = $request->bearerToken();
        if (! $key) {
            return null;
        }

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
        if (! $user) {
            return $this->unauthorized();
        }

        $services = Service::active()
            ->orderBy('category')
            ->orderBy('display_order')
            ->get()
            ->map(fn ($s) => [
                'service' => $s->id,
                'name' => $s->name,
                'name_sn' => $s->name_sn,
                'category' => $s->category,
                'type' => $s->type,
                'rate' => (float) $s->rate,
                'min' => $s->min_qty,
                'max' => $s->max_qty,
                'dripfeed' => $s->is_dripfeed,
                'refill' => $s->is_refill,
            ]);

        return response()->json($services);
    }

    // ─── POST /api/v1/order ───────────────────────────────────────────────────

    public function placeOrder(Request $request, OrderService $orderService, OrderDispatchService $dispatchService): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (! $user) {
            return $this->unauthorized();
        }

        $data = $request->validate([
            'service' => ['required', 'exists:services,id'],
            'link' => ['required', 'url'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $service = Service::active()->findOrFail($data['service']);

        $result = $orderService->placeOrder(
            $user,
            $service,
            $data['link'],
            (int) $data['quantity'],
            $dispatchService,
            'API Order'
        );

        if (! $result['ok']) {
            $payload = ['error' => $result['error']];
            if (isset($result['balance'])) {
                $payload['balance'] = $result['balance'];
            }
            if (isset($result['required'])) {
                $payload['required'] = $result['required'];
            }

            return response()->json($payload, $result['code'] ?? 422);
        }

        $order = $result['order'];
        $dispatch = $result['dispatch'];

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
        if (! $user) {
            return $this->unauthorized();
        }

        $orderId = $request->input('order');
        if (! $orderId) {
            return response()->json(['error' => 'Missing order ID.'], 422);
        }

        $order = Order::forUser($user->id)->find($orderId);
        if (! $order) {
            return response()->json(['error' => 'Order not found.'], 404);
        }

        return response()->json([
            'order' => $order->id,
            'charge' => (float) $order->charge,
            'start_count' => $order->start_count,
            'status' => $order->status,
            'remains' => $order->remains,
            'currency' => 'USD',
        ]);
    }

    // ─── GET /api/v1/balance ──────────────────────────────────────────────────

    public function balance(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (! $user) {
            return $this->unauthorized();
        }

        return response()->json([
            'balance' => (float) $user->balance,
            'currency' => 'USD',
        ]);
    }

    // ─── POST /api/v1/refill ──────────────────────────────────────────────────

    public function refill(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (! $user) {
            return $this->unauthorized();
        }

        $data = $request->validate(['order' => ['required', 'integer']]);
        $order = Order::forUser($user->id)->find($data['order']);

        if (! $order) {
            return response()->json(['error' => 'Order not found.'], 404);
        }

        if (! $order->service->is_refill) {
            return response()->json(['error' => 'This service does not support refills.'], 422);
        }

        // Upstream refill isn't implemented yet. Say so instead of returning a
        // fake success that makes resellers believe a refill was requested.
        return response()->json([
            'error' => 'Refill requests are not yet supported. Please open a support ticket and our team will process the refill manually.',
        ], 501);
    }

    // ─── POST /api/v1/cancel ──────────────────────────────────────────────────

    public function cancel(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (! $user) {
            return $this->unauthorized();
        }

        $data = $request->validate(['order' => ['required', 'integer']]);
        $order = Order::forUser($user->id)->find($data['order']);

        if (! $order || ! $order->canCancel()) {
            return response()->json(['error' => 'Order cannot be cancelled.'], 422);
        }

        DB::transaction(function () use ($order, $user): void {
            $lockedOrder = Order::lockForUpdate()->findOrFail($order->id);

            if (! $lockedOrder->canCancel()) {
                throw new \RuntimeException('Order cannot be cancelled.');
            }

            $lockedOrder->update(['status' => 'cancelled']);

            $lockedUser = User::lockForUpdate()->findOrFail($user->id);
            $refundable = $lockedOrder->remainingRefundable();
            if ($refundable > 0) {
                $lockedUser->creditBalance($refundable, 'refund', "API cancel order #{$lockedOrder->id}", 'refund', $lockedOrder);
            }
        });

        return response()->json(['cancel' => $order->id]);
    }
}
