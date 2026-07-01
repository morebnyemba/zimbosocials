<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LeaderboardService;
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
        $path = trim((string) Setting::get('referral_landing_path', '/'));

        if ($path === '') {
            $path = '/';
        }
        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        $separator = str_contains($path, '?') ? '&' : '?';

        return url($path.$separator.'ref='.urlencode($code));
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
            ->get(['id', 'amount', 'method', 'reference', 'created_at', 'notes'])
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
        ]);
    }
}
