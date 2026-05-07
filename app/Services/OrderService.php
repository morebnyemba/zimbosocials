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

        // --- Atomically create order and deduct balance ---
        // Balance check is inside the transaction with lockForUpdate to prevent
        // two concurrent orders from both passing a stale balance check.
        try {
            $order = DB::transaction(function () use ($user, $service, $link, $quantity, $charge, $notePrefix): Order {
                $lockedUser = User::lockForUpdate()->findOrFail($user->id);

                if ((float) $lockedUser->balance < $charge) {
                    throw new \App\Exceptions\InsufficientBalanceException(
                        'Insufficient balance.',
                        (float) $lockedUser->balance,
                        $charge
                    );
                }

                $order = Order::create([
                    'user_id'       => $lockedUser->id,
                    'service_id'    => $service->id,
                    'link'          => $link,
                    'quantity'      => $quantity,
                    'charge'        => $charge,
                    'rate_at_order' => $service->rate,
                    'status'        => 'pending',
                ]);

                $deducted = $lockedUser->deductBalance(
                    $charge,
                    $order,
                    "{$notePrefix} #{$order->id} — {$service->name}"
                );

                if (! $deducted) {
                    throw new \RuntimeException('Balance deduction failed inside transaction.');
                }

                return $order;
            });
        } catch (\App\Exceptions\InsufficientBalanceException $e) {
            return [
                'ok'       => false,
                'error'    => $e->getMessage(),
                'balance'  => $e->balance,
                'required' => $e->required,
                'code'     => 402,
            ];
        }

        // --- Dispatch upstream (outside transaction; failure is recoverable) ---
        $dispatch = $dispatchService->dispatch($order);

        return [
            'ok'       => true,
            'order'    => $order,
            'dispatch' => $dispatch,
        ];
    }
}
