<?php
// app/Http/Controllers/TicketController.php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function index(): Response
    {
        $tickets = Ticket::where('user_id', Auth::id())
            ->orderByDesc('updated_at')
            ->paginate(15);

        return Inertia::render('Tickets/Index', ['tickets' => $tickets]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        Ticket::create([
            'user_id'  => Auth::id(),
            'subject'  => $data['subject'],
            'message'  => $data['message'],
            'status'   => 'open',
            'priority' => 'medium',
        ]);

        return back()->with('success', __('messages.ticket_submitted'));
    }

    public function show(Ticket $ticket): Response
    {
        $this->authorize('view', $ticket);
        $ticket->load('replies.user');

        return Inertia::render('Tickets/Show', ['ticket' => $ticket]);
    }

    public function reply(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('view', $ticket);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id'   => Auth::id(),
            'message'   => $data['message'],
            'is_admin'  => Auth::user()->isAdmin(),
        ]);

        $ticket->update([
            'status'        => 'pending',
            'last_reply_at' => now(),
        ]);

        return back()->with('success', __('messages.reply'));
    }
}
