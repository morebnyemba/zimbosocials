<?php

namespace App\WhatsApp\Flow;

use App\WhatsApp\Session\SessionContext;

/**
 * Drives flows: starting them, feeding input, and applying each FlowResult back
 * onto the SessionContext (advance state, or reset the flow when it ends).
 */
class FlowEngine
{
    public function __construct(private readonly FlowRegistry $registry) {}

    public function canStart(string $flowId): bool
    {
        return $this->registry->has($flowId);
    }

    public function get(string $flowId): ?FlowInterface
    {
        return $this->registry->get($flowId);
    }

    public function start(SessionContext $ctx, string $flowId): FlowResult
    {
        $flow = $this->registry->get($flowId);
        if (! $flow) {
            $ctx->resetFlow();

            return FlowResult::fail("🛠️ That option isn't available yet.");
        }

        $ctx->setFlow($flowId, $flow->entryState());
        $res = $flow->prompt($flow->entryState(), $ctx);
        $this->apply($ctx, $res);

        return $res;
    }

    public function advance(SessionContext $ctx, string $input): FlowResult
    {
        $flow = $ctx->flow ? $this->registry->get($ctx->flow) : null;
        if (! $flow) {
            $ctx->resetFlow();

            return FlowResult::fail("Let's start again — type *menu*.");
        }

        $res = $flow->handle($ctx->state ?? $flow->entryState(), $input, $ctx);
        $this->apply($ctx, $res);

        return $res;
    }

    /** Re-render the current state (used after a timeout resume). */
    public function resume(SessionContext $ctx): FlowResult
    {
        $flow = $ctx->flow ? $this->registry->get($ctx->flow) : null;
        if (! $flow) {
            $ctx->resetFlow();

            return FlowResult::fail("Nothing to resume — type *menu*.");
        }
        $ctx->wasExpired = false;

        return $flow->prompt($ctx->state ?? $flow->entryState(), $ctx);
    }

    public function cancel(SessionContext $ctx): void
    {
        $ctx->resetFlow();
    }

    private function apply(SessionContext $ctx, FlowResult $res): void
    {
        if ($res->isDone()) {
            $ctx->resetFlow();
        } elseif ($res->nextState !== null) {
            $ctx->setState($res->nextState);
        }
    }
}
