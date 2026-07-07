<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Services\AI\SupportTicketAssistant;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AdminTicketController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Ticket::with('user:id,name,email')
            ->withCount('replies');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('id', $search)
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }

        $tickets = $query->latest('last_reply_at')->latest()->paginate(25)->withQueryString();

        // Consolidated: 1 GROUP BY instead of 4 separate counts
        $rawCounts = Ticket::selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $status_counts = [
            'all' => $rawCounts->sum(),
            'open' => (int) ($rawCounts['open'] ?? 0),
            'pending' => (int) ($rawCounts['pending'] ?? 0),
            'closed' => (int) ($rawCounts['closed'] ?? 0),
        ];

        return Inertia::render('Admin/Tickets/Index', [
            'tickets' => $tickets,
            'filters' => $request->only(['search', 'status', 'priority']),
            'status_counts' => $status_counts,
        ]);
    }

    public function show(Ticket $ticket): Response
    {
        $ticket->load(['user', 'replies.user']);

        return Inertia::render('Admin/Tickets/Show', [
            'ticket' => $ticket,
        ]);
    }

    /**
     * Generate a reply draft built around the admin's own talking points —
     * the AI is required to know WHAT the admin wants to say before it
     * writes anything.
     */
    public function draftReply(Ticket $ticket, Request $request, SupportTicketAssistant $assistant): JsonResponse
    {
        if ($ticket->status === 'closed') {
            return response()->json(['draft' => null, 'message' => 'Ticket is closed.'], 422);
        }

        $data = $request->validate([
            'intent' => ['required', 'string', 'min:5', 'max:2000'],
            'tone' => ['nullable', 'string', 'max:200'],
        ]);

        $draft = $assistant->draftReply($ticket, $data['intent'], $request->input('tone'));

        if ($draft === null) {
            return response()->json(['draft' => null, 'message' => 'AI assistant is not available.'], 503);
        }

        return response()->json(['draft' => $draft]);
    }

    /**
     * Polish the reply the admin already typed (grammar/clarity/tone) without
     * changing any facts or commitments.
     */
    public function enhanceReply(Ticket $ticket, Request $request, SupportTicketAssistant $assistant): JsonResponse
    {
        if ($ticket->status === 'closed') {
            return response()->json(['draft' => null, 'message' => 'Ticket is closed.'], 422);
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'min:5', 'max:5000'],
            'tone' => ['nullable', 'string', 'max:200'],
        ]);

        $improved = $assistant->enhanceReply($ticket, $data['message'], $request->input('tone'));

        if ($improved === null) {
            return response()->json(['draft' => null, 'message' => 'AI assistant is not available.'], 503);
        }

        return response()->json(['draft' => $improved]);
    }

    /** Quick AI catch-up on a long thread. */
    public function summarizeThread(Ticket $ticket, SupportTicketAssistant $assistant): JsonResponse
    {
        $summary = $assistant->summarizeThread($ticket);

        if ($summary === null) {
            return response()->json(['summary' => null, 'message' => 'AI assistant is not available.'], 503);
        }

        return response()->json(['summary' => $summary]);
    }

    public function reply(Ticket $ticket, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'message' => $data['message'],
            'is_admin' => true,
        ]);

        $ticket->update([
            'status' => 'pending',
            'last_reply_at' => now(),
        ]);

        NotificationService::notify($ticket->user_id, 'ticket_reply', 'Ticket Reply',
            "Admin replied to your ticket: \"{$ticket->subject}\"",
            ['ticket_id' => $ticket->id]);

        return back()->with('success', 'Reply sent.');
    }

    public function close(Ticket $ticket): RedirectResponse
    {
        $ticket->update(['status' => 'closed']);

        NotificationService::notify($ticket->user_id, 'ticket_closed', 'Ticket Closed',
            "Your ticket \"{$ticket->subject}\" has been closed.",
            ['ticket_id' => $ticket->id]);

        return back()->with('success', 'Ticket closed.');
    }
}
