<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\NotificationService;
use App\Models\Ticket;
use App\Models\TicketReply;
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

        $status_counts = [
            'all'     => Ticket::count(),
            'open'    => Ticket::where('status', 'open')->count(),
            'pending' => Ticket::where('status', 'pending')->count(),
            'closed'  => Ticket::where('status', 'closed')->count(),
        ];

        return Inertia::render('Admin/Tickets/Index', [
            'tickets'       => $tickets,
            'filters'       => $request->only(['search', 'status', 'priority']),
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

    public function reply(Ticket $ticket, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id'   => Auth::id(),
            'message'   => $data['message'],
            'is_admin'  => true,
        ]);

        $ticket->update([
            'status'       => 'pending',
            'last_reply_at'=> now(),
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
