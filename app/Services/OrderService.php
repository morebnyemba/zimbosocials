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
use Illuminate\Support\Facades\Log;

class OrderService
{
    /** Order statuses considered "still in progress" for the duplicate-link guard. */
    private const IN_PROGRESS_STATUSES = ['pending', 'processing', 'in_progress'];

    /**
     * Platform keyword → allowed link hosts. Catches the classic mistake of
     * pasting a TikTok link into an Instagram service (which then fails or
     * misdelivers upstream after the money is already taken). Categories that
     * match no keyword skip validation entirely.
     */
    private const PLATFORM_HOSTS = [
        'instagram' => ['instagram.com', 'instagr.am'],
        'tiktok' => ['tiktok.com'],
        'youtube' => ['youtube.com', 'youtu.be'],
        'facebook' => ['facebook.com', 'fb.com', 'fb.watch'],
        'twitter' => ['x.com', 'twitter.com'],
        'telegram' => ['t.me', 'telegram.me'],
        'whatsapp' => ['whatsapp.com', 'wa.me'],
        'spotify' => ['spotify.com'],
        'linkedin' => ['linkedin.com'],
    ];

    /**
     * Null when the link is acceptable for the service's platform; otherwise
     * a user-facing error message.
     */
    private function validateLinkPlatform(Service $service, string $link): ?string
    {
        $category = strtolower((string) $service->category);
        $host = strtolower((string) parse_url($link, PHP_URL_HOST));

        if ($host === '') {
            return null; // the url validation rule already handles malformed links
        }

        foreach (self::PLATFORM_HOSTS as $keyword => $hosts) {
            // 'x' as a keyword would false-positive; twitter covers x.com below.
            if (! str_contains($category, $keyword)) {
                continue;
            }

            foreach ($hosts as $allowed) {
                if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                    return null;
                }
            }

            return "This is a {$service->category} service — the link must be a {$keyword} link (got {$host}).";
        }

        return null; // unknown platform category: don't block
    }

    /**
     * What the link must point at, judged from the service wording:
     * 'profile' (followers/subscribers grow an account) or 'post' (likes/views/
     * comments act on one post/video). Null = ambiguous, never police it.
     */
    private function linkTarget(Service $service): ?string
    {
        $text = strtolower($service->name.' '.$service->type);

        // Product types whose links legitimately go either way.
        if (preg_match('/story|stories|live|page|group|website|traffic|play|stream|listen/', $text)) {
            return null;
        }
        if (preg_match('/follower|subscriber|member|friend/', $text)) {
            return 'profile';
        }
        if (preg_match('/like|view|comment|share|retweet|repost|save|impression|reach/', $text)) {
            return 'post';
        }

        return null;
    }

    /** Classify a link as 'post' | 'profile' | null (unrecognized) per platform. */
    private function linkShape(string $host, string $link): ?string
    {
        $path = strtolower((string) (parse_url($link, PHP_URL_PATH) ?: '/'));
        $query = strtolower((string) parse_url($link, PHP_URL_QUERY));
        $is = fn (string $domain): bool => $host === $domain || str_ends_with($host, '.'.$domain);

        if ($is('instagram.com') || $is('instagr.am')) {
            if (preg_match('#^/(p|reel|reels|tv)/#', $path)) {
                return 'post';
            }

            return preg_match('#^/[a-z0-9._]+/?$#', $path) ? 'profile' : null;
        }

        if ($is('tiktok.com')) {
            if (str_contains($path, '/video/') || str_contains($path, '/photo/')) {
                return 'post';
            }

            return preg_match('#^/@[^/]+/?$#', $path) ? 'profile' : null;
        }

        if ($is('youtu.be')) {
            return 'post';
        }
        if ($is('youtube.com')) {
            if ((str_starts_with($path, '/watch') && str_contains($query, 'v=')) || str_starts_with($path, '/shorts/')) {
                return 'post';
            }

            return preg_match('#^/(@|channel/|c/|user/)#', $path) ? 'profile' : null;
        }

        if ($is('x.com') || $is('twitter.com')) {
            if (str_contains($path, '/status/')) {
                return 'post';
            }

            return preg_match('#^/[a-z0-9_]+/?$#', $path) ? 'profile' : null;
        }

        if ($is('fb.watch')) {
            return 'post';
        }
        if ($is('facebook.com') || $is('fb.com')) {
            if (preg_match('#/(posts|videos|photos?|reel|share|watch)/#', $path) || str_contains($path, 'story.php')) {
                return 'post';
            }
            if (str_contains($path, '/groups/')) {
                return null;
            }

            return (preg_match('#^/[a-z0-9.\-]+/?$#', $path) || str_contains($path, 'profile.php')) ? 'profile' : null;
        }

        return null;
    }

    /**
     * Null when the link points at the right kind of target for the service;
     * otherwise a user-facing error. Catches "followers order with a post
     * link" (and the reverse) BEFORE money is taken and the upstream dispatch
     * fails or misdelivers. Only clear mismatches block — anything ambiguous
     * passes through.
     */
    private function validateLinkTarget(Service $service, string $link): ?string
    {
        $target = $this->linkTarget($service);
        if ($target === null) {
            return null;
        }

        $host = strtolower((string) parse_url($link, PHP_URL_HOST));
        if ($host === '') {
            return null;
        }

        $shape = $this->linkShape($host, $link);
        if ($shape === null || $shape === $target) {
            return null;
        }

        return $target === 'profile'
            ? "This service delivers to an account, so it needs your *profile* link (e.g. instagram.com/yourname) — the link you sent points to a single post."
            : "This service delivers to a specific post/video, so it needs the *post's* link — the link you sent points to a profile.";
    }

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
                'field' => 'quantity',
                'code' => 422,
            ];
        }

        // --- Validate the link matches the service's platform ---
        if ($linkError = $this->validateLinkPlatform($service, $link)) {
            return [
                'ok' => false,
                'error' => $linkError,
                'field' => 'link',
                'code' => 422,
            ];
        }

        // --- Validate the link points at the right kind of target
        //     (profile link for followers, post link for likes/views) ---
        if ($targetError = $this->validateLinkTarget($service, $link)) {
            return [
                'ok' => false,
                'error' => $targetError,
                'field' => 'link',
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
        // CRITICAL: the order + charge are already committed. A throw here (a
        // provider client blowing up, a notify failure, anything) must NOT 500
        // the request — that would show the customer an error for an order that
        // actually went through, and leave it looking "stuck right after
        // placing". Any failure just leaves the order PENDING; the queued retry
        // below and the every-5-min orders:recover-pending command finish it.
        try {
            $dispatch = $dispatchService->dispatch($order);
        } catch (\Throwable $e) {
            Log::error('Order dispatch threw after placement; leaving order pending for recovery', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
            $dispatch = ['ok' => false, 'message' => 'Dispatch deferred — will retry automatically.', 'raw' => null, 'external_order_id' => null];
        }

        // A failed synchronous push gets queued retries with backoff; the job
        // auto-cancels and refunds the order if every attempt fails, so the
        // customer's money never stays stuck on an undeliverable order.
        // Skipped on the sync driver (it can't defer or retry, so the job's
        // retry-signalling throw would just crash this request) and for
        // unknown outcomes (the provider may have the order — retrying could
        // buy the delivery twice; admins are alerted to verify manually).
        if (! $dispatch['ok'] && ! ($dispatch['unknown'] ?? false) && config('queue.default') !== 'sync') {
            try {
                DispatchOrderUpstream::dispatch($order->id)->delay(now()->addSeconds(15));
            } catch (\Throwable $e) {
                Log::error('Failed to queue order dispatch retry', ['order_id' => $order->id, 'message' => $e->getMessage()]);
            }
        }

        return [
            'ok' => true,
            'order' => $order,
            'dispatch' => $dispatch,
        ];
    }
}
