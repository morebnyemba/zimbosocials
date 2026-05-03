<?php
// app/Http/Controllers/MarketerController.php

namespace App\Http\Controllers;

use App\Models\BusinessContract;
use App\Models\ContractApplication;
use App\Models\ContractProofSubmission;
use App\Models\MarketerReview;
use App\Models\MarketerSocialLink;
use App\Models\Order;
use App\Models\Service;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MarketerController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();
        $userId = (int) $user->getAuthIdentifier();

        $stats = [
            'total_orders'  => Order::forUser($userId)->count(),
            'active_orders' => Order::forUser($userId)->active()->count(),
            'total_spend'   => Order::forUser($userId)->sum('charge'),
            'balance'       => $user->balance,
            'services'      => Service::active()->count(),
            'contract_earnings' => Transaction::where('user_id', $userId)
                ->where('type', 'contract_earning')
                ->where('status', 'completed')
                ->sum('amount'),
            'withdrawn' => abs(Transaction::where('user_id', $userId)
                ->where('type', 'withdrawal')
                ->sum('amount')),
            'client_orders_this_month' => Order::forUser($userId)
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
        ];

        $recent_orders = Order::with('service')
            ->forUser($userId)
            ->latest()
            ->limit(10)
            ->get();

        $recent_transactions = Transaction::where('user_id', $userId)
            ->latest()
            ->limit(10)
            ->get();

        $available_contracts = BusinessContract::with([
            'business:id,name,company_name',
            'applications' => fn ($query) => $query
                ->where('marketer_id', $userId)
                ->select('id', 'business_contract_id', 'marketer_id', 'status', 'created_at'),
        ])
            ->where('status', 'open')
            ->where('user_id', '!=', $userId)
            ->latest()
            ->limit(8)
            ->get();

        $my_contract_applications = ContractApplication::with([
            'contract:id,user_id,title,platform,status',
            'contract.business:id,name,company_name',
        ])
            ->where('marketer_id', $userId)
            ->latest()
            ->limit(8)
            ->get();

        $contract_stats = [
            'open_contracts_available' => BusinessContract::where('status', 'open')->where('user_id', '!=', $userId)->count(),
            'my_pending_applications' => ContractApplication::where('marketer_id', $userId)->where('status', 'pending')->count(),
            'my_approved_contracts' => ContractApplication::where('marketer_id', $userId)->where('status', 'approved')->count(),
        ];

        // Rating stats for this marketer
        $ratingRow = \Illuminate\Support\Facades\DB::table('marketer_reviews')
            ->where('marketer_id', $userId)
            ->selectRaw('ROUND(AVG(rating), 1) as avg_rating, COUNT(*) as review_count')
            ->first();

        $stats['avg_rating']   = (float) ($ratingRow->avg_rating ?? 0);
        $stats['review_count'] = (int) ($ratingRow->review_count ?? 0);

        // Recent reviews
        $recent_reviews = MarketerReview::with([
            'reviewer:id,name,company_name',
            'contract:id,title',
        ])
            ->where('marketer_id', $userId)
            ->latest()
            ->limit(5)
            ->get();

        // Social links
        $social_links = MarketerSocialLink::where('user_id', $userId)->get();

        // Approved contract applications that still need proof submission
        $approved_apps = ContractApplication::with([
            'contract:id,title,platform',
            'proofSubmissions' => fn ($q) => $q->where('marketer_id', $userId)->latest()->limit(1),
        ])
            ->where('marketer_id', $userId)
            ->where('status', 'approved')
            ->latest()
            ->get();

        return Inertia::render('Marketer/Dashboard', compact(
            'stats',
            'recent_orders',
            'recent_transactions',
            'available_contracts',
            'my_contract_applications',
            'contract_stats',
            'social_links',
            'approved_apps',
            'recent_reviews'
        ));
    }
}
