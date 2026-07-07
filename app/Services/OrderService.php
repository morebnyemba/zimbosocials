<?php

namespace App\Services;

use App\Exceptions\DuplicateOrderException;
use App\Exceptions\InsufficientBalanceException;
use App\Jobs\DispatchOrderUpstream;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Services\Upstream\OrderDispatchService;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /** Order statuses considered "still in progress" for the duplicate-link guard. */
    private const IN_PROGRESS_STATUSES = ['pending', 'processing', 'in_progress'];

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
        $link = trim($link);

        // --- Validate quantity range ---
        if ($quantity < $service->min_qty || $quantity > $service->max_qty) {
            return [
                'ok' => false,
                'error' => "Quantity must be between {$service->min_qty} and {$service->max_qty}.",
                'code' => 422,
            ];
        }

        $charge = $service->calculateCharge($quantity);

        // --- Atomically create order and deduct balance ---
        // Balance check is inside the transaction with lockForUpdate to prevent
        // two concurrent orders from both passing a stale balance check. The
        // duplicate-link check rides the same lock, so two rapid clicks by the
        // same user can't both slip past it.
        try {
            $order = DB::transaction(function () use ($user, $service, $link, $quantity, $charge, $notePrefix): Order {
                $lockedUser = User::lockForUpdate()->findOrFail($user->id);

                $hasOrderInProgressForLink = Order::where('user_id', $lockedUser->id)
                    ->where('link', $link)
                    ->whereIn('status', self::IN_PROGRESS_STATUSES)
                    ->exists();

                if ($hasOrderInProgressForLink) {
                    throw new DuplicateOrderException(
                        'You already have an order in progress for this link. Please wait for it to complete before ordering again.'
                    );
                }

                if ((float) $lockedUser->balance < $charge) {
                    throw new InsufficientBalanceException(
                        'Insufficient balance.',
                        (float) $lockedUser->balance,
                        $charge
                    );
                }

                $order = Order::create([
                    'user_id' => $lockedUser->id,
                    'service_id' => $service->id,
                    'link' => $link,
                    'quantity' => $quantity,
                    'charge' => $charge,
                    'rate_at_order' => $service->rate,
                    'status' => 'pending',
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
        } catch (DuplicateOrderException $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'code' => 409,
            ];
        } catch (InsufficientBalanceException $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'balance' => $e->balance,
                'required' => $e->required,
                'code' => 402,
            ];
        }

        // --- Dispatch upstream (outside transaction; failure is recoverable) ---
        $dispatch = $dispatchService->dispatch($order);

        // A failed synchronous push gets queued retries with backoff; the job
        // auto-cancels and refunds the order if every attempt fails, so the
        // customer's money never stays stuck on an undeliverable order.
        // Skipped on the sync driver: it can't defer or retry, so the job's
        // retry-signalling throw would just crash this request.
        if (! $dispatch['ok'] && config('queue.default') !== 'sync') {
            DispatchOrderUpstream::dispatch($order->id)->delay(now()->addSeconds(15));
        }

        return [
            'ok' => true,
            'order' => $order,
            'dispatch' => $dispatch,
        ];
    }
}
