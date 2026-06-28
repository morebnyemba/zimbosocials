<?php

// app/Http/Controllers/MarketerController.php

namespace App\Http\Controllers;

use App\Models\BusinessContract;
use App\Models\ContractApplication;
use App\Models\MarketerReview;
use App\Models\MarketerSocialLink;
use App\Models\Order;
use App\Models\Service;
use App\Models\Transaction;
use App\Services\AI\ContentCalendarGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MarketerController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();
        $userId = (int) $user->getAuthIdentifier();

        // Consolidated: 1 aggregate query instead of 4 Order queries
        $orderRow = Order::forUser($userId)
            ->selectRaw("
                COUNT(*)                                                                     AS total_orders,
                SUM(CASE WHEN status IN ('pending','processing','in_progress') THEN 1 ELSE 0 END) AS active_orders,
                SUM(charge)                                                                  AS total_spend,
                SUM(CASE WHEN created_at >= ? AND created_at <= ? THEN 1 ELSE 0 END)         AS client_orders_this_month
            ", [now()->startOfMonth(), now()->endOfMonth()])
            ->first();

        // Consolidated: 1 aggregate query instead of 2 Transaction queries
        $finRow = Transaction::where('user_id', $userId)
            ->selectRaw("
                SUM(CASE WHEN type = 'contract_earning' AND status = 'completed' THEN amount ELSE 0 END) AS contract_earnings,
                ABS(SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END))                           AS withdrawn
            ")
            ->first();

        $stats = [
            'total_orders' => (int) ($orderRow->total_orders ?? 0),
            'active_orders' => (int) ($orderRow->active_orders ?? 0),
            'total_spend' => (float) ($orderRow->total_spend ?? 0),
            'balance' => $user->balance,
            'services' => Service::active()->count(),
            'contract_earnings' => (float) ($finRow->contract_earnings ?? 0),
            'withdrawn' => (float) ($finRow->withdrawn ?? 0),
            'client_orders_this_month' => (int) ($orderRow->client_orders_this_month ?? 0),
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
        $ratingRow = DB::table('marketer_reviews')
            ->where('marketer_id', $userId)
            ->selectRaw('ROUND(AVG(rating), 1) as avg_rating, COUNT(*) as review_count')
            ->first();

        $stats['avg_rating'] = (float) ($ratingRow->avg_rating ?? 0);
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

    public function contentCalendar(): Response
    {
        return Inertia::render('Marketer/ContentCalendar');
    }

    public function portfolioCaption(): Response
    {
        return Inertia::render('Marketer/PortfolioCaption');
    }

    public function generateCalendar(Request $request, ContentCalendarGenerator $generator): JsonResponse
    {
        $data = $request->validate([
            'brief' => ['required', 'string', 'max:500'],
            'platform' => ['nullable', 'string', 'in:instagram,tiktok,facebook,x,whatsapp'],
            'tone' => ['nullable', 'string', 'max:200'],
        ]);

        $result = $generator->generate(
            (string) $data['brief'],
            isset($data['platform']) ? (string) $data['platform'] : null,
            isset($data['tone']) ? (string) $data['tone'] : null
        );

        if ($result === null) {
            return response()->json(['message' => 'AI content calendar is not available.'], 503);
        }

        return response()->json($result);
    }
}
