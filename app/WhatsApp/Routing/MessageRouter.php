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
use Illuminate\Support\Facades\Cache;
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
        private readonly \App\WhatsApp\Deposit\ProofIntake $proof,
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

        // Serialize processing per phone: session persistence is last-write-wins,
        // and a slow AI call widens the load→save race window when the user
        // double-sends. If the lock can't be acquired in time (previous message
        // stuck on a slow upstream), proceed unlocked — losing a session write
        // beats silently dropping the user's message.
        $lock = Cache::lock('wa:sess-lock:'.$phone, 20);
        $locked = false;
        try {
            $locked = $lock->block(8);
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            Log::warning('WhatsApp session lock timeout — processing unlocked', ['phone' => $phone]);
        }

        try {
            $ctx = $this->sessions->load($phone);
            if ($account->isLinked()) {
                $ctx->set('_user_id', $account->user_id);
            }

            $selection = $msg['interactive_id'] ?? null;

            // Ad-origin signal for this message; also remembered for the AI's
            // first-contact intro while the account remains fresh.
            $ad = $msg['ad_referral'] ?? null;
            if ($ad !== null && ! empty($ad['headline'])) {
                $ctx->set('_ad_headline', mb_substr((string) $ad['headline'], 0, 120));
            }

            try {
                $media = $msg['media'] ?? null;
                if (is_array($media) && ! empty($media['id'])) {
                    $tag = $this->handleMedia($account, $ctx, $media);
                } else {
                    $tag = $this->dispatch($ctx, $account, $text, $selection, $ad);
                }
            } catch (\Throwable $e) {
                Log::error('WhatsApp dispatch error', ['message' => $e->getMessage()]);
                $ctx->resetFlow();
                $this->responder->send($phone, "⚠️ Oops, something went wrong on my end. Tell me what you'd like to do and we'll try again — or type *menu* for options.", ['handled_by' => 'system', 'intent' => 'error']);
                $tag = ['handled_by' => 'system', 'intent' => 'error'];
            }

            $this->sessions->save($ctx);
            $this->messages->tagInbound($inboundId, array_merge(['flow' => $ctx->flow], $tag));
        } finally {
            if ($locked) {
                $lock->release();
            }
        }
    }

    private function dispatch(SessionContext $ctx, WhatsAppAccount $account, string $text, ?string $selection, ?array $ad = null): array
    {
        $phone = $ctx->phone;

        // 0. Very first message from an unknown number. Greetings — and the
        //    generic canned texts click-to-WhatsApp ads produce ("Hi! Can I
        //    get more info about this?") — get a platform introduction and an
        //    invitation to say what they want. No signup gate: the account is
        //    auto-created silently the moment they take an action. Substantive
        //    first messages skip this — the AI handles them with a
        //    first-contact intro (see consultAi).
        if ($account->wasRecentlyCreated && ! $account->isLinked() && $selection === null) {
            $greetings = ['', 'hi', 'hello', 'hey', 'hie', 'menu', 'start', 'hesi', 'mhoro', 'makadii', 'sawubona', 'salibonani'];
            $fromAd = $ad !== null || $this->looksLikeAdCta($text);

            if (in_array(mb_strtolower($text), $greetings, true) || ($fromAd && ! $this->mentionsAProduct($text))) {
                $name = $account->display_name ? " {$account->display_name}" : '';
                $site = \App\WhatsApp\AI\GeminiProvider::siteName();
                $intro = $fromAd
                    ? "👋 Hi{$name}, thanks for reaching out! You've found *{$site}* — we grow social media accounts: followers, likes, views and more, plus *sponsored adverts* that put your business in front of new customers. Delivered fast and paid with EcoCash and other local methods, all right here on WhatsApp."
                    : "👋 Hi{$name}! Welcome to *{$site}* — followers, likes, views, and *sponsored adverts* to reach new customers, right here on WhatsApp.";

                // Lower the barrier up front: asking costs nothing, and they can
                // do it in whichever of our languages they're most comfortable in.
                $openDoor = "\n\n💬 Ask me anything — chatting and advice are *free*, and I'm happy to help in *English*, *Shona* or *Ndebele*.";

                // No signup hurdle: the account is created silently the moment
                // they take an action — so go straight to business.
                $this->responder->send(
                    $phone,
                    $intro.$openDoor."\n\nSo — what would you like to do today? Grow your page (e.g. *\"1000 Instagram followers\"*), run a *sponsored advert* to find customers, or type *menu* to browse. 🚀",
                    ['handled_by' => 'system', 'intent' => $fromAd ? 'first_contact_ad' : 'first_contact']
                );

                return ['handled_by' => 'system', 'intent' => 'first_contact_intro'];
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

            // Tap on a button/list whose flow has ended (or a tap we couldn't
            // route). Never dump the menu — re-render if mid-flow, else nudge.
            $this->handleStuck($account, $ctx);

            return ['handled_by' => 'system', 'intent' => 'stale_selection'];
        }

        // 4b. Global navigation selection (menu tap, fl_* action). Only a KNOWN
        //     nav target cancels the active flow and navigates; an unknown or
        //     stale selection must NOT dump the main menu — re-render the step
        //     or nudge instead.
        if ($selection !== null) {
            $isNav = isset(MenuProvider::$actionFlow[$selection])
                || in_array($selection, ['menu', 'menu_home', 'guest_learn'], true);

            if (! $isNav) {
                // The id wasn't one of ours (some payloads deliver the button's
                // title as the id). Try to map it — by the raw value or its
                // title form — back to the option we sent before giving up.
                if ($tag = $this->routeMappedOption($ctx, $account, $selection, $text)) {
                    return $tag;
                }

                $this->handleStuck($account, $ctx);

                return ['handled_by' => 'system', 'intent' => 'unknown_selection'];
            }

            if ($ctx->inFlow()) {
                $this->engine->cancel($ctx);
            }
            $this->handleSelection($selection, $ctx, $account);

            return ['handled_by' => 'menu', 'intent' => $selection];
        }

        // 4c. Tap that arrived as its plain TITLE (no interactive id). Some
        //     WhatsApp payloads/clients deliver a button/list tap as the option
        //     label text instead of its id — which would otherwise fall through
        //     as stray free text, fail the flow, and bounce off the AI (slow +
        //     wrong). Map the title back to the id we sent and route it as a
        //     real tap so buttons always advance the flow.
        if ($selection === null && $text !== '') {
            if ($tag = $this->routeMappedOption($ctx, $account, $text)) {
                return $tag;
            }
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

        // 7. AI unavailable / over budget / nothing matched → feedback, never
        //    the main menu (that only appears on an explicit *menu*).
        $this->handleStuck($account, $ctx);

        return ['handled_by' => 'menu', 'intent' => 'fallback'];
    }

    /** The canned texts Meta's click-to-WhatsApp ad CTAs pre-fill. */
    private function looksLikeAdCta(string $text): bool
    {
        $t = mb_strtolower(preg_replace('/[^a-z ]/i', '', $text));

        foreach (['interested', 'more info', 'tell me more', 'get more info', 'saw your ad', 'about this'] as $phrase) {
            if (str_contains($t, $phrase)) {
                return true;
            }
        }

        return false;
    }

    /** Whether the text already names a platform/product — a real ask, not a canned CTA. */
    private function mentionsAProduct(string $text): bool
    {
        return (bool) preg_match(
            '/instagram|tiktok|youtube|facebook|twitter|telegram|whatsapp|spotify|follow|like|view|subscriber|comment|share/i',
            $text
        );
    }

    /**
     * Hand a message to the AI brain — ONE VOICE: the AI both decides and
     * speaks. When it triggers a flow, its reply is NOT sent separately; the
     * flow runs silently and the AI's draft is fused with the step's facts
     * into a single message (money steps stay verbatim). Answer-only turns
     * send just the answer — the AI steers back to any pending step itself.
     * Returns false when AI is unavailable/over budget so the caller can fall
     * back deterministically.
     */
    private function consultAi(SessionContext $ctx, WhatsAppAccount $account, string $text, bool $inFlow): bool
    {
        $authenticated = $account->isLinked();
        $history = (array) $ctx->get('_ai_history', []);
        $nudgeAllowed = $authenticated && \App\WhatsApp\ReferralNudge::allowed($ctx->phone);

        $r = $this->intent->resolve($text, $ctx->phone, [
            'user' => $authenticated ? $account->user : null,
            'authenticated' => $authenticated,
            'current_flow' => $ctx->flow,
            'current_state' => $ctx->state,
            'history' => $history,
            'referral_nudge_allowed' => $nudgeAllowed,
            // First-ever message (often straight off an ad): the model opens
            // with a one-line introduction of itself and the platform.
            'first_contact' => $account->wasRecentlyCreated && ! $authenticated,
            'ad_headline' => $ctx->get('_ad_headline'),
        ]);

        if (empty($r['handled'])) {
            return false;
        }

        $reply = (string) ($r['reply'] ?? '');
        $flow = $r['flow'] ?? null;
        $flowData = array_filter((array) ($r['flow_data'] ?? []), fn ($v) => $v !== null && $v !== '');

        // Only a *different* flow or *new data* warrants a (re)start — the
        // model sometimes re-names the active flow for a plain side answer.
        $willStartFlow = $flow !== null && $flow !== 'handoff'
            && ($flow !== $ctx->flow || $flowData !== []);

        // Full decision, kept for offline accuracy analysis (ai-eval).
        $decision = [
            'flow' => $flow,
            'flow_data' => $r['flow_data'] ?? [],
            'prompt_version' => $r['prompt_version'] ?? null,
            'in_flow' => $inFlow ? $ctx->flow : null,
        ];

        if ($reply !== '') {
            // Keep short-term memory (last 6 exchanges) for follow-ups.
            $history[] = ['user' => $text, 'model' => $reply];
            $ctx->set('_ai_history', array_slice($history, -6));
        }

        // Standalone reply ONLY when no flow will speak — one voice, never two.
        if (! $willStartFlow && $reply !== '') {
            $this->responder->send($ctx->phone, $reply, [
                'handled_by' => 'ai', 'ai_used' => true, 'intent' => $flow ?? 'ai',
                'payload' => $decision,
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

        // Optional AI follow-up nudge — answer-only turns; a flow turn already
        // ends in the step's question, a nudge on top would be a second voice.
        if (! $willStartFlow && ! empty($r['follow_up'])) {
            $this->responder->send($ctx->phone, (string) $r['follow_up'], ['handled_by' => 'ai', 'ai_used' => true, 'intent' => 'follow_up']);
        }

        // The model spent its one allowed referral mention — start the cooldown
        // so the program isn't plugged again for a while on any surface.
        if ($nudgeAllowed && $reply !== ''
            && (mb_stripos($reply.' '.($r['follow_up'] ?? ''), 'refer') !== false || mb_stripos($reply, 'invite') !== false)
        ) {
            \App\WhatsApp\ReferralNudge::mark($ctx->phone);
        }

        if ($willStartFlow) {
            foreach ($flowData as $k => $v) {
                $ctx->set('_prefill_'.$k, $v);
            }
            $this->startFlow($flow, $ctx, $account, voiceDraft: $reply, decisionMeta: $decision, userText: $text);
        }
        // Answer-only mid-flow: the step is NOT re-blasted — the prompt makes
        // the AI steer back to the pending question in its own words, and the
        // flow state is untouched so their next input still lands on the step.

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
                $this->responder->send($phone, $wasInFlow
                    ? "✖ Cancelled 👍 What would you like to do next? (or type *menu* for options)"
                    : "All good — what can I help you with? Type *menu* to see your options.");

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
            'ask_ai' => 'ask_ai', 'referral' => 'referral',
        ];
        $flowId = $flowMap[$cmd] ?? null;
        if ($flowId !== null) {
            $this->startFlow($flowId, $ctx, $account);

            return;
        }

        $this->handleStuck($account, $ctx);
    }

    /**
     * An inbound image/PDF — treat it as proof of payment for a pending manual
     * deposit (the in-chat version of the wallet's proof upload). If there's no
     * deposit waiting on proof, or the file isn't usable, guide them warmly.
     */
    private function handleMedia(WhatsAppAccount $account, SessionContext $ctx, array $media): array
    {
        $account->refresh();

        if (! $account->isLinked() || ! $account->user) {
            $this->responder->send($ctx->phone,
                "Thanks for the file! 📎 If you're sending proof of a deposit, reply *deposit* first, choose a *manual* method, pay, then send the screenshot here.",
                ['handled_by' => 'system', 'intent' => 'media_no_user']
            );

            return ['handled_by' => 'system', 'intent' => 'media_no_user'];
        }

        $res = $this->proof->intake($account->user, $media);

        if (! empty($res['ok'])) {
            $tx = $res['transaction'];
            $cur = $account->user->currency ?? 'USD';
            $amount = number_format((float) abs($tx->amount), 2);
            $bonus = app(\App\Services\DepositService::class)->manualDepositBonusPercent();
            $bonusLine = $bonus > 0
                ? " You'll also get your *+".rtrim(rtrim(number_format($bonus, 2), '0'), '.').'% bonus* once it clears.'
                : '';

            $this->responder->send($ctx->phone,
                "✅ *Proof received!* Thanks — I've attached it to your *{$amount} {$cur}* deposit. Our team will verify and credit your wallet shortly.{$bonusLine} 🙌",
                ['handled_by' => 'system', 'intent' => 'proof_received']
            );

            return ['handled_by' => 'system', 'intent' => 'proof_received'];
        }

        $message = match ($res['reason'] ?? '') {
            'no_pending' => "Thanks for the file! 📎 I couldn't find a manual deposit waiting on proof. To top up, reply *deposit*, pick a *manual* method, pay, then send your screenshot here.",
            'bad_type' => "I can only read a *photo* or *PDF* of your payment. Please resend it as an image (JPG/PNG) or PDF. 📸",
            'too_large' => "That file's a bit large for me (max 5MB). Please send a smaller *photo* of your payment confirmation. 📸",
            default => "Hmm, I couldn't open that file 🤔 Please resend a clear *photo* of your payment confirmation, or upload it at ".url('/wallet').".",
        };

        $this->responder->send($ctx->phone, $message, ['handled_by' => 'system', 'intent' => 'proof_'.($res['reason'] ?? 'error')]);

        return ['handled_by' => 'system', 'intent' => 'proof_'.($res['reason'] ?? 'error')];
    }

    /**
     * Nothing actionable matched — but NEVER dump the main menu here (it appears
     * only on an explicit *menu*). If they're mid-flow, re-render the step so a
     * mis-registered tap or stray message doesn't derail them; otherwise a
     * short, warm nudge that invites them to say what they want.
     */
    private function handleStuck(WhatsAppAccount $account, SessionContext $ctx): void
    {
        if ($ctx->inFlow()) {
            $this->emit($account, $ctx, $this->engine->resume($ctx));

            return;
        }

        $this->responder->send(
            $ctx->phone,
            "I didn't quite catch that 🤔 Just tell me what you'd like — an *order*, a *deposit*, your *balance*, or ask me anything about growing your socials. Type *menu* to see all options.",
            ['handled_by' => 'system', 'intent' => 'stuck']
        );
    }

    private function handleSelection(string $selection, SessionContext $ctx, WhatsAppAccount $account): void
    {
        if ($selection === 'menu' || $selection === 'menu_home') {
            $this->sendMenuFor($account, $ctx);

            return;
        }
        if ($selection === 'guest_learn') {
            $this->responder->send($ctx->phone, $this->learnMoreText());

            return;
        }

        $flowId = MenuProvider::$actionFlow[$selection] ?? null;
        if ($flowId === null) {
            $this->handleStuck($account, $ctx);

            return;
        }

        $this->startFlow($flowId, $ctx, $account);
    }

    private function startFlow(string $flowId, SessionContext $ctx, WhatsAppAccount $account, ?string $voiceDraft = null, array $decisionMeta = [], string $userText = ''): void
    {
        $isGuestFlow = in_array($flowId, $this->guestFlows, true);

        if (! $isGuestFlow && ! $account->isLinked()) {
            // Silent auto-registration: create a real account in the background
            // ({phone}@auto-domain, random password, no questions) and proceed
            // straight into what they asked for — the conversation IS the
            // onboarding. They can attach a real email later via register/link.
            $auto = app(\App\WhatsApp\Auth\WhatsAppRegistrar::class)->autoRegister($ctx->phone, $account->display_name);

            if (! empty($auto['ok'])) {
                $account->refresh();
                $ctx->set('_user_id', $account->user_id);
            } else {
                // Couldn't auto-create (rare) → fall back to guided sign-up,
                // remembering the action so it resumes after registration.
                $ctx->set('_pending_flow', $flowId);
                $res = $this->engine->start($ctx, 'register');
                $this->emit($account, $ctx, $res, $voiceDraft, $decisionMeta, $userText);

                return;
            }
        }

        if (! $this->engine->canStart($flowId)) {
            $label = ucwords(str_replace('_', ' ', $flowId));
            $this->responder->send($ctx->phone, "🛠️ *{$label}* is coming in the next update. Type *menu* to go back.");

            return;
        }

        $res = $this->engine->start($ctx, $flowId);
        $this->emit($account, $ctx, $res, $voiceDraft, $decisionMeta, $userText);
    }

    /**
     * Money-critical steps whose wording must stay verbatim: confirmation
     * summaries (exact amounts) and OTP entry. Everything else may be voiced.
     */
    private function isMoneyStep(FlowResult $res, SessionContext $ctx): bool
    {
        if (in_array($ctx->state, ['confirm', 'ask_otp'], true)) {
            return true;
        }
        foreach ((array) ($res->buttons ?? []) as $button) {
            if (($button['id'] ?? '') === 'fs:yes') {
                return true;
            }
        }

        return false;
    }

    private function emit(WhatsAppAccount $account, SessionContext $ctx, FlowResult $res, ?string $voiceDraft = null, array $decisionMeta = [], string $userText = ''): void
    {
        $meta = ['flow' => $ctx->flow, 'handled_by' => 'flow'];
        $body = $res->reply;

        // ONE VOICE: this flow output is the consequence of an AI decision —
        // Simbah speaks it. Fuse the AI's draft with the step's facts into a
        // single message. Money steps and terminal results stay verbatim (the
        // factual card/summary IS the message); on any voice failure the
        // scripted text sends unchanged — never both.
        if ($voiceDraft !== null) {
            $meta = ['flow' => $ctx->flow, 'handled_by' => 'ai', 'ai_used' => true, 'payload' => $decisionMeta];

            if (! $res->isDone() && ! $this->isMoneyStep($res, $ctx) && $body !== null && $body !== '') {
                $voiced = $this->intent->voice($voiceDraft, (string) $body, $userText);
                if ($voiced !== null) {
                    $body = $voiced;
                }
            }
        }

        if ($res->buttons !== null && $body !== null) {
            $this->responder->sendButtons($ctx->phone, $body, $res->buttons, $meta);
        } elseif ($res->list !== null) {
            $this->responder->sendMenu($ctx->phone, [
                'body' => (string) $body,
                'button' => $res->list['button'],
                'sections' => $res->list['sections'],
                'header' => $res->list['header'] ?? null,
                'footer' => $res->list['footer'] ?? null,
            ], $meta);
        } elseif ($body !== null && $body !== '') {
            $this->responder->send($ctx->phone, $body, $meta);
        }

        // Remember this step's tappable options (title → id). Some WhatsApp
        // payloads deliver a button/list tap as the option's plain TITLE with no
        // interactive id; the dispatcher uses this map to route such a tap back
        // to the flow instead of treating it as stray free text. See dispatch().
        $this->rememberOptions($ctx, $res);

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

    /**
     * Resolve a tap that didn't arrive as one of our option ids back to the
     * option we actually sent, and route it. Handles two malformed-payload
     * shapes: a tap delivered as the option's plain title (no id), and a tap
     * whose id is the title itself. Candidates are tried in order, each matched
     * against the remembered option map by raw value and by title form.
     * Returns the dispatch tag when routed, or null when nothing matched.
     */
    private function routeMappedOption(SessionContext $ctx, WhatsAppAccount $account, ?string ...$candidates): ?array
    {
        $map = (array) $ctx->get('_option_map', []);
        if ($map === []) {
            return null;
        }

        $mapped = null;
        foreach ($candidates as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }
            $mapped = $map[$candidate] ?? $map[$this->optionKey($candidate)] ?? null;
            if ($mapped !== null) {
                break;
            }
        }

        if ($mapped === null) {
            return null;
        }

        if (str_starts_with($mapped, 'fs:') && $ctx->inFlow()) {
            $this->emit($account, $ctx, $this->engine->advance($ctx, substr($mapped, 3)));

            return ['handled_by' => 'flow', 'intent' => 'title_tap'];
        }

        if (isset(MenuProvider::$actionFlow[$mapped])
            || in_array($mapped, ['menu', 'menu_home', 'guest_learn'], true)
        ) {
            if ($ctx->inFlow()) {
                $this->engine->cancel($ctx);
            }
            $this->handleSelection($mapped, $ctx, $account);

            return ['handled_by' => 'menu', 'intent' => 'title_tap'];
        }

        return null;
    }

    /**
     * Stash the current step's tappable options as title → id, so a tap that
     * arrives as its plain title (no interactive id) can still be routed to the
     * flow. Cleared to nothing when a step has no options.
     */
    private function rememberOptions(SessionContext $ctx, FlowResult $res): void
    {
        $map = [];

        foreach ((array) ($res->buttons ?? []) as $b) {
            if (isset($b['title'], $b['id'])) {
                $map[$this->optionKey((string) $b['title'])] = (string) $b['id'];
            }
        }

        foreach ((array) ($res->list['sections'] ?? []) as $section) {
            foreach ((array) ($section['rows'] ?? []) as $row) {
                if (isset($row['title'], $row['id'])) {
                    $map[$this->optionKey((string) $row['title'])] = (string) $row['id'];
                }
            }
        }

        if ($map !== []) {
            $ctx->set('_option_map', $map);
        } else {
            $ctx->forget('_option_map');
        }
    }

    /**
     * Normalise a button/row title for matching: drop emojis and punctuation,
     * lower-case, collapse spaces. So "✅ Place order" and a typed "place order"
     * both key to "place order".
     */
    private function optionKey(string $title): string
    {
        $stripped = preg_replace('/[^\p{L}\p{N} ]+/u', ' ', $title) ?? $title;

        return trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($stripped)));
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
