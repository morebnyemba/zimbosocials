<?php

namespace App\WhatsApp\Order;

use App\Models\Order;
use App\Models\User;
use App\Services\Upstream\OrderStatusSyncService;
use App\Services\Upstream\UpstreamProviderClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Refreshes a customer's live orders from the upstream provider BEFORE the
 * assistant answers a "is it done yet?" question.
 *
 * The scheduled sync runs every five minutes, so without this the assistant
 * answers from whatever that last wrote — and "it's still processing" when it
 * finished four minutes ago is the kind of answer that makes people ask again,
 * and then doubt us. Checking on demand means the reply is true at the moment
 * it is sent.
 *
 * Deliberately bounded: the webhook is synchronous, so this only fires when the
 * customer actually asked about an order, only touches orders still in flight,
 * caps how many it checks, and never lets an upstream problem break the reply.
 */
class LiveOrderStatus
{
    /** Statuses still worth asking the provider about. */
    private const LIVE = ['pending', 'processing', 'in_progress'];

    /** Don't re-poll the same order more often than this. */
    private const MIN_SECONDS_BETWEEN_CHECKS = 90;

    /** An upstream call per order — keep the worst case small. */
    private const MAX_ORDERS = 3;

    public function __construct(
        private readonly OrderStatusSyncService $sync,
        private readonly UpstreamProviderClient $client,
    ) {}

    /** Does this message read like someone chasing an order? */
    public function isStatusQuestion(string $text): bool
    {
        return (bool) preg_match(
            '/\b(status|track|tracking|progress|update|done|finished|complete|delivered|deliver|received|'
            .'started|starting|yet|still|when|how long|zvaita|zvakaita|matii|yakaita|paita|hasn.?t|not.*(yet|come|arrived))\b/i',
            $text
        );
    }

    /**
     * Refresh this user's in-flight orders. Returns how many actually changed,
     * so the caller can tell whether the context it builds is newer than what
     * the customer was last told.
     */
    public function refresh(User $user): int
    {
        $orders = Order::where('user_id', $user->getKey())
            ->whereIn('status', self::LIVE)
            ->whereNotNull('external_order_id')
            ->where('pushed_to_upstream', true)
            ->latest()
            ->limit(self::MAX_ORDERS)
            ->get();

        $changed = 0;

        foreach ($orders as $order) {
            // Several messages in a row about the same order shouldn't mean
            // several upstream calls.
            if (! Cache::add("order:live-check:{$order->id}", true, self::MIN_SECONDS_BETWEEN_CHECKS)) {
                continue;
            }

            try {
                $result = $this->sync->syncSingleOrder($order, $this->client);
                if (! empty($result['changed'])) {
                    $changed++;
                }
            } catch (\Throwable $e) {
                // A provider being slow or down must never cost the customer
                // their reply — we simply answer from what we already know.
                Log::warning('Live order check failed', [
                    'order_id' => $order->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $changed;
    }
}
