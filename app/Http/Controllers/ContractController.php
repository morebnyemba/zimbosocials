<?php

namespace App\Http\Controllers;

use App\Models\BusinessContract;
use App\Models\ContractApplication;
use App\Models\MarketerReview;
use App\Models\MarketerSocialLink;
use App\Models\User;
use App\Models\Transaction;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ContractController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $my_contracts = [];
        if ($user->account_type === 'business') {
            $my_contracts = BusinessContract::where('user_id', $user->id)
                ->withCount('applications')
                ->latest()
                ->paginate(10);
        } else {
            // Marketer see their applications
            $my_contracts = ContractApplication::where('marketer_id', $user->id)
                ->with(['contract.business'])
                ->latest()
                ->paginate(10);
        }

        $available_contracts = BusinessContract::open()
            ->where('user_id', '!=', $user->id)
            ->with(['business:id,name,company_name'])
            ->withCount('applications')
            ->latest()
            ->paginate(10);

        // Top-rated marketers for the Rankings tab — cached for 10 minutes
        $top_marketers = Cache::remember('contracts:top_marketers', 600, function () {
            return User::where('role', 'marketer')
                ->where('marketer_status', 'approved')
                ->withAvg('receivedReviews as avg_rating', 'rating')
                ->withCount('receivedReviews as review_count')
                ->withCount(['contractApplications as completed_contracts' => fn ($q) => $q->where('status', 'completed')])
                ->having('review_count', '>', 0)
                ->orderByDesc('avg_rating')
                ->orderByDesc('review_count')
                ->limit(30)
                ->get(['id', 'name', 'company_name', 'slug', 'profile_image_url']);
        });

        return Inertia::render('Contracts/Index', [
            'my_contracts'        => $my_contracts,
            'available_contracts' => $available_contracts,
            'top_marketers'       => $top_marketers,
        ]);
    }

    public function show(BusinessContract $contract): Response
    {
        $this->authorize('view', $contract);

        $contract->load([
            'applications' => function ($q) {
                $q->with([
                    'marketer' => function ($q) {
                        $q->withAvg('receivedReviews as avg_rating', 'rating')
                          ->withCount('receivedReviews as review_count')
                          ->with('socialLinks');
                    },
                    'decider',
                    'proofs',
                    'review',
                ]);
            },
        ]);

        return Inertia::render('Contracts/Show', [
            'contract' => $contract,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $this->authorize('create', BusinessContract::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'platform' => ['nullable', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:5000'],
            'budget' => ['required', 'numeric', 'min:0.5'],
            'slots' => ['required', 'integer', 'min:1', 'max:20'],
            'deadline_at' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $unitBudget = (float) $data['budget'];
        $slots = (int) $data['slots'];
        $platformFeeRate = 0.10; // 10% Service Fee

        $totalBudget = $unitBudget * $slots;
        $totalFee = $totalBudget * $platformFeeRate;
        $totalCharge = $totalBudget + $totalFee;

        if ((float) $user->balance < $totalCharge) {
            return back()->with('error', "Insufficient balance. Total campaign cost is $" . number_format($totalCharge, 2) . " (incl. 10% service fee). Please top up.");
        }

        try {
            DB::transaction(function () use ($user, $data, $totalCharge, $totalBudget, $totalFee): void {
                // Lock user row to prevent concurrent balance manipulation
                $lockedUser = User::lockForUpdate()->findOrFail($user->id);
                $balanceBefore = (float) $lockedUser->balance;

                if ($balanceBefore < $totalCharge) {
                    throw new \RuntimeException("Insufficient balance. Total campaign cost is $" . number_format($totalCharge, 2) . " (incl. 10% service fee). Please top up.");
                }

                $lockedUser->decrement('balance', $totalCharge);

                Transaction::create([
                    'user_id' => (int) $lockedUser->getKey(),
                    'type' => 'contract_payout',
                    'amount' => -$totalCharge,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceBefore - $totalCharge,
                    'status' => 'completed',
                    'notes' => 'Pre-funding contract: ' . $data['title'] . ' (Budget: $' . $totalBudget . ', Fee: $' . $totalFee . ')',
                ]);

                BusinessContract::create([
                    'user_id' => (int) $lockedUser->getAuthIdentifier(),
                    'title' => $data['title'],
                    'platform' => $data['platform'],
                    'description' => $data['description'],
                    'budget' => $data['budget'],
                    'slots' => $data['slots'],
                    'funded_amount' => $totalCharge,
                    'deadline_at' => $data['deadline_at'],
                    'status' => 'open',
                ]);
            });

            return back()->with('success', 'Contract mission deployed and fully funded in escrow!');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to deploy contract: ' . $e->getMessage());
        }
    }

    public function apply(Request $request, BusinessContract $contract): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $contractId = (int) $contract->getKey();

        $data = $request->validate([
            'pitch' => ['nullable', 'string', 'max:2500'],
        ]);

        // Policy check covers role, marketer_status, and contract open status
        $this->authorize('apply', $contract);

        // Require at least one social link before applying
        $hasSocialLinks = MarketerSocialLink::where('user_id', (int) $user->getAuthIdentifier())->exists();
        if (!$hasSocialLinks) {
            return back()->with('error', 'Add at least one managed social account in your Settings before applying to contracts.');
        }

        ContractApplication::updateOrCreate(
            [
                'business_contract_id' => $contractId,
                'marketer_id' => (int) $user->getAuthIdentifier(),
            ],
            [
                'pitch' => $data['pitch'] ?? null,
                'status' => 'pending',
                'decided_by' => null,
                'reviewed_at' => null,
            ]
        );

        NotificationService::notify(
            (int) $contract->getAttribute('user_id'),
            'contract_application',
            'New Contract Application',
            "{$user->name} applied to your contract: {$contract->title}",
            ['contract_id' => $contractId]
        );

        return back()->with('success', 'Application submitted successfully.');
    }

    /**
     * Approve or deny a contract application.
     * Wrapped in a DB transaction to prevent race conditions on concurrent approvals.
     */
    public function decide(BusinessContract $contract, ContractApplication $application, Request $request): RedirectResponse
    {
        $this->authorize('update', $contract);

        $userId = (int) Auth::id();
        $contractId = (int) $contract->getKey();

        $decision = $request->validate([
            'decision' => ['required', 'in:approved,denied'],
        ])['decision'];

        if ($decision === 'approved') {
            try {
                DB::transaction(function () use ($contract, $application, $contractId, $userId): void {
                    // Lock the contract row to prevent concurrent slot allocation
                    $lockedContract = BusinessContract::lockForUpdate()->findOrFail($contractId);
                    $totalSlots = (int) $lockedContract->slots;

                    $filledSlots = ContractApplication::where('business_contract_id', $contractId)
                        ->where('status', 'approved')
                        ->count();

                    $completedSlots = ContractApplication::where('business_contract_id', $contractId)
                        ->where('status', 'completed')
                        ->count();

                    if (($filledSlots + $completedSlots) >= $totalSlots) {
                        throw new \RuntimeException('All slots are already filled for this contract.');
                    }

                    $application->update([
                        'status' => 'approved',
                        'decided_by' => $userId,
                        'reviewed_at' => now(),
                    ]);

                    if (($filledSlots + $completedSlots + 1) >= $totalSlots) {
                        $lockedContract->update(['status' => 'filled']);

                        ContractApplication::where('business_contract_id', $contractId)
                            ->where('status', 'pending')
                            ->update([
                                'status' => 'ignored',
                                'decided_by' => $userId,
                                'reviewed_at' => now(),
                            ]);
                    }
                });

                return back()->with('success', 'Marketer hired! Fund release is pending proof review.');
            } catch (\RuntimeException $e) {
                return back()->with('error', $e->getMessage());
            }
        } else {
            $application->update([
                'status' => 'denied',
                'decided_by' => $userId,
                'reviewed_at' => now(),
            ]);
            return back()->with('success', 'Application denied.');
        }
    }

    public function closeContract(BusinessContract $contract): RedirectResponse
    {
        $this->authorize('close', $contract);

        /** @var User $user */
        $user = Auth::user();

        try {
            DB::transaction(function () use ($contract, $user): void {
                // Lock user and contract rows
                $lockedContract = BusinessContract::lockForUpdate()->findOrFail($contract->id);
                $lockedUser = User::lockForUpdate()->findOrFail($user->id);

                $totalSlots = (int) $lockedContract->slots;
                $approvedApps = ContractApplication::where('business_contract_id', $lockedContract->id)
                    ->whereIn('status', ['approved', 'completed'])
                    ->count();

                $unusedSlots = $totalSlots - $approvedApps;

                if ($unusedSlots > 0) {
                    $unitBudget = (float) $lockedContract->budget;
                    $feeRate = 0.10;
                    $refundPerSlot = $unitBudget + ($unitBudget * $feeRate);
                    $totalRefund = $unusedSlots * $refundPerSlot;

                    $balanceBefore = (float) $lockedUser->balance;
                    $lockedUser->increment('balance', $totalRefund);

                    Transaction::create([
                        'user_id' => (int) $lockedUser->getKey(),
                        'type' => 'refund',
                        'amount' => $totalRefund,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balanceBefore + $totalRefund,
                        'status' => 'completed',
                        'notes' => 'Refund for ' . $unusedSlots . ' unused slots on contract #' . $lockedContract->id,
                    ]);
                }

                $lockedContract->update(['status' => 'closed']);

                ContractApplication::where('business_contract_id', $lockedContract->id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'ignored',
                        'decided_by' => (int) $lockedUser->id,
                        'reviewed_at' => now(),
                    ]);
            });

            return back()->with('success', 'Contract closed and unused funds refunded to your wallet.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to close contract: ' . $e->getMessage());
        }
    }
}
