<?php

namespace App\Http\Controllers;

use App\Models\TranslationOverride;
use App\Models\TranslationSuggestion;
use App\Services\TranslationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AdminTranslationController extends Controller
{
    public function __construct(private TranslationService $translations)
    {
    }

    /** Review queue + recently approved overrides. */
    public function index(Request $request)
    {
        $pending = TranslationSuggestion::pending()
            ->with('user:id,name,email')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Translations/Index', [
            'pending' => $pending,
            'pendingCount' => TranslationSuggestion::pending()->count(),
            'overridesCount' => TranslationOverride::count(),
        ]);
    }

    /** Approve a suggestion: persist the override and make it live. */
    public function approve(Request $request, TranslationSuggestion $suggestion)
    {
        abort_unless($suggestion->status === 'pending', 422);

        TranslationOverride::updateOrCreate(
            ['locale' => $suggestion->locale, 'key' => $suggestion->key],
            ['value' => $suggestion->value, 'updated_by' => Auth::id()],
        );

        $suggestion->update([
            'status' => 'approved',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        // Supersede any other pending proposals for the same string.
        TranslationSuggestion::pending()
            ->where('locale', $suggestion->locale)
            ->where('key', $suggestion->key)
            ->where('id', '!=', $suggestion->id)
            ->update([
                'status' => 'rejected',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'review_note' => __('messages.translation_superseded'),
            ]);

        $this->translations->flush($suggestion->locale);

        return back()->with('success', __('messages.translation_approved'));
    }

    /** Reject a suggestion with an optional note. */
    public function reject(Request $request, TranslationSuggestion $suggestion)
    {
        abort_unless($suggestion->status === 'pending', 422);

        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $suggestion->update([
            'status' => 'rejected',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'review_note' => $validated['review_note'] ?? null,
        ]);

        return back()->with('success', __('messages.translation_rejected'));
    }
}
