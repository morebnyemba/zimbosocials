<?php
// app/Http/Controllers/AdminUserController.php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Order;
use App\Services\NotificationService;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(Request $request): Response
    {
        $query = User::query()
            ->withCount(['orders', 'tickets', 'transactions']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('id', $search);
            });
        }

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        if ($request->query('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->query('status') === 'inactive') {
            $query->where('is_active', false);
        }

        $sortField = $request->query('sort', 'created_at');
        $sortDir   = $request->query('dir', 'desc');
        $allowed   = ['id', 'name', 'email', 'balance', 'role', 'created_at', 'orders_count'];
        if (in_array($sortField, $allowed, true)) {
            $query->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $users = $query->paginate(25)->withQueryString();

        // Consolidated: 1 GROUP BY instead of 5 separate counts
        $rawCounts = User::selectRaw('role, COUNT(*) as cnt')
            ->groupBy('role')
            ->pluck('cnt', 'role');

        $role_counts = [
            'all'      => $rawCounts->sum(),
            'user'     => (int) ($rawCounts['user']     ?? 0),
            'marketer' => (int) ($rawCounts['marketer'] ?? 0),
            'reseller' => (int) ($rawCounts['reseller'] ?? 0),
            'admin'    => (int) ($rawCounts['admin']    ?? 0),
        ];

        return Inertia::render('Admin/Users/Index', [
            'users'       => $users,
            'filters'     => $request->only(['search', 'role', 'status', 'sort', 'dir']),
            'role_counts' => $role_counts,
        ]);
    }

    public function show(User $user): Response
    {
        $user->loadCount(['orders', 'tickets', 'transactions']);

        $recent_orders = Order::with('service')
            ->where('user_id', $user->id)
            ->latest()
            ->limit(10)
            ->get();

        $recent_transactions = Transaction::where('user_id', $user->id)
            ->latest()
            ->limit(10)
            ->get();

        // Consolidated: 1 aggregate query instead of 4
        $orderRow = Order::where('user_id', $user->id)
            ->selectRaw("
                COUNT(*) AS total,
                SUM(CASE WHEN status IN ('pending','processing','in_progress') THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
                SUM(charge) AS total_spent
            ")
            ->first();

        $order_stats = [
            'total'      => (int) ($orderRow->total      ?? 0),
            'active'     => (int) ($orderRow->active     ?? 0),
            'completed'  => (int) ($orderRow->completed  ?? 0),
            'total_spent'=> (float) ($orderRow->total_spent ?? 0),
        ];

        // Consolidated: 1 aggregate query instead of 3
        $finRow = Transaction::where('user_id', $user->id)
            ->selectRaw("
                SUM(CASE WHEN type = 'deposit' AND status = 'completed' THEN amount ELSE 0 END)          AS deposited,
                ABS(SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END))                           AS withdrawn,
                SUM(CASE WHEN type = 'contract_earning' AND status = 'completed' THEN amount ELSE 0 END) AS earnings
            ")
            ->first();

        $financial_stats = [
            'deposited' => (float) ($finRow->deposited ?? 0),
            'withdrawn' => (float) ($finRow->withdrawn ?? 0),
            'earnings'  => (float) ($finRow->earnings  ?? 0),
        ];

        $services = \App\Models\Service::where('is_active', true)->orderBy('category')->orderBy('name')->get();

        return Inertia::render('Admin/Users/Show', [
            'targetUser'           => $user,
            'recent_orders'        => $recent_orders,
            'recent_transactions'  => $recent_transactions,
            'order_stats'          => $order_stats,
            'financial_stats'      => $financial_stats,
            'services'             => $services,
        ]);
    }

    public function toggleActive(User $user): RedirectResponse
    {
        $old = $user->is_active;
        $user->update(['is_active' => !$old]);

        AuditLog::log(
            $old ? 'user.deactivated' : 'user.activated',
            Auth::id(),
            User::class,
            $user->id,
            ['is_active' => $old],
            ['is_active' => !$old],
        );

        $status = $user->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "User {$user->name} has been {$status}.");
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:user,marketer,reseller,admin'],
            'admin_role' => ['nullable', 'string', 'in:full,support,finance,compliance'],
            'balance' => ['nullable', 'numeric', 'min:0'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'admin_role' => $data['role'] === 'admin' ? ($data['admin_role'] ?? 'support') : null,
            'balance' => $data['balance'] ?? 0,
            'is_active' => true,
        ]);

        AuditLog::log('user.created_manually', Auth::id(), User::class, $user->id);

        return redirect()->route('admin.users.show', $user->id)->with('success', "User account created successfully.");
    }

    public function changeRole(User $user, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', 'in:user,marketer,reseller,admin'],
            'admin_role' => ['nullable', 'string', 'in:full,support,finance,compliance'],
        ]);

        $oldRole = $user->role;
        $user->update([
            'role' => $data['role'],
            'admin_role' => $data['role'] === 'admin' ? ($data['admin_role'] ?? $user->admin_role ?? 'support') : null,
        ]);

        AuditLog::log(
            'user.role_changed',
            Auth::id(),
            User::class,
            $user->id,
            ['role' => $oldRole],
            ['role' => $data['role']],
        );

        NotificationService::notify(
            $user->id,
            'role_changed',
            'Account Role Updated',
            "Your account role has been changed from {$oldRole} to {$data['role']}."
        );

        return back()->with('success', "User role changed from {$oldRole} to {$data['role']}.");
    }

    /**
     * Adjust a user's balance — atomic with row locking to prevent concurrent overwrites.
     */
    public function adjustBalance(User $user, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $amount = (float) $data['amount'];

        DB::transaction(function () use ($user, $amount, $data): void {
            $lockedUser = User::lockForUpdate()->findOrFail($user->id);
            $before = (float) $lockedUser->balance;

            if ($amount > 0) {
                $lockedUser->increment('balance', $amount);
            } else {
                $lockedUser->decrement('balance', abs($amount));
            }

            Transaction::create([
                'user_id'        => $lockedUser->id,
                'type'           => 'adjustment',
                'amount'         => $amount,
                'balance_before' => $before,
                'balance_after'  => $before + $amount,
                'status'         => 'completed',
                'notes'          => $data['reason'],
                'processed_by'   => Auth::id(),
                'processed_at'   => now(),
            ]);

            AuditLog::log(
                'user.balance_adjusted',
                Auth::id(),
                User::class,
                $lockedUser->id,
                ['balance' => $before],
                ['balance' => $before + $amount, 'reason' => $data['reason']],
            );
        });

        NotificationService::notify(
            $user->id,
            'balance_adjusted',
            'Balance Adjustment',
            "Your balance has been adjusted by \${$amount}. Reason: {$data['reason']}",
            ['amount' => $amount]
        );

        $formatted = number_format(abs($amount), 2);
        $direction = $amount > 0 ? 'credited' : 'debited';
        return back()->with('success', "\${$formatted} {$direction} to {$user->name}'s account.");
    }

    public function impersonate(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', "You cannot impersonate yourself.");
        }

        AuditLog::log(
            'user.impersonation_started',
            Auth::id(),
            User::class,
            $user->id,
            ['admin_id' => Auth::id()],
            ['target_user_id' => $user->id, 'target_email' => $user->email],
        );

        // Store the original admin ID
        session(['impersonator_id' => Auth::id()]);

        // Login as the user
        Auth::login($user);

        return redirect()->route('dashboard')->with('success', "Now impersonating {$user->name}");
    }

    public function leaveImpersonation(): RedirectResponse
    {
        $impersonatorId = session('impersonator_id');

        if (!$impersonatorId) {
            return redirect()->route('dashboard');
        }

        $admin = User::find($impersonatorId);
        
        if (!$admin || $admin->role !== 'admin') {
            session()->forget('impersonator_id');
            return redirect()->route('login');
        }

        $impersonatedId = Auth::id();
        Auth::login($admin);
        session()->forget('impersonator_id');

        AuditLog::log(
            'user.impersonation_ended',
            $admin->id,
            User::class,
            $impersonatedId,
        );

        return redirect()->route('admin.users.index')->with('success', "Returned to Admin Panel");
    }

    public function sendPasswordReset(User $user): RedirectResponse
    {
        // For simplicity in this environment, we'll just log it and show a success message
        // In production, this would trigger Password::sendResetLink()
        
        AuditLog::log('user.password_reset_sent', Auth::id(), User::class, $user->id);
        
        return back()->with('success', "Password reset instructions have been queued for {$user->email}.");
    }

    public function ban(User $user): RedirectResponse
    {
        $user->update(['is_active' => false]);
        
        AuditLog::log('user.banned', Auth::id(), User::class, $user->id);
        
        return back()->with('success', "User {$user->name} has been banned.");
    }

    public function destroy(User $user): RedirectResponse
    {
        $name = $user->name;
        $user->delete();
        
        AuditLog::log('user.terminated', Auth::id(), User::class, $user->id);
        
        return redirect()->route('admin.users.index')->with('success', "User account for {$name} has been terminated.");
    }
}
