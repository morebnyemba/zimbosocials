<?php

namespace App\WhatsApp\Routing;

use App\Models\WhatsAppAccount;
use App\WhatsApp\Flow\FlowEngine;
use App\WhatsApp\Flow\FlowResult;
use App\WhatsApp\Intent\IntentEngine;
use App\WhatsApp\Menu\MenuProvider;
use App\WhatsApp\Messaging\Responder;
use App\WhatsApp\Persistence\AccountStore;
use App\WhatsApp\Persistence\MessageStore;
use App\WhatsApp\Session\SessionContext;
use App\WhatsApp\Session\SessionManager;
use Illuminate\Support\Facades\Log;

/**
 * MessageRouter (Wave 2) — the deterministic dispatch ladder.
 *
 *   1. Resume / restart buttons (after a timeout)
 *   2. Typed universal command / data shortcut       [no AI]
 *   3. Timed-out active flow → offer resume/restart   [no AI]
 *   4. Any menu/button selection → navigate           [no AI]
 *   5. Active flow → advance with free text           [no AI]
 *   6. Fallback → menu (Wave 5 inserts rule → KB → AI here)
 *
 * Flows in Wave 2 collect input as free text (numbers), so every interactive
 * selection is treated as global navigation.
 */
class MessageRouter
{
    /** Flows a guest may start without authenticating. */
    private array $guestFlows = ['register', 'login', 'link', 'forgot', 'faq'];

    public function __construct(
        private readonly AccountStore $accounts,
        private readonly MessageStore $messages,
        private readonly Responder $responder,
        private readonly MenuProvider $menus,
        private readonly SessionManager $sessions,
        private readonly FlowEngine $engine,
        private readonly CommandRegistry $commands,
        private readonly RateLimiter $limiter,
        private readonly IntentEngine $intent,
    ) {}

    public function handle(array $msg, ?string $displayName = null): void
    {
        $inboundId = $this->messages->recordInbound($msg);
        if ($inboundId === 0) {
            return; // duplicate delivery
        }

        $phone = $msg['from'] ?? '';
        if ($phone === '') {
            return;
        }

        $this->responder->markRead($msg['wa_message_id'] ?? null);
        $this->responder->typing($msg['wa_message_id'] ?? null);

        // Flood control.
        $rl = $this->limiter->check($phone);
        if (! $rl['allowed']) {
            if ($rl['warn']) {
                $this->responder->send($phone, "⏳ You're sending messages too quickly. Please wait a moment.");
            }
            $this->messages->tagInbound($inboundId, ['handled_by' => 'system', 'intent' => 'rate_limited']);

            return;
        }

        $account = $this->accounts->resolveOrCreate($phone, $displayName ?? ($msg['name'] ?? null));

        // Agent handoff — a human is handling this chat; record but stay silent.
        if ($account->inAgentHandoff()) {
            $this->messages->tagInbound($inboundId, ['handled_by' => 'agent', 'intent' => 'handoff']);

            return;
        }

        $text = trim((string) ($msg['text'] ?? ''));

        // Opt-out gate.
        if (! $account->opted_in) {
            if (in_array(mb_strtolower($text), ['start', 'menu', 'hi', 'hello'], true)) {
                $this->accounts->setOptOut($phone, true);
                $account->opted_in = true;
            } else {
                $this->messages->tagInbound($inboundId, ['handled_by' => 'system', 'intent' => 'opted_out']);

                return;
            }
        }

        $ctx = $this->sessions->load($phone);
        if ($account->isLinked()) {
            $ctx->set('_user_id', $account->user_id);
        }

        $selection = $msg['interactive_id'] ?? null;

        try {
            $tag = $this->dispatch($ctx, $account, $text, $selection);
        } catch (\Throwable $e) {
            Log::error('WhatsApp dispatch error', ['message' => $e->getMessage()]);
            $ctx->resetFlow();
            $this->responder->send($phone, "⚠️ Something went wrong. Let's go back to the menu.");
            $this->sendMenuFor($account, $ctx);
            $tag = ['handled_by' => 'system', 'intent' => 'error'];
        }

        $this->sessions->save($ctx);
        $this->messages->tagInbound($inboundId, array_merge(['flow' => $ctx->flow], $tag));
    }

    private function dispatch(SessionContext $ctx, WhatsAppAccount $account, string $text, ?string $selection): array
    {
        $phone = $ctx->phone;

        // 0. Very first message from an unknown number: if it's just a greeting,
        //    welcome them and go straight into guided sign-up (name → email →
        //    account created automatically). A substantive first message ("I
        //    want 1000 followers") skips this — the normal ladder handles it,
        //    and the auth gate starts this same sign-up with their request
        //    remembered as _pending_flow.
        if ($account->wasRecentlyCreated && ! $account->isLinked() && $selection === null) {
            $greetings = ['', 'hi', 'hello', 'hey', 'hie', 'menu', 'start', 'hesi', 'mhoro', 'makadii', 'sawubona', 'salibonani'];
            if (in_array(mb_strtolower($text), $greetings, true)) {
                $name = $account->display_name ? " {$account->display_name}" : '';
                $this->responder->send(
                    $phone,
                    "👋 Hi{$name}! Welcome to ".config('app.name').' — followers, likes, views and more, right here on WhatsApp.'
                    ."\n\nLet me set up your account real quick (takes ~30 seconds), then you can order and track everything in this chat."
                    ."\n\n_Type *cancel* anytime to skip._",
                    ['handled_by' => 'system', 'intent' => 'first_contact']
                );
                $res = $this->engine->start($ctx, 'register');
                $this->emit($account, $ctx, $res);

                return ['handled_by' => 'system', 'intent' => 'first_contact_signup'];
            }
        }

        // 1. Resume / restart buttons.
        if ($selection === 'wa_resume') {
            $res = $this->engine->resume($ctx);
            $this->emit($account, $ctx, $res);

            return ['handled_by' => 'command', 'intent' => 'resume'];
        }
        if ($selection === 'wa_restart') {
            $this->engine->cancel($ctx);
            $this->sendMenuFor($account, $ctx);

            return ['handled_by' => 'command', 'intent' => 'restart'];
        }

        // 2. Typed keyword. Conversation controls (menu/cancel/stop/help) act
        //    instantly — never via AI. Data shortcuts ("balance", "deposit",
        //    "hi", ...) give the AI first crack so the user gets a warm,
        //    contextual reply; the mapped command is the deterministic
        //    fallback when AI is off or over budget.
        if ($selection === null) {
            $cmd = $this->commands->match($text);
            if ($cmd !== null) {
                if (! $this->commands->isControl($text)
                    && $this->consultAi($ctx, $account, $text, inFlow: $ctx->inFlow())
                ) {
                    return ['handled_by' => 'ai', 'intent' => $cmd, 'ai_used' => true];
                }

                $this->runCommand($cmd, $ctx, $account);

                return ['handled_by' => 'command', 'intent' => $cmd];
            }
        }

        // 3. Active flow that timed out → offer resume / restart.
        if ($ctx->wasExpired && $ctx->inFlow() && $selection === null && $text !== '') {
            $this->responder->sendButtons(
                $phone,
                "⏱️ You have an unfinished action. Resume where you left off?",
                [['id' => 'wa_resume', 'title' => 'Resume'], ['id' => 'wa_restart', 'title' => 'Main menu']],
                ['handled_by' => 'system']
            );

            return ['handled_by' => 'system', 'intent' => 'resume_offer'];
        }

        // 4a. Flow-internal selection (fs:<value>) — a tapped list row / button
        //     that belongs to the active flow. Feed the value to the flow as if
        //     the user had typed it. A stale tap (flow already ended) → menu.
        if ($selection !== null && str_starts_with($selection, 'fs:')) {
            if ($ctx->inFlow()) {
                $res = $this->engine->advance($ctx, substr($selection, 3));
                $this->emit($account, $ctx, $res);

                return ['handled_by' => 'flow', 'intent' => $selection];
            }

            // Tap on a button/list from an already-finished flow (e.g. a
            // double-tap on ✅ Place order). A quiet nudge beats dumping the
            // whole menu on them right after they completed something.
            $this->responder->send($ctx->phone, "That button has expired 👍 Just tell me what you'd like to do, or type *menu*.", ['handled_by' => 'system', 'intent' => 'stale_selection']);

            return ['handled_by' => 'system', 'intent' => 'stale_selection'];
        }

        // 4b. Any global selection navigates (cancel an active flow first).
        if ($selection !== null) {
            if ($ctx->inFlow()) {
                $this->engine->cancel($ctx);
            }
            $this->handleSelection($selection, $ctx, $account);

            return ['handled_by' => 'menu', 'intent' => $selection];
        }

        // 5. Active flow consumes free text. When the flow doesn't understand
        //    the input, the AI brain gets first crack at it — it can answer,
        //    adjust the current flow's data, or switch to another flow. The
        //    flow's own error text is only sent when the AI can't help.
        if ($ctx->inFlow() && $text !== '') {
            $res = $this->engine->advance($ctx, $text);

            if ($res->isRetry() && $this->consultAi($ctx, $account, $text, inFlow: true)) {
                return ['handled_by' => 'ai', 'intent' => 'flow_rescue', 'ai_used' => true];
            }

            $this->emit($account, $ctx, $res);

            return ['handled_by' => 'flow'];
        }

        // 6. Free text → AI brain. Gemini always replies and may trigger a flow;
        //    the knowledge base is used only as grounding context inside it.
        if ($text !== '' && $this->consultAi($ctx, $account, $text, inFlow: false)) {
            return ['handled_by' => 'ai', 'intent' => 'ai', 'ai_used' => true];
        }

        // 7. AI unavailable / over budget → menu.
        $this->sendMenuFor($account, $ctx);

        return ['handled_by' => 'menu', 'intent' => 'fallback'];
    }

    /**
     * Hand a message to the AI brain. It always replies and decides what runs
     * next: trigger a flow (with extracted data as prefills — flows fast-forward
     * to the right step), or just answer. Mid-flow, an answer-only outcome
     * re-renders the current step so the user isn't left stranded; outside a
     * flow it shows the menu. Returns false when AI is unavailable/over budget
     * so the caller can fall back deterministically.
     */
    private function consultAi(SessionContext $ctx, WhatsAppAccount $account, string $text, bool $inFlow): bool
    {
        $authenticated = $account->isLinked();
        $history = (array) $ctx->get('_ai_history', []);

        $r = $this->intent->resolve($text, $ctx->phone, [
            'user' => $authenticated ? $account->user : null,
            'authenticated' => $authenticated,
            'current_flow' => $ctx->flow,
            'current_state' => $ctx->state,
            'history' => $history,
        ]);

        if (empty($r['handled'])) {
            return false;
        }

        $reply = (string) ($r['reply'] ?? '');
        $flow = $r['flow'] ?? null;

        if ($reply !== '') {
            // Keep short-term memory (last 6 exchanges) for follow-ups.
            $history[] = ['user' => $text, 'model' => $reply];
            $ctx->set('_ai_history', array_slice($history, -6));
            $this->responder->send($ctx->phone, $reply, [
                'handled_by' => 'ai', 'ai_used' => true, 'intent' => $flow ?? 'ai',
                // Full decision, kept for offline accuracy analysis (ai-eval).
                'payload' => [
                    'flow' => $flow,
                    'flow_data' => $r['flow_data'] ?? [],
                    'prompt_version' => $r['prompt_version'] ?? null,
                    'in_flow' => $inFlow ? $ctx->flow : null,
                ],
            ]);
        }

        // The model escalated to a human: pause the bot for this chat (same
        // window as the admin takeover button) and alert the team.
        if ($flow === 'handoff') {
            if ($ctx->inFlow()) {
                $this->engine->cancel($ctx);
            }
            $this->accounts->startAgentHandoff($ctx->phone);
            \App\Services\NotificationService::notifyAdmins(
                'admin_whatsapp_handoff',
                'WhatsApp chat needs a human',
                "The assistant escalated +{$ctx->phone} to a human agent. Reply from Admin → WA Assistant → Conversations.",
                ['wa_phone' => $ctx->phone]
            );

            return true;
        }

        // Optional AI follow-up nudge, sent as a second message.
        if (! empty($r['follow_up'])) {
            $this->responder->send($ctx->phone, (string) $r['follow_up'], ['handled_by' => 'ai', 'ai_used' => true, 'intent' => 'follow_up']);
        }

        $flowData = array_filter((array) ($r['flow_data'] ?? []), fn ($v) => $v !== null && $v !== '');

        // The model sometimes re-names the active flow for what is really just
        // a side answer. Only a *different* flow or *new data* warrants a
        // (re)start — otherwise re-render the step so it doesn't look like the
        // flow keeps calling itself.
        if ($flow !== null && ($flow !== $ctx->flow || $flowData !== [])) {
            foreach ($flowData as $k => $v) {
                $ctx->set('_prefill_'.$k, $v);
            }
            $this->startFlow($flow, $ctx, $account);

            return true;
        }

        if ($inFlow && $ctx->inFlow()) {
            // Answered a side-question mid-flow → re-render the step they were on.
            $this->emit($account, $ctx, $this->engine->resume($ctx));
        }
        // Outside a flow the AI reply stands on its own — no menu chaser. The
        // menu still appears via 'menu', after flows finish, and as the
        // fallback when the AI is unavailable.

        return true;
    }

    private function runCommand(string $cmd, SessionContext $ctx, WhatsAppAccount $account): void
    {
        $phone = $ctx->phone;

        switch ($cmd) {
            case 'menu':
                $this->engine->cancel($ctx);
                $this->sendMenuFor($account, $ctx);

                return;
            case 'help':
                // The help text already lists every command — no menu on top.
                $this->responder->send($phone, $this->helpText());

                return;
            case 'back':
            case 'cancel':
                $wasInFlow = $ctx->inFlow();
                $this->engine->cancel($ctx);
                $this->responder->send($phone, $wasInFlow ? '✖ Cancelled.' : 'Nothing to cancel.');
                $this->sendMenuFor($account, $ctx);

                return;
            case 'stop':
                $this->accounts->setOptOut($phone, false);
                $this->responder->send($phone, "🔕 You've been unsubscribed. Send *menu* anytime to come back.");

                return;
        }

        // Auth entry-points and data shortcuts map to flows.
        $flowMap = [
            'register' => 'register', 'login' => 'link', 'link' => 'link', 'forgot' => 'forgot',
            'order' => 'order',
            'balance' => 'balance', 'orders' => 'my_orders', 'services' => 'browse',
            'support' => 'ticket', 'tickets' => 'tickets', 'deposit' => 'deposit',
            'track' => 'track', 'profile' => 'profile', 'history' => 'history',
            'search' => 'search', 'faq' => 'faq', 'settings' => 'settings',
            'ask_ai' => 'ask_ai',
        ];
        $flowId = $flowMap[$cmd] ?? null;
        if ($flowId !== null) {
            $this->startFlow($flowId, $ctx, $account);

            return;
        }

        $this->sendMenuFor($account, $ctx);
    }

    private function handleSelection(string $selection, SessionContext $ctx, WhatsAppAccount $account): void
    {
        if ($selection === 'menu' || $selection === 'menu_home') {
            $this->sendMenuFor($account, $ctx);

            return;
        }
        if ($selection === 'guest_learn') {
            $this->responder->send($ctx->phone, $this->learnMoreText());
            $this->sendMenuFor($account, $ctx);

            return;
        }

        $flowId = MenuProvider::$actionFlow[$selection] ?? null;
        if ($flowId === null) {
            $this->sendMenuFor($account, $ctx);

            return;
        }

        $this->startFlow($flowId, $ctx, $account);
    }

    private function startFlow(string $flowId, SessionContext $ctx, WhatsAppAccount $account): void
    {
        $isGuestFlow = in_array($flowId, $this->guestFlows, true);

        if (! $isGuestFlow && ! $account->isLinked()) {
            // Auth required for a guest → start guided sign-up, remembering the
            // action they wanted so it can be resumed after registration.
            $ctx->set('_pending_flow', $flowId);
            $res = $this->engine->start($ctx, 'register');
            $this->emit($account, $ctx, $res);

            return;
        }

        if (! $this->engine->canStart($flowId)) {
            $label = ucwords(str_replace('_', ' ', $flowId));
            $this->responder->send($ctx->phone, "🛠️ *{$label}* is coming in the next update. Type *menu* to go back.");

            return;
        }

        $res = $this->engine->start($ctx, $flowId);
        $this->emit($account, $ctx, $res);
    }

    private function emit(WhatsAppAccount $account, SessionContext $ctx, FlowResult $res): void
    {
        $meta = ['flow' => $ctx->flow, 'handled_by' => 'flow'];

        if ($res->buttons !== null && $res->reply !== null) {
            $this->responder->sendButtons($ctx->phone, $res->reply, $res->buttons, $meta);
        } elseif ($res->list !== null) {
            $this->responder->sendMenu($ctx->phone, [
                'body' => (string) $res->reply,
                'button' => $res->list['button'],
                'sections' => $res->list['sections'],
                'header' => $res->list['header'] ?? null,
                'footer' => $res->list['footer'] ?? null,
            ], $meta);
        } elseif ($res->reply !== null && $res->reply !== '') {
            $this->responder->send($ctx->phone, $res->reply, $meta);
        }

        if (! $res->isDone()) {
            return; // still mid-flow, awaiting input
        }

        // If the user just authenticated, resume the action they originally
        // wanted (e.g. asked for "balance" as a guest → registered → balance).
        $pending = $ctx->get('_pending_flow');
        if ($pending !== null) {
            $ctx->forget('_pending_flow');
            $account->refresh();
            if ($account->isLinked()
                && ! in_array($pending, ['register', 'link', 'forgot'], true)
                && $this->engine->canStart($pending)
            ) {
                $ctx->set('_user_id', $account->user_id);
                $this->emit($account, $ctx, $this->engine->start($ctx, $pending));

                return;
            }
        }

        // A finished flow's closing message stands on its own — no menu chaser.
        // The menu appears when asked for ('menu' / a tap) and as the fallback
        // when the AI can't handle free text.
    }

    private function sendMenuFor(WhatsAppAccount $account, SessionContext $ctx): void
    {
        $account->refresh();

        if ($account->isLinked()) {
            $user = $account->user;
            $balanceText = $user ? number_format((float) $user->balance, 2).' '.($user->currency ?? 'USD') : null;
            $this->responder->sendMenu($ctx->phone, $this->menus->mainMenu($account->display_name, $balanceText));
        } else {
            $this->responder->sendMenu($ctx->phone, $this->menus->guestMenu());
        }
    }

    private function helpText(): string
    {
        return "🤖 *How to use this assistant*\n\n"
            ."Tap a menu option, or type a command:\n"
            ."*menu* – main menu\n"
            ."*balance* – your wallet\n"
            ."*orders* – your orders\n"
            ."*track* – track an order\n"
            ."*services* – browse services\n"
            ."*history* – transactions\n"
            ."*cancel* – stop the current step\n\n"
            .'Type *menu* at any time.';
    }

    private function learnMoreText(): string
    {
        return "ℹ️ We provide social media marketing services — followers, likes, views and more, "
            ."delivered fast. Register or link your account to browse services, place orders and "
            .'track delivery right here on WhatsApp.';
    }
}
