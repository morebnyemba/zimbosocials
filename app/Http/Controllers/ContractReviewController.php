<?php

namespace App\Http\Controllers;

use App\Models\BusinessContract;
use App\Models\ContractApplication;
use App\Models\MarketerReview;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContractReviewController extends Controller
{
    public function store(BusinessContract $contract, ContractApplication $application, Request $request): RedirectResponse
    {
        $user = Auth::user();
        $userId = (int) $user->getAuthIdentifier();

        // Only the business owner can leave a review
        if ((int) $contract->getAttribute('user_id') !== $userId) {
            abort(403);
        }

        // Application must belong to this contract
        if ((int) $application->getAttribute('business_contract_id') !== (int) $contract->getKey()) {
            abort(404);
        }

        // Only reviewable once the work is completed
        if ($application->getAttribute('status') !== 'completed') {
            return back()->with('error', 'You can only review a completed engagement.');
        }

        // One review per application
        if (MarketerReview::where('contract_application_id', $application->getKey())->exists()) {
            return back()->with('error', 'You have already reviewed this marketer for this contract.');
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        $marketerId = (int) $application->getAttribute('marketer_id');

        MarketerReview::create([
            'business_contract_id' => (int) $contract->getKey(),
            'contract_application_id' => (int) $application->getKey(),
            'reviewer_id' => $userId,
            'marketer_id' => $marketerId,
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
        ]);

        NotificationService::notify(
            $marketerId,
            'contract_review',
            'New Review Received',
            $user->name.' left you a '.$data['rating'].'-star review for: '.$contract->getAttribute('title'),
            ['contract_id' => (int) $contract->getKey()],
        );

        return back()->with('success', 'Review submitted — thank you for your feedback!');
    }
}
