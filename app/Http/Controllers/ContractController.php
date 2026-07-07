<?php

namespace App\Http\Controllers;

use App\Models\BusinessContract;
use App\Models\ContractApplication;
use App\Models\MarketerSocialLink;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ContractSettlementService;
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
    private const PLATFORM_FEE_RATE = 0.10;

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $my_contracts = [];
        if ($user->account_type === 'business') {
            $my_contracts = BusinessContract::where('user_id', $user->id)
                ->withCount('applications')
                ->withCount([
                    'applications as pending_applications_count' => fn ($query) => $query->where('status', ContractApplication::STATUS_PENDING),
                ])
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
                ->withCount(['contractApplications as completed_contracts' => fn ($q) => $q->where('status', ContractApplication::STATUS_COMPLETED)])
                ->having('review_count', '>', 0)
                ->orderByDesc('avg_rating')
                ->orderByDesc('review_count')
                ->limit(30)
                ->get(['id', 'name', 'company_name', 'slug', 'profile_image_url']);
        });

        return Inertia::render('Contracts/Index', [
            'my_contracts' => $my_contracts,
            'available_contracts' => $available_contracts,
            'top_marketers' => $top_marketers,
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

        $data = $this->validateContractData($request);
        [, , $totalBudget, $totalFee, $totalCharge] = $this->calculateFundingTotals($data);

        if ((float) $user->balance < $totalCharge) {
            return back()->with('error', 'Insufficient balance. Total campaign cost is $'.number_format($totalCharge, 2).' (incl. 10% service fee). Please top up.');
        }

        try {
            DB::transaction(function () use ($user, $data, $totalCharge, $totalBudget, $totalFee): void {
                // Lock user row to prevent concurrent balance manipulation
                $lockedUser = User::lockForUpdate()->findOrFail($user->id);
                $balanceBefore = (float) $lockedUser->balance;

                if ($balanceBefore < $totalCharge) {
                    throw new \RuntimeException('Insufficient balance. Total campaign cost is $'.number_format($totalCharge, 2).' (incl. 10% service fee). Please top up.');
                }

                $lockedUser->decrement('balance', $totalCharge);

                Transaction::create([
                    'user_id' => (int) $lockedUser->getKey(),
                    'type' => 'contract_payout',
                    'amount' => -$totalCharge,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceBefore - $totalCharge,
                    'status' => 'completed',
                    'notes' => 'Pre-funding contract: '.$data['title'].' (Budget: $'.$totalBudget.', Fee: $'.$totalFee.')',
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
                    'status' => BusinessContract::STATUS_OPEN,
                ]);
            });

            return back()->with('success', 'Contract mission deployed and fully funded in escrow!');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to deploy contract: '.$e->getMessage());
        }
    }

    public function update(Request $request, BusinessContract $contract): RedirectResponse
    {
        $this->authorize('update', $contract);

        $data = $this->validateContractData($request);

        try {
            DB::transaction(function () use ($contract, $data): void {
                $lockedContract = BusinessContract::lockForUpdate()->findOrFail($contract->getKey());

                if ($lockedContract->status !== BusinessContract::STATUS_OPEN) {
                    throw new \RuntimeException('Only open contracts can be edited.');
                }

                [$unitBudget, $slots, $totalBudget, $totalFee, $totalCharge] = $this->calculateFundingTotals($data);
                $currentFundedAmount = $this->resolveFundedAmount($lockedContract);
                $activeAssignments = ContractApplication::where('business_contract_id', $lockedContract->getKey())
                    ->whereIn('status', ContractApplication::slotConsumingStatuses())
                    ->count();

                $financialsChanged = round((float) $lockedContract->budget, 2) !== $unitBudget
                    || (int) $lockedContract->slots !== $slots;

                if ($slots < $activeAssignments) {
                    throw new \RuntimeException("This contract already has {$activeAssignments} active slot(s). Increase slots or keep the current value.");
                }

                if ($financialsChanged && $activeAssignments > 0) {
                    throw new \RuntimeException('Budget and slot changes are locked once a marketer has been hired. You can still update the brief, platform, or deadline.');
                }

                $lockedUser = User::lockForUpdate()->findOrFail((int) $lockedContract->user_id);

                // Escrow only moves when budget/slots actually changed.
                // funded_amount is drawn down as payouts release, so comparing
                // it against the full total on a brief-only edit would wrongly
                // re-charge the business for already-paid slots.
                $chargeDifference = $financialsChanged ? round($totalCharge - $currentFundedAmount, 2) : 0.0;

                if ($chargeDifference > 0) {
                    $balanceBefore = (float) $lockedUser->balance;

                    if ($balanceBefore < $chargeDifference) {
                        throw new \RuntimeException('Insufficient balance to increase contract funding by $'.number_format($chargeDifference, 2).'.');
                    }

                    $lockedUser->decrement('balance', $chargeDifference);

                    Transaction::create([
                        'user_id' => (int) $lockedUser->getKey(),
                        'type' => 'contract_payout',
                        'amount' => -$chargeDifference,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balanceBefore - $chargeDifference,
                        'status' => 'completed',
                        'notes' => 'Escrow increase for contract #'.$lockedContract->getKey().' (Budget: $'.$totalBudget.', Fee: $'.$totalFee.')',
                    ]);
                } elseif ($chargeDifference < 0) {
                    $refundAmount = abs($chargeDifference);
                    $balanceBefore = (float) $lockedUser->balance;
                    $lockedUser->increment('balance', $refundAmount);

                    Transaction::create([
                        'user_id' => (int) $lockedUser->getKey(),
                        'type' => 'refund',
                        'amount' => $refundAmount,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balanceBefore + $refundAmount,
                        'status' => 'completed',
                        'notes' => 'Escrow adjustment refund for contract #'.$lockedContract->getKey(),
                    ]);
                }

                $lockedContract->update([
                    'title' => $data['title'],
                    'platform' => $data['platform'],
                    'description' => $data['description'],
                    'budget' => $unitBudget,
                    'slots' => $slots,
                    'funded_amount' => $financialsChanged ? $totalCharge : $lockedContract->funded_amount,
                    'deadline_at' => $data['deadline_at'],
                    'status' => $activeAssignments >= $slots
                        ? BusinessContract::STATUS_FILLED
                        : BusinessContract::STATUS_OPEN,
                ]);
            });

            return back()->with('success', 'Contract updated successfully.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update contract: '.$e->getMessage());
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

        if ($contract->isPastDeadline()) {
            return back()->with('error', 'This contract\'s deadline has passed — applications are closed.');
        }

        // Require at least one social link before applying
        $hasSocialLinks = MarketerSocialLink::where('user_id', (int) $user->getAuthIdentifier())->exists();
        if (! $hasSocialLinks) {
            return back()->with('error', 'Add at least one managed social account in your Settings before applying to contracts.');
        }

        $existingApplication = ContractApplication::where('business_contract_id', $contractId)
            ->where('marketer_id', (int) $user->getAuthIdentifier())
            ->first();

        if ($existingApplication && in_array($existingApplication->status, ContractApplication::slotConsumingStatuses(), true)) {
            return back()->with('error', 'You already hold a live slot on this contract.');
        }

        ContractApplication::updateOrCreate(
            [
                'business_contract_id' => $contractId,
                'marketer_id' => (int) $user->getAuthIdentifier(),
            ],
            [
                'pitch' => $data['pitch'] ?? null,
                'status' => ContractApplication::STATUS_PENDING,
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

        if ((int) $application->getAttribute('business_contract_id') !== $contractId) {
            abort(404);
        }

        $decision = $request->validate([
            'decision' => ['required', 'in:'.ContractApplication::STATUS_APPROVED.','.ContractApplication::STATUS_DENIED],
        ])['decision'];

        if ($decision === ContractApplication::STATUS_APPROVED) {
            if ($contract->isPastDeadline()) {
                return back()->with('error', 'This contract\'s deadline has passed — close it to refund unused escrow, or extend the deadline first.');
            }

            try {
                DB::transaction(function () use ($application, $contractId, $userId): void {
                    // Lock the contract row to prevent concurrent slot allocation
                    $lockedContract = BusinessContract::lockForUpdate()->findOrFail($contractId);
                    $totalSlots = (int) $lockedContract->slots;

                    $filledSlots = ContractApplication::where('business_contract_id', $contractId)
                        ->where('status', ContractApplication::STATUS_APPROVED)
                        ->count();

                    $completedSlots = ContractApplication::where('business_contract_id', $contractId)
                        ->where('status', ContractApplication::STATUS_COMPLETED)
                        ->count();

                    if (($filledSlots + $completedSlots) >= $totalSlots) {
                        throw new \RuntimeException('All slots are already filled for this contract.');
                    }

                    $application->update([
                        'status' => ContractApplication::STATUS_APPROVED,
                        'decided_by' => $userId,
                        'reviewed_at' => now(),
                    ]);

                    if (($filledSlots + $completedSlots + 1) >= $totalSlots) {
                        $lockedContract->update(['status' => BusinessContract::STATUS_FILLED]);

                        ContractApplication::where('business_contract_id', $contractId)
                            ->where('status', ContractApplication::STATUS_PENDING)
                            ->update([
                                'status' => ContractApplication::STATUS_IGNORED,
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
                'status' => ContractApplication::STATUS_DENIED,
                'decided_by' => $userId,
                'reviewed_at' => now(),
            ]);

            return back()->with('success', 'Application denied.');
        }
    }

    public function closeContract(BusinessContract $contract, ContractSettlementService $settlement): RedirectResponse
    {
        $this->authorize('close', $contract);

        try {
            $settlement->closeAndRefundUnusedSlots($contract, 'Refund on contract close');

            return back()->with('success', 'Contract closed and unused funds refunded to your wallet.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to close contract: '.$e->getMessage());
        }
    }

    /**
     * Revoke an approved application whose marketer never delivered. Frees
     * the slot (reopening a filled contract) so the escrow can be reassigned
     * or refunded on close — previously a no-show marketer locked that slot's
     * funds forever.
     */
    public function revoke(BusinessContract $contract, ContractApplication $application): RedirectResponse
    {
        $this->authorize('update', $contract);

        if ((int) $application->getAttribute('business_contract_id') !== (int) $contract->getKey()) {
            abort(404);
        }

        try {
            DB::transaction(function () use ($contract, $application): void {
                $lockedContract = BusinessContract::lockForUpdate()->findOrFail($contract->getKey());
                $lockedApplication = ContractApplication::lockForUpdate()->findOrFail($application->getKey());

                if ($lockedApplication->status !== ContractApplication::STATUS_APPROVED) {
                    throw new \RuntimeException('Only approved (not yet completed) applications can be revoked.');
                }

                $hasApprovedProof = $lockedApplication->proofSubmissions()
                    ->where('status', 'approved')
                    ->exists();

                if ($hasApprovedProof) {
                    throw new \RuntimeException('This marketer already has approved proof of work — the slot is completed, not revocable.');
                }

                $lockedApplication->update([
                    'status' => ContractApplication::STATUS_REVOKED,
                    'decided_by' => (int) Auth::id(),
                    'reviewed_at' => now(),
                ]);

                // Reject any still-pending proof so it can't be approved (and
                // paid) after the slot was taken away.
                $lockedApplication->proofSubmissions()
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'rejected',
                        'reviewed_by' => (int) Auth::id(),
                        'reviewed_at' => now(),
                    ]);

                // Freeing a slot reopens a filled contract.
                if ($lockedContract->status === BusinessContract::STATUS_FILLED) {
                    $lockedContract->update(['status' => BusinessContract::STATUS_OPEN]);
                }
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        NotificationService::notify(
            (int) $application->getAttribute('marketer_id'),
            'contract_application',
            'Contract Slot Revoked',
            "Your slot on the contract \"{$contract->title}\" was revoked because no approved proof of work was submitted.",
            ['contract_id' => (int) $contract->getKey()]
        );

        return back()->with('success', 'Application revoked — the slot is available again.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateContractData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'platform' => ['nullable', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:5000'],
            'budget' => ['required', 'numeric', 'min:0.5'],
            'slots' => ['required', 'integer', 'min:1', 'max:20'],
            'deadline_at' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: float, 1: int, 2: float, 3: float, 4: float}
     */
    private function calculateFundingTotals(array $data): array
    {
        $unitBudget = round((float) $data['budget'], 2);
        $slots = (int) $data['slots'];
        $totalBudget = round($unitBudget * $slots, 2);
        $totalFee = round($totalBudget * self::PLATFORM_FEE_RATE, 2);
        $totalCharge = round($totalBudget + $totalFee, 2);

        return [$unitBudget, $slots, $totalBudget, $totalFee, $totalCharge];
    }

    private function resolveFundedAmount(BusinessContract $contract): float
    {
        $storedFundedAmount = round((float) $contract->funded_amount, 2);

        if ($storedFundedAmount > 0) {
            return $storedFundedAmount;
        }

        return round(((float) $contract->budget * (int) $contract->slots) * (1 + self::PLATFORM_FEE_RATE), 2);
    }
}
