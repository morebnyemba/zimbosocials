<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ReferralController extends Controller
{
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
            ->map(fn (User $referral) => [
                'id' => $referral->getKey(),
                'name' => $referral->getAttribute('name'),
                'email' => $referral->getAttribute('email'),
                'joined_at' => optional($referral->getAttribute('created_at'))->toISOString(),
                'first_deposit_rewarded' => (bool) $referral->getAttribute('referred_bonus_awarded_at'),
                'completed_deposits' => (int) $referral->completed_deposits,
                'orders_count' => (int) $referral->orders_count,
            ])
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
            'successful_first_deposits' => $referrals->where('first_deposit_rewarded', true)->count(),
            'total_rewards_earned' => round((float) $rewardTransactions->sum('amount'), 4),
            'order_commissions_earned' => round((float) $rewardTransactions->where('method', 'referral_order')->sum('amount'), 4),
        ];

        return Inertia::render('Referrals/Index', [
            'summary' => $summary,
            'referralCode' => $user->getAttribute('referral_code'),
            'referralLink' => url('/register?ref=' . $user->getAttribute('referral_code')),
            'referrals' => $referrals,
            'rewardHistory' => $rewardTransactions,
        ]);
    }
}
