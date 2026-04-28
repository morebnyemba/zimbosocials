<?php

namespace App\Http\Controllers;

use App\Models\BusinessContract;
use App\Models\ContractApplication;
use App\Models\MarketerSocialLink;
use App\Models\User;
use App\Models\Transaction;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        return Inertia::render('Contracts/Index', [
            'my_contracts'        => $my_contracts,
            'available_contracts' => $available_contracts,
        ]);
    }

    public function show(BusinessContract $contract): Response
    {
        $user = Auth::user();
        if ((int) $contract->getAttribute('user_id') !== (int) $user->getAuthIdentifier()) {
            abort(403);
        }

        $contract->load([
            'applications.marketer.socialLinks', 
            'applications.decider',
            'applications.proofs'
        ]);

        return Inertia::render('Contracts/Show', [
            'contract' => $contract,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->account_type !== 'business') {
            return back()->with('error', 'Only business accounts can post contracts.');
        }

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
                $balanceBefore = (float) $user->balance;
                $user->decrement('balance', $totalCharge);

                Transaction::create([
                    'user_id' => (int) $user->getKey(),
                    'type' => 'contract_payout',
                    'amount' => -$totalCharge,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceBefore - $totalCharge,
                    'status' => 'completed',
                    'notes' => 'Pre-funding contract: ' . $data['title'] . ' (Budget: $' . $totalBudget . ', Fee: $' . $totalFee . ')',
                ]);

                BusinessContract::create([
                    'user_id' => (int) $user->getAuthIdentifier(),
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
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to deploy contract: ' . $e->getMessage());
        }
    }

    public function apply(Request $request, BusinessContract $contract): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $contractId = (int) $contract->getKey();
        $contractStatus = (string) $contract->getAttribute('status');
        $userRole = (string) $user->getAttribute('role');

        $data = $request->validate([
            'pitch' => ['nullable', 'string', 'max:2500'],
        ]);

        if (!in_array($userRole, ['marketer', 'reseller'], true)) {
            return back()->with('error', 'Only marketer accounts can apply to contracts.');
        }

        if ($contractStatus !== 'open') {
            return back()->with('error', 'This contract is no longer open.');
        }

        if ($user->marketer_status !== 'approved') {
            return back()->with('error', 'Your marketer account is currently ' . ($user->marketer_status ?: 'pending approval') . '. You will be able to apply once approved by an admin.');
        }

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

    public function decide(BusinessContract $contract, ContractApplication $application, Request $request): RedirectResponse
    {
        $user = Auth::user();
        $userId = (int) $user->getAuthIdentifier();
        $contractId = (int) $contract->getKey();
        $applicationId = (int) $application->getKey();

        if ((int) $contract->getAttribute('user_id') !== $userId) {
            abort(403);
        }

        $decision = $request->validate([
            'decision' => ['required', 'in:approved,denied'],
        ])['decision'];

        if ($decision === 'approved') {
            $totalSlots = (int) $contract->slots;
            $filledSlots = ContractApplication::where('business_contract_id', $contractId)
                ->where('status', 'approved')
                ->count();
            
            $completedSlots = ContractApplication::where('business_contract_id', $contractId)
                ->where('status', 'completed')
                ->count();

            if (($filledSlots + $completedSlots) >= $totalSlots) {
                return back()->with('error', 'All slots are already filled for this contract.');
            }

            $application->update([
                'status' => 'approved',
                'decided_by' => $userId,
                'reviewed_at' => now(),
            ]);

            if (($filledSlots + $completedSlots + 1) >= $totalSlots) {
                $contract->update(['status' => 'filled']);
                
                ContractApplication::where('business_contract_id', $contractId)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'ignored',
                        'decided_by' => $userId,
                        'reviewed_at' => now(),
                    ]);
            }

            return back()->with('success', 'Marketer hired! Fund release is pending proof review.');
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
        /** @var User $user */
        $user = Auth::user();
        $userId = (int) $user->getAuthIdentifier();

        if ((int) $contract->getAttribute('user_id') !== $userId) {
            abort(403);
        }

        try {
            DB::transaction(function () use ($contract, $user): void {
                $totalSlots = (int) $contract->slots;
                $approvedApps = ContractApplication::where('business_contract_id', $contract->id)
                    ->whereIn('status', ['approved', 'completed'])
                    ->count();
                
                $unusedSlots = $totalSlots - $approvedApps;

                if ($unusedSlots > 0) {
                    $unitBudget = (float) $contract->budget;
                    $feeRate = 0.10;
                    $refundPerSlot = $unitBudget + ($unitBudget * $feeRate);
                    $totalRefund = $unusedSlots * $refundPerSlot;

                    $balanceBefore = (float) $user->balance;
                    $user->increment('balance', $totalRefund);

                    Transaction::create([
                        'user_id' => (int) $user->getKey(),
                        'type' => 'refund',
                        'amount' => $totalRefund,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balanceBefore + $totalRefund,
                        'status' => 'completed',
                        'notes' => 'Refund for ' . $unusedSlots . ' unused slots on contract #' . $contract->id,
                    ]);
                }

                $contract->update(['status' => 'closed']);

                ContractApplication::where('business_contract_id', $contract->id)
                    ->where('status', 'pending')
                    ->update([
                        'status' => 'ignored',
                        'decided_by' => (int) $user->id,
                        'reviewed_at' => now(),
                    ]);
            });

            return back()->with('success', 'Contract closed and unused funds refunded to your wallet.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to close contract: ' . $e->getMessage());
        }
    }
}
