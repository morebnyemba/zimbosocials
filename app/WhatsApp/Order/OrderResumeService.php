<?php

namespace App\WhatsApp\Order;

use App\Models\Service;
use App\Models\User;
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
        $this->sessions->save($ctx);
        self::clear($userId);

        // Only push if it actually landed on confirm with funds ready.
        if ($ctx->flow !== 'order' || $ctx->state !== 'confirm') {
            return;
        }

        $lead = "✅ *You're topped up!* Let's finish your order.\n\n";
        $this->emitConfirm($phone, $lead, $res);
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

    private static function key(int $userId): string
    {
        return 'wa:resume_order:'.$userId;
    }
}
