<?php

namespace App\Http\Controllers;

use App\Models\TranslationSuggestion;
use App\Services\TranslationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TranslationContributionController extends Controller
{
    public function __construct(private TranslationService $translations)
    {
    }

    /** Public contribution editor: browse strings and propose better translations. */
    public function index(Request $request)
    {
        $locale = $request->query('locale', $request->user()->locale ?? 'sn');
        if (! $this->translations->isEditableLocale($locale)) {
            $locale = 'sn';
        }

        $english = $this->translations->editableKeys();
        $current = $this->translations->messages($locale);

        // Strings the user already has in review, keyed for quick lookup in the UI.
        $myPending = TranslationSuggestion::where('user_id', Auth::id())
            ->where('locale', $locale)
            ->where('status', 'pending')
            ->pluck('value', 'key');

        $strings = collect($english)->map(fn ($source, $key) => [
            'key' => $key,
            'source' => $source,                      // English reference
            'current' => $current[$key] ?? $source,   // live value in target locale
            'pending' => $myPending[$key] ?? null,     // this user's pending proposal, if any
        ])->values();

        return Inertia::render('Translations/Index', [
            'locales' => TranslationService::LOCALES,
            'activeLocale' => $locale,
            'strings' => $strings,
            'mySuggestions' => TranslationSuggestion::where('user_id', Auth::id())
                ->latest()
                ->limit(50)
                ->get(['id', 'locale', 'key', 'value', 'status', 'review_note', 'created_at']),
        ]);
    }

    /** Submit (or replace) a pending suggestion for a single key. */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'locale' => ['required', Rule::in(TranslationService::LOCALES)],
            'key' => ['required', 'string', 'max:191'],
            'value' => ['required', 'string', 'max:2000'],
        ]);

        // Only allow keys that actually exist in the source catalogue.
        abort_unless(array_key_exists($validated['key'], $this->translations->editableKeys()), 422, 'Unknown translation key.');

        $current = $this->translations->messages($validated['locale']);
        $original = $current[$validated['key']] ?? null;

        // No-op if the proposal matches the live value.
        if (trim($validated['value']) === trim((string) $original)) {
            return back()->with('info', __('messages.translation_no_change'));
        }

        // One pending proposal per user/locale/key — update in place if it exists.
        TranslationSuggestion::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'locale' => $validated['locale'],
                'key' => $validated['key'],
                'status' => 'pending',
            ],
            [
                'value' => $validated['value'],
                'original_value' => $original,
            ]
        );

        return back()->with('success', __('messages.translation_submitted'));
    }
}
