<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppKnowledge;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppSession;
use App\WhatsApp\Messaging\WhatsAppGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin console for the WhatsApp assistant: conversation transcripts, human
 * agent takeover, and the knowledge base.
 */
class AdminWhatsAppController extends Controller
{
    private const HANDOFF_HOURS = 2;

    // ─── Conversations ────────────────────────────────────────────────────────

    public function conversations(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $accounts = WhatsAppAccount::query()
            ->with('user:id,name,email')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('wa_phone', 'like', "%{$search}%")
                ->orWhere('display_name', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))))
            ->orderByDesc('last_seen_at')
            ->paginate(30)
            ->withQueryString()
            ->through(function (WhatsAppAccount $a) {
                $last = WhatsAppMessage::where('wa_phone', $a->wa_phone)->latest('id')->first();

                return [
                    'id' => $a->id,
                    'wa_phone' => $a->wa_phone,
                    'display_name' => $a->display_name,
                    'link_status' => $a->link_status,
                    'user' => $a->user ? ['id' => $a->user->id, 'name' => $a->user->name, 'email' => $a->user->email] : null,
                    'opted_in' => $a->opted_in,
                    'in_handoff' => $a->inAgentHandoff(),
                    'last_seen_at' => $a->last_seen_at?->toIso8601String(),
                    'last_message' => $last ? mb_substr((string) $last->body, 0, 60) : null,
                    'last_direction' => $last?->direction,
                ];
            });

        return Inertia::render('Admin/WhatsApp/Conversations', [
            'conversations' => $accounts,
            'stats' => $this->stats(),
            'filters' => ['search' => $search],
        ]);
    }

    public function conversation(WhatsAppAccount $account): Response
    {
        $messages = WhatsAppMessage::where('wa_phone', $account->wa_phone)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->map(fn (WhatsAppMessage $m) => [
                'id' => $m->id,
                'direction' => $m->direction,
                'body' => $m->body,
                'msg_type' => $m->msg_type,
                'handled_by' => $m->handled_by,
                'intent' => $m->intent,
                'ai_used' => $m->ai_used,
                'created_at' => $m->created_at?->toIso8601String(),
            ])->values();

        $session = WhatsAppSession::where('wa_phone', $account->wa_phone)->first();

        return Inertia::render('Admin/WhatsApp/Conversation', [
            'account' => [
                'id' => $account->id,
                'wa_phone' => $account->wa_phone,
                'display_name' => $account->display_name,
                'link_status' => $account->link_status,
                'opted_in' => $account->opted_in,
                'in_handoff' => $account->inAgentHandoff(),
                'handoff_until' => $account->agent_handoff_until?->toIso8601String(),
                'user' => $account->user ? ['id' => $account->user->id, 'name' => $account->user->name, 'email' => $account->user->email] : null,
            ],
            'messages' => $messages,
            'session' => $session ? ['flow' => $session->current_flow, 'state' => $session->current_state, 'status' => $session->status] : null,
        ]);
    }

    /** Send a message as a human agent and pause the bot for a window. */
    public function reply(Request $request, WhatsAppAccount $account, WhatsAppGateway $gateway): RedirectResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:4000']]);

        $res = $gateway->sendText($account->wa_phone, $data['message']);
        if (empty($res['ok'])) {
            return back()->with('error', 'Failed to send: '.($res['error'] ?? 'unknown error'));
        }

        WhatsAppMessage::create([
            'wa_phone' => $account->wa_phone,
            'direction' => 'out',
            'wa_message_id' => $res['message_id'] ?? null,
            'msg_type' => 'text',
            'body' => $data['message'],
            'handled_by' => 'agent',
            'intent' => 'agent_reply',
        ]);

        // Keep the bot quiet while the agent is engaged.
        $account->update(['agent_handoff_until' => now()->addHours(self::HANDOFF_HOURS)]);

        return back()->with('success', 'Message sent.');
    }

    /** Toggle human handoff (pause/resume the bot for this number). */
    public function toggleHandoff(WhatsAppAccount $account): RedirectResponse
    {
        if ($account->inAgentHandoff()) {
            $account->update(['agent_handoff_until' => null]);

            return back()->with('success', 'Bot resumed for this conversation.');
        }

        $account->update(['agent_handoff_until' => now()->addHours(self::HANDOFF_HOURS)]);

        return back()->with('success', 'Bot paused — you are now handling this chat.');
    }

    public function resetSession(WhatsAppAccount $account): RedirectResponse
    {
        WhatsAppSession::where('wa_phone', $account->wa_phone)->delete();

        return back()->with('success', 'Conversation state reset.');
    }

    // ─── Knowledge base ───────────────────────────────────────────────────────

    public function knowledgeBase(): Response
    {
        return Inertia::render('Admin/WhatsApp/KnowledgeBase', [
            'entries' => WhatsAppKnowledge::orderByDesc('hits')->orderBy('title')->get(),
        ]);
    }

    public function storeKb(Request $request): RedirectResponse
    {
        WhatsAppKnowledge::create($this->validateKb($request));

        return back()->with('success', 'FAQ entry added.');
    }

    public function updateKb(Request $request, WhatsAppKnowledge $kb): RedirectResponse
    {
        $kb->update($this->validateKb($request));

        return back()->with('success', 'FAQ entry updated.');
    }

    public function destroyKb(WhatsAppKnowledge $kb): RedirectResponse
    {
        $kb->delete();

        return back()->with('success', 'FAQ entry deleted.');
    }

    private function validateKb(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'question' => ['required', 'string', 'max:2000'],
            'answer' => ['required', 'string', 'max:4000'],
            'keywords' => ['nullable', 'string', 'max:512'],
            'category' => ['nullable', 'string', 'max:64'],
            'status' => ['boolean'],
        ]);
    }

    /** @return array<string,int> */
    private function stats(): array
    {
        $today = now()->startOfDay();

        return [
            'conversations' => WhatsAppAccount::count(),
            'linked' => WhatsAppAccount::where('link_status', 'linked')->count(),
            'messages_today' => WhatsAppMessage::where('created_at', '>=', $today)->count(),
            'ai_today' => WhatsAppMessage::where('created_at', '>=', $today)->where('ai_used', true)->count(),
            'active_flows' => WhatsAppSession::whereNotNull('current_flow')->where('status', 'active')->count(),
            'in_handoff' => WhatsAppAccount::whereNotNull('agent_handoff_until')->where('agent_handoff_until', '>', now())->count(),
            'kb_entries' => WhatsAppKnowledge::where('status', true)->count(),
        ];
    }
}
