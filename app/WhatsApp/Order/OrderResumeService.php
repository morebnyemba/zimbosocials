<?php

namespace App\WhatsApp\Order;

use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\WhatsApp\Flow\FlowEngine;
use App\WhatsApp\Flow\FlowResult;
use App\WhatsApp\Messaging\Responder;
use App\WhatsApp\Persistence\AccountStore;
use App\WhatsApp\Session\SessionContext;
use App\WhatsApp\Session\SessionManager;
use Illuminate\Support\Facades\Cache;

/**
 * "Finish your order after you top up." When a WhatsApp order stalls at confirm
 * for lack of funds, the intent (service/link/quantity) is stashed durably.
 * DepositService::credit calls resumeAfterDeposit() from whatever request
 * confirms the payment (webhook / poll / admin) — there is no live chat session
 * there, so this rebuilds one: it re-enters the order flow at the confirm step
 * with funds now available and pushes a proactive "ready to place?" message.
 *
 * Money-safe: it resumes TO the confirm step, never past it — the order still
 * only places on the user's explicit *Place order* / *yes*.
 */
class OrderResumeService
{
    /** Stash lives longer than a session (deposits can confirm hours later). */
    private const TTL_HOURS = 24;

    public function __construct(
        private readonly SessionManager $sessions,
        private readonly FlowEngine $engine,
        private readonly Responder $responder,
        private readonly AccountStore $accounts,
    ) {}

    /** Remember an order that couldn't be placed yet (called at confirm when short). */
    public static function stash(int $userId, string $phone, int $serviceId, string $link, int $quantity): void
    {
        Cache::put(self::key($userId), compact('phone', 'serviceId', 'link', 'quantity'), now()->addHours(self::TTL_HOURS));
    }

    public static function clear(int $userId): void
    {
        Cache::forget(self::key($userId));
    }

    /**
     * A deposit for this user just confirmed — if they have a stashed order and
     * can now afford it, re-open it at the confirm step and nudge them.
     */
    public function resumeAfterDeposit(int $userId): void
    {
        $stash = Cache::get(self::key($userId));
        if (! is_array($stash)) {
            return;
        }

        $user = User::find($userId);
        $service = Service::active()->find($stash['serviceId'] ?? 0);
        if (! $user || ! $service) {
            self::clear($userId);

            return;
        }

        $qty = (int) ($stash['quantity'] ?? 0);
        $charge = $service->calculateCharge($qty);

        // Still short (they topped up less than needed) → keep the stash so the
        // next deposit can finish it; don't nag now.
        if ((float) $user->balance < $charge) {
            return;
        }

        $phone = (string) ($stash['phone'] ?? '');
        if ($phone === '') {
            self::clear($userId);

            return;
        }

        // Rebuild a session at the order/confirm step with the stashed details.
        $ctx = $this->sessions->load($phone);
        $ctx->set('_user_id', $userId);
        $ctx->set('_prefill_service_id', $service->id);
        $ctx->set('_prefill_link', $stash['link'] ?? '');
        $ctx->set('_prefill_quantity', $qty);

        $res = $this->engine->start($ctx, 'order');

        // Remember the confirm buttons as title → id, so a tap that arrives as
        // its plain label ("Place order") still routes to the flow even when the
        // WhatsApp payload omits the interactive id (see MessageRouter dispatch).
        $this->rememberOptions($ctx, $res);

        $this->sessions->save($ctx);
        self::clear($userId);

        // Only push if it actually landed on confirm with funds ready.
        if ($ctx->flow !== 'order' || $ctx->state !== 'confirm') {
            return;
        }

        $lead = "✅ *You're topped up!* Let's finish your order.\n\n";
        $this->emitConfirm($phone, $lead, $res);
    }

    /**
     * A deposit just CONFIRMED — tell the WhatsApp user right away. If an order
     * was waiting on it, resume that (its own message); otherwise a plain,
     * immediate confirmation with the new balance.
     */
    public function afterDepositCredited(Transaction $tx): void
    {
        $userId = (int) $tx->user_id;

        if (Cache::get(self::key($userId)) !== null) {
            $this->resumeAfterDeposit($userId);

            // If the top-up still wasn't enough, the stash is kept and no resume
            // fired — fall through to a plain confirmation so they still hear back.
            if (Cache::get(self::key($userId)) === null) {
                return;
            }
        }

        $phone = $this->resolvePhone($userId);
        if ($phone === null) {
            return;
        }

        $user = User::find($userId);
        $cur = $user?->currency ?? 'USD';
        $bal = number_format((float) ($user?->balance ?? 0), 2);
        $amount = number_format((float) abs($tx->amount), 2);

        $this->responder->send(
            $phone,
            "✅ *Deposit confirmed!* Your *{$amount} {$cur}* is in — your balance is now *{$bal} {$cur}*. 🎉\n\nReady when you are — just tell me what you'd like to grow!",
            ['handled_by' => 'system', 'intent' => 'deposit_confirmed']
        );
    }

    /**
     * A deposit FAILED or expired — tell the WhatsApp user immediately what
     * happened, that no money was taken, and how to retry. Never leaves them
     * guessing (the old behaviour: a silent status change + the menu).
     */
    public function afterDepositFailed(Transaction $tx, bool $expired = false): void
    {
        $phone = $this->resolvePhone((int) $tx->user_id);
        if ($phone === null) {
            return;
        }

        $user = User::find($tx->user_id);
        $cur = $user?->currency ?? 'USD';
        $amount = number_format((float) abs($tx->amount), 2);
        $method = $tx->method ? ucfirst((string) $tx->method) : 'payment';

        $reason = $expired
            ? "we didn't receive the payment in time"
            : "the payment was declined or failed (often not enough funds)";

        $msg = "❌ Your *{$amount} {$cur}* {$method} top-up didn't go through — {$reason}. *No money was taken.* 🙏\n\n"
            ."Want to try again? Reply *deposit* to retry or pick another method (EcoCash, OneMoney, InnBucks, OMari).";

        // If an order was waiting on this top-up, reassure them it's still saved.
        if (Cache::get(self::key((int) $tx->user_id)) !== null) {
            $msg .= "\n\nYour order is still saved — top up and I'll finish it for you. 👍";
        }

        $this->responder->send($phone, $msg, ['handled_by' => 'system', 'intent' => 'deposit_failed']);
    }

    /** The WhatsApp number linked to this user, if they use the assistant. */
    private function resolvePhone(int $userId): ?string
    {
        $phone = (string) (WhatsAppAccount::where('user_id', $userId)->value('wa_phone') ?? '');

        return $phone !== '' ? $phone : null;
    }

    /** Send the confirm card verbatim (money step — never model-rendered), buttons attached. */
    private function emitConfirm(string $phone, string $lead, FlowResult $res): void
    {
        $body = $lead.((string) $res->reply);
        $meta = ['handled_by' => 'system', 'intent' => 'order_resume'];

        if ($res->buttons !== null) {
            $this->responder->sendButtons($phone, $body, $res->buttons, $meta);
        } else {
            $this->responder->send($phone, $body, $meta);
        }
    }

    /**
     * Stash the step's tappable options as title → id on the session, matching
     * MessageRouter's fallback so a title-only tap can be routed to the flow.
     */
    private function rememberOptions(SessionContext $ctx, FlowResult $res): void
    {
        $map = [];
        foreach ((array) ($res->buttons ?? []) as $b) {
            if (isset($b['title'], $b['id'])) {
                $map[$this->optionKey((string) $b['title'])] = (string) $b['id'];
            }
        }

        if ($map !== []) {
            $ctx->set('_option_map', $map);
        }
    }

    /** Same normalisation as MessageRouter::optionKey so keys line up. */
    private function optionKey(string $title): string
    {
        $stripped = preg_replace('/[^\p{L}\p{N} ]+/u', ' ', $title) ?? $title;

        return trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($stripped)));
    }

    private static function key(int $userId): string
    {
        return 'wa:resume_order:'.$userId;
    }
}
