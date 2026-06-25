<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ContractApplication;
use App\Models\MarketerPortfolio;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AI\ContentModerator;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AdminMarketerController extends Controller
{
    public function index(Request $request): Response
    {
        $query = User::whereIn('role', ['marketer', 'reseller']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('marketer_status', $status);
        }

        $marketers = $query->latest()->paginate(25)->withQueryString();

        // Consolidated: 1 GROUP BY instead of 4 separate counts
        $rawCounts = User::whereIn('role', ['marketer', 'reseller'])
            ->selectRaw('marketer_status, COUNT(*) as cnt')
            ->groupBy('marketer_status')
            ->pluck('cnt', 'marketer_status');

        $status_counts = [
            'all' => $rawCounts->sum(),
            'pending' => (int) ($rawCounts['pending'] ?? 0),
            'approved' => (int) ($rawCounts['approved'] ?? 0),
            'rejected' => (int) ($rawCounts['rejected'] ?? 0),
        ];

        return Inertia::render('Admin/Marketers/Index', [
            'marketers' => $marketers,
            'filters' => $request->only(['search', 'status']),
            'status_counts' => $status_counts,
        ]);
    }

    public function approve(User $user): RedirectResponse
    {
        if (! $user->isMarketer() && $user->role !== 'reseller') {
            return back()->with('error', 'User is not a marketer.');
        }

        $user->update(['marketer_status' => 'approved']);

        AuditLog::log(
            'marketer.approved',
            Auth::id(),
            User::class,
            $user->id,
            ['status' => 'pending'],
            ['status' => 'approved']
        );

        NotificationService::notify(
            $user->id,
            'marketer_approved',
            'Marketer Account Approved',
            'Your marketer account has been reviewed and approved! You can now apply for contracts.'
        );

        return back()->with('success', "Marketer {$user->name} approved successfully.");
    }

    public function reject(User $user, Request $request): RedirectResponse
    {
        if (! $user->isMarketer() && $user->role !== 'reseller') {
            return back()->with('error', 'User is not a marketer.');
        }

        $request->validate(['reason' => 'nullable|string|max:255']);

        $user->update(['marketer_status' => 'rejected']);

        AuditLog::log(
            'marketer.rejected',
            Auth::id(),
            User::class,
            $user->id,
            ['status' => 'pending'],
            ['status' => 'rejected', 'reason' => $request->reason]
        );

        NotificationService::notify(
            $user->id,
            'marketer_rejected',
            'Marketer Account Rejected',
            'Your marketer account request was not approved. '.($request->reason ? "Reason: {$request->reason}" : '')
        );

        return back()->with('success', "Marketer {$user->name} rejected.");
    }

    public function show($id): Response
    {
        $user = User::findOrFail($id);

        if (! $user->isMarketer() && $user->role !== 'reseller') {
            abort(404);
        }

        $user->load(['socialLinks', 'portfolios']);
        $user->loadCount(['contractApplications', 'contractProofSubmissions']);

        $recent_applications = ContractApplication::where('marketer_id', $user->id)
            ->with('contract.business')
            ->latest()
            ->limit(10)
            ->get();

        $stats = [
            'earnings' => Transaction::where('user_id', $user->id)
                ->where('type', 'contract_earning')->where('status', 'completed')->sum('amount'),
            'applications' => $user->contract_applications_count,
            'proofs' => $user->contract_proof_submissions_count,
        ];

        return Inertia::render('Admin/Marketers/Show', [
            'marketer' => $user,
            'stats' => $stats,
            'recent_applications' => $recent_applications,
        ]);
    }

    public function suspend(User $user): RedirectResponse
    {
        $user->update(['is_active' => false]);

        AuditLog::log('marketer.suspended', Auth::id(), User::class, $user->id);

        return back()->with('success', "Marketer {$user->name} suspended.");
    }

    public function demote(User $user): RedirectResponse
    {
        $user->update([
            'role' => 'user',
            'marketer_status' => 'approved', // Reset status but they are now just a user
        ]);

        AuditLog::log('marketer.demoted', Auth::id(), User::class, $user->id);

        return redirect()->route('admin.users.show', $user->id)->with('success', "Marketer {$user->name} demoted to regular user.");
    }

    public function terminate(User $user): RedirectResponse
    {
        $name = $user->name;
        $user->delete();

        AuditLog::log('marketer.terminated', Auth::id(), User::class, $user->id);

        return redirect()->route('admin.marketers.index')->with('success', "Marketer {$name} account terminated.");
    }

    public function moderatePortfolio(MarketerPortfolio $portfolio, ContentModerator $moderator): JsonResponse
    {
        $result = $moderator->reviewPortfolio($portfolio);

        if ($result === null) {
            return response()->json(['message' => 'AI moderator is not available or content looks clean.'], 503);
        }

        return response()->json($result);
    }

    public function resendEmailVerification(User $user): RedirectResponse
    {
        $user->sendEmailVerificationNotification();
        AuditLog::log('marketer.email_verification_resent', Auth::id(), User::class, $user->id);

        return back()->with('success', "Verification email has been resent to {$user->email}.");
    }

    public function manualVerifyEmail(User $user): RedirectResponse
    {
        $user->markEmailAsVerified();
        AuditLog::log('marketer.email_manually_verified', Auth::id(), User::class, $user->id);

        return back()->with('success', "Email address for {$user->name} has been manually verified.");
    }

    public function resendPhoneVerification(User $user): RedirectResponse
    {
        AuditLog::log('marketer.phone_verification_resent', Auth::id(), User::class, $user->id);

        return back()->with('success', "Phone verification request has been resent to {$user->phone}.");
    }
}
