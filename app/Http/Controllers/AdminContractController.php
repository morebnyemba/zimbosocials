<?php

namespace App\Http\Controllers;

use App\Models\BusinessContract;
use App\Models\ContractApplication;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\AuditLog;

class AdminContractController extends Controller
{
    public function index(Request $request): Response
    {
        $query = BusinessContract::with(['business:id,name,email,company_name'])
            ->withCount(['applications']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('business', fn($bq) => $bq->where('name', 'like', "%{$search}%")->orWhere('company_name', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $contracts = $query->latest()->paginate(25)->withQueryString();

        // Consolidated: 1 GROUP BY instead of 4 separate counts
        $rawCounts = BusinessContract::selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $status_counts = [
            'all'    => $rawCounts->sum(),
            'open'   => (int) ($rawCounts['open']   ?? 0),
            'filled' => (int) ($rawCounts['filled'] ?? 0),
            'closed' => (int) ($rawCounts['closed'] ?? 0),
        ];

        return Inertia::render('Admin/Contracts/Index', [
            'contracts'     => $contracts,
            'filters'       => $request->only(['search', 'status']),
            'status_counts' => $status_counts,
        ]);
    }

    public function show(BusinessContract $contract): Response
    {
        $contract->load(['business', 'applications.marketer.socialLinks', 'applications.decider']);

        return Inertia::render('Admin/Contracts/Show', [
            'contract' => $contract,
        ]);
    }

    public function destroy(BusinessContract $contract): RedirectResponse
    {
        $contract->delete();

        AuditLog::log(
            'contract.deleted_by_admin',
            Auth::id(),
            BusinessContract::class,
            $contract->id
        );

        return redirect()->route('admin.contracts.index')->with('success', 'Contract deleted successfully.');
    }
}
