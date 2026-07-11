<?php

namespace App\WhatsApp\Flow\Definitions;

use App\WhatsApp\AI\AIGuard;
use App\WhatsApp\AI\GeminiProvider;
use App\WhatsApp\Flow\AbstractFlow;
use App\WhatsApp\Flow\FlowResult;
use App\WhatsApp\Messaging\ResponseBuilder;
use App\WhatsApp\Session\SessionContext;

/**
 * Free-form Q&A powered by Gemini. Flow id: 'ask_ai'.
 * States: ask (prompt) → answer (respond, stay open for follow-ups).
 */
class AskAiFlow extends AbstractFlow
{
    public function __construct(
        ResponseBuilder $rb,
        private readonly GeminiProvider $ai,
        private readonly AIGuard $guard,
    ) {
        parent::__construct($rb);
    }

    public function authRequired(): bool
    {
        return false;
    }

    public function id(): string
    {
        return 'ask_ai';
    }

    public function prompt(string $state, SessionContext $ctx): FlowResult
    {
        if (! $this->ai->isConfigured()) {
            return FlowResult::fail("🤖 AI answers aren't available right now. Type *support* to reach our team.");
        }

        return FlowResult::step("🤖 Ask me anything about our services, your orders or your wallet. (Type *menu* to exit.)", 'answer');
    }

    public function handle(string $state, string $input, SessionContext $ctx): FlowResult
    {
        if (! $this->guard->allow($ctx->phone)) {
            return FlowResult::fail("You've reached today's AI limit. Type *support* to reach our team.");
        }
        $this->guard->record($ctx->phone);

        $answer = $this->ai->answer(trim($input));
        if (! $answer) {
            return FlowResult::fail("🤖 I couldn't find an answer just now. Type *support* to reach our team.");
        }

        // Stay in the flow so the user can keep asking follow-ups.
        return FlowResult::step($answer."\n\n_Ask another question, or type *menu* to exit._", 'answer');
    }
}
