<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AI\ServiceListFormatter;
use App\Services\LeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ReferralController extends Controller
{
    public function __construct(
        private readonly LeaderboardService $leaderboardService,
    ) {}

    /**
     * Build a referral link for the given code. The landing page is configurable
     * via the `referral_landing_path` setting (admin → Settings) and defaults to
     * the home page. The CaptureReferral middleware records `?ref=` on any page.
     */
    private function referralLink(string $code): string
    {
        return \App\Support\ReferralLink::build($code);
    }

    public function index(): Response
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->getAttribute('referral_code')) {
            $user->update([
                'referral_code' => User::generateReferralCode(),
            ]);
            $user->refresh();
        }

        $referrals = User::query()
            ->where('referred_by', $user->getKey())
            ->select(['id', 'name', 'email', 'created_at', 'referred_bonus_awarded_at'])
            ->withCount([
                'transactions as completed_deposits' => fn ($q) => $q->where('type', 'deposit')->where('status', 'completed'),
                'orders as orders_count',
            ])
            ->latest()
            ->get()
            ->map(function (User $referral) {
                $expiresAt = app(\App\Services\ReferralService::class)->referralExpiresAt($referral);

                return [
                    'id' => $referral->getKey(),
                    'name' => $referral->getAttribute('name'),
                    'email' => $referral->getAttribute('email'),
                    'joined_at' => optional($referral->getAttribute('created_at'))->toISOString(),
                    'first_deposit_rewarded' => (bool) $referral->getAttribute('referred_bonus_awarded_at'),
                    'completed_deposits' => (int) $referral->completed_deposits,
                    'orders_count' => (int) $referral->orders_count,
                    'expires_at' => optional($expiresAt)->toDateString(),
                    'is_expired' => $expiresAt !== null && $expiresAt->isPast(),
                ];
            })
            ->values();

        $rewardTransactions = Transaction::query()
            ->where('user_id', $user->getKey())
            ->where('type', 'bonus')
            ->whereIn('method', ['referral', 'referral_order'])
            ->latest()
            ->get(['id', 'user_id', 'amount', 'method', 'reference', 'created_at', 'notes'])
            ->map(fn (Transaction $transaction) => [
                'id' => $transaction->getKey(),
                'amount' => (float) $transaction->getAttribute('amount'),
                'method' => $transaction->getAttribute('method'),
                'reference' => $transaction->getAttribute('reference'),
                'notes' => $transaction->getAttribute('notes'),
                'created_at' => optional($transaction->getAttribute('created_at'))->toISOString(),
            ])
            ->values();

        $summary = [
            'total_referrals' => $referrals->count(),
            'successful_first_deposits' => $referrals->where('completed_deposits', '>', 0)->count(),
            'total_rewards_earned' => round((float) $rewardTransactions->sum('amount'), 4),
            'order_commissions_earned' => round((float) $rewardTransactions->where('method', 'referral_order')->sum('amount'), 4),
        ];

        $myRank = $this->leaderboardService->getUserRank($user->id, 'referrals');

        $globalRecentRewards = Transaction::query()
            ->where('type', 'bonus')
            ->whereIn('method', ['referral', 'referral_order'])
            ->with('user:id,username')
            ->latest()
            ->limit(10)
            ->get(['id', 'user_id', 'amount', 'method', 'created_at'])
            ->map(fn (Transaction $t) => [
                'id' => $t->id,
                'user_name' => $t->user->username ?? 'Someone',
                'amount' => (float) $t->amount,
                'method' => $t->method,
                'time_ago' => $t->created_at->diffForHumans(),
            ])
            ->values();

        return Inertia::render('Referrals/Index', [
            'summary' => $summary,
            'referralCode' => $user->getAttribute('referral_code'),
            'referralLink' => $this->referralLink((string) $user->getAttribute('referral_code')),
            'referrals' => $referrals,
            'rewardHistory' => $rewardTransactions,
            'myRank' => $myRank,
            'globalRecentRewards' => $globalRecentRewards,
            'programRates' => ($svc = app(\App\Services\ReferralService::class))->programRates(),
            'commissionStatus' => [
                'active_days' => $svc->commissionActiveDays(),
                'is_active' => $svc->isReferrerCommissionActive($user->id),
                'active_until' => optional($svc->commissionActiveUntil($user->id))->toDateString(),
                'has_referrals' => $referrals->count() > 0,
            ],
            'serviceCategories' => Service::active()->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    /**
     * WhatsApp/Telegram/Twitter/Instagram/Facebook-ready message combining a
     * short service list with the user's own referral link, restyled by
     * ServiceListFormatter (Gemini). The mechanical text — including the
     * referral link — is built server-side and handed to the model as fixed
     * source-of-truth text; the prompt forbids altering names, prices, or the
     * link itself, so a hallucination can only mangle tone, never money or
     * the tracking code.
     */
    public function shareMessage(Request $request, ServiceListFormatter $formatter): JsonResponse
    {
        $data = $request->validate([
            'platform' => ['required', 'string', 'max:40'],
            'category' => ['nullable', 'string'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        if (! $user->getAttribute('referral_code')) {
            $user->update(['referral_code' => User::generateReferralCode()]);
            $user->refresh();
        }

        $link = $this->referralLink((string) $user->getAttribute('referral_code'));
        $rates = app(\App\Services\ReferralService::class)->programRates();

        // The original referral pitch (same wording used by the one-click
        // WhatsApp/Facebook/Instagram buttons) is the primary message here —
        // the service list is a supplementary addition, not a replacement.
        $pitch = __('messages.referral_share_message', [
            'percent' => rtrim(rtrim(number_format((float) $rates['welcome_bonus_percent'], 2), '0'), '.'),
            'link' => $link,
        ]);

        $query = Service::active()->orderBy('name')->limit(5);
        if ($data['category'] ?? null) {
            $query->where('category', $data['category']);
        }
        $services = $query->get(['name', 'rate', 'min_qty']);

        $lines = [$pitch];

        if ($services->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'A few services you can try:';
            foreach ($services as $service) {
                $rate = number_format((float) $service->rate, 2);
                $lines[] = "• {$service->name} — \${$rate}/1000 (min: {$service->min_qty})";
            }
        }

        $raw = implode("\n", $lines);

        if (! $formatter->isAvailable()) {
            return response()->json(['text' => $raw, 'ai_used' => false]);
        }

        $extraInstructions = $data['platform'] === 'Twitter/X'
            ? 'Keep the whole thing under 280 characters total — mention at most one or two services and keep the referral link intact.'
            : null;

        $enhanced = $formatter->format($raw, $data['platform'], $extraInstructions);

        return response()->json([
            'text' => $enhanced ?? $raw,
            'ai_used' => $enhanced !== null,
        ]);
    }
}
