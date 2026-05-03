<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Services\Upstream\OrderDispatchService;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * Atomically validate, create, and charge an order.
     *
     * Returns an array:
     *   ['ok' => true, 'order' => Order, 'dispatch' => array]
     *   ['ok' => false, 'error' => string, 'code' => int]
     */
    public function placeOrder(
        User $user,
        Service $service,
        string $link,
        int $quantity,
        OrderDispatchService $dispatchService,
        string $notePrefix = 'Order'
    ): array {
        // --- Validate quantity range ---
        if ($quantity < $service->min_qty || $quantity > $service->max_qty) {
            return [
                'ok'    => false,
                'error' => "Quantity must be between {$service->min_qty} and {$service->max_qty}.",
                'code'  => 422,
            ];
        }

        $charge = $service->calculateCharge($quantity);

        if ((float) $user->balance < $charge) {
            return [
                'ok'       => false,
                'error'    => 'Insufficient balance.',
                'balance'  => (float) $user->balance,
                'required' => $charge,
                'code'     => 402,
            ];
        }

        // --- Atomically create order and deduct balance ---
        $order = DB::transaction(function () use ($user, $service, $link, $quantity, $charge, $notePrefix): Order {
            $order = Order::create([
                'user_id'       => $user->id,
                'service_id'    => $service->id,
                'link'          => $link,
                'quantity'      => $quantity,
                'charge'        => $charge,
                'rate_at_order' => $service->rate,
                'status'        => 'pending',
            ]);

            $deducted = $user->deductBalance(
                $charge,
                $order,
                "{$notePrefix} #{$order->id} — {$service->name}"
            );

            if (! $deducted) {
                throw new \RuntimeException('Balance deduction failed inside transaction.');
            }

            return $order;
        });

        // --- Dispatch upstream (outside transaction; failure is recoverable) ---
        $dispatch = $dispatchService->dispatch($order);

        return [
            'ok'       => true,
            'order'    => $order,
            'dispatch' => $dispatch,
        ];
    }
}
