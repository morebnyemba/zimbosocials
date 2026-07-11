<?php

namespace App\WhatsApp\Flow\Definitions;

use App\WhatsApp\Flow\AbstractFlow;
use App\WhatsApp\Flow\FlowResult;
use App\WhatsApp\Session\SessionContext;
use Illuminate\Support\Facades\DB;

/**
 * Frequently asked questions. Flow id: 'faq'. Lists the knowledge-base entries
 * when present, otherwise a built-in starter set. Guests may use it.
 */
class FaqFlow extends AbstractFlow
{
    public function authRequired(): bool
    {
        return false;
    }

    public function id(): string
    {
        return 'faq';
    }

    public function prompt(string $state, SessionContext $ctx): FlowResult
    {
        $entries = DB::table('whatsapp_knowledge_base')
            ->where('status', true)
            ->orderByDesc('hits')
            ->limit(6)
            ->get(['title', 'answer']);

        if ($entries->isNotEmpty()) {
            $body = $entries->map(fn ($e) => "*{$e->title}*\n{$e->answer}")->implode("\n\n");

            return FlowResult::complete("❓ *FAQ*\n\n{$body}\n\nStill stuck? Type *support* to reach our team.");
        }

        // Built-in fallback until the knowledge base is populated (Wave 6 admin).
        $msg = "❓ *Frequently asked questions*\n\n"
            ."*How do I place an order?*\nType *order*, pick a service, paste your link and choose a quantity.\n\n"
            ."*How do I add funds?*\nType *deposit* and follow the link to top up your wallet.\n\n"
            ."*Is my password safe?*\nWe never ask for your password here. Linking uses a one-time email code.\n\n"
            ."*How fast is delivery?*\nMost orders start within minutes; times vary by service.\n\n"
            .'Type *support* to reach our team, or just ask me a question.';

        return FlowResult::complete($msg);
    }

    public function handle(string $state, string $input, SessionContext $ctx): FlowResult
    {
        return $this->prompt($state, $ctx);
    }
}
