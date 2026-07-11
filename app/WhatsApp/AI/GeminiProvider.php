<?php

namespace App\WhatsApp\AI;

use App\Services\AI\GeminiClient;

/**
 * Wraps the app's GeminiClient for the assistant: classifies a free-text
 * message into an intent (+ entities) so the router can start the right flow,
 * and answers general questions about the service.
 */
class GeminiProvider
{
    /** Intents the classifier may return (mapped to flows by IntentEngine). */
    private const INTENTS = [
        'order', 'balance', 'my_orders', 'track', 'browse', 'deposit',
        'history', 'ticket', 'tickets', 'profile', 'faq', 'question', 'none',
    ];

    public function __construct(private readonly GeminiClient $client) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * Primary orchestration: decide what to do with the user's message. The AI
     * may start any flow (with extracted params), answer directly, or run a
     * universal command. It never confirms money on the user's behalf — flows
     * still require the confirm step.
     *
     * @param  array{authenticated:bool, current_flow:?string}  $context
     * @return array{action:string, flow:?string, command:?string, reply:?string, entities:array}|null
     */
    public function plan(string $text, array $context): ?array
    {
        $catalog = FlowCatalog::prompt();
        $auth = $context['authenticated'] ? 'authenticated' : 'a guest (only register/link/forgot/faq flows allowed)';
        $current = $context['current_flow'] ? "They are currently in the '{$context['current_flow']}' flow." : 'They are not in any flow.';

        $prompt = <<<PROMPT
        You are the orchestrating brain of a WhatsApp assistant for a social media
        marketing (SMM) panel. Decide the single best next action for the user's message.

        The user is {$auth}. {$current}

        Available flows you can start:
        {$catalog}

        Reply ONLY with JSON:
        {"action": "flow" | "answer" | "command",
         "flow": "<flow id or null>",
         "command": "menu" | "cancel" | "help" | null,
         "reply": "<short friendly message to send, or null>",
         "entities": {"platform": null, "service": null, "quantity": null, "link": null, "order_id": null, "amount": null, "email": null, "name": null, "subject": null, "message": null}}

        Rules:
        - Prefer "flow" whenever the user wants to DO something the panel supports; fill entities you can extract (leave others null). Do NOT invent order numbers, amounts or links.
        - Use "answer" for questions/greetings/smalltalk; put the reply in "reply" (max 3 sentences, warm, concise).
        - Use "command" only for clear navigation ("go to menu", "cancel", "help").
        - Never place orders or move money yourself — starting the 'order'/'deposit' flow is enough; the flow will ask the user to confirm.

        User message: "{$text}"
        PROMPT;

        $json = $this->client->generateJson($prompt, 0.2);
        if (! is_array($json) || empty($json['action'])) {
            return null;
        }

        $action = in_array($json['action'], ['flow', 'answer', 'command'], true) ? $json['action'] : 'answer';
        $flow = $json['flow'] ?? null;
        if ($flow !== null && ! array_key_exists($flow, FlowCatalog::all())) {
            $flow = null;
            $action = $action === 'flow' ? 'answer' : $action;
        }

        return [
            'action' => $action,
            'flow' => $flow,
            'command' => in_array($json['command'] ?? null, ['menu', 'cancel', 'help'], true) ? $json['command'] : null,
            'reply' => isset($json['reply']) && $json['reply'] !== '' ? (string) $json['reply'] : null,
            'entities' => is_array($json['entities'] ?? null) ? array_filter($json['entities'], fn ($v) => $v !== null && $v !== '') : [],
        ];
    }

    /**
     * @return array{intent:string, reply:?string, entities:array}|null
     */
    public function classify(string $text): ?array
    {
        $intents = implode(', ', self::INTENTS);
        $prompt = <<<PROMPT
        You are the assistant for a social media marketing (SMM) panel on WhatsApp.
        Customers buy followers, likes, views etc., top up a wallet, and track orders.

        Classify the user's message and reply ONLY with JSON of this shape:
        {"intent": "<one of: {$intents}>", "reply": "<short friendly reply, max 2 sentences>", "entities": {"service": "<platform/service if named>", "quantity": <number or null>, "order_id": <number or null>, "amount": <number or null>}}

        Rules:
        - "order" if they want to buy/place an order. "deposit" to add funds. "balance" to check wallet.
        - "my_orders" to list orders. "track" to track a specific order (capture order_id).
        - "browse" to see services. "history" for transactions. "ticket" to get help, "tickets" to view tickets.
        - "profile" for account details. "faq"/"question" for general questions — put the answer in "reply".
        - "none" if it's smalltalk/greeting. Keep "reply" warm and concise. Do not invent order data.

        User message: "{$text}"
        PROMPT;

        $json = $this->client->generateJson($prompt, 0.2);
        if (! is_array($json) || empty($json['intent'])) {
            return null;
        }

        $intent = in_array($json['intent'], self::INTENTS, true) ? $json['intent'] : 'none';

        return [
            'intent' => $intent,
            'reply' => isset($json['reply']) ? (string) $json['reply'] : null,
            'entities' => is_array($json['entities'] ?? null) ? $json['entities'] : [],
        ];
    }

    /** Free-form answer to a general question. */
    public function answer(string $text): ?string
    {
        $prompt = "You are a helpful WhatsApp assistant for a social media marketing panel "
            ."(followers, likes, views; wallet top-ups; order tracking). Answer the user's "
            ."question briefly and warmly in at most 3 sentences. If it's outside the service, "
            ."politely steer back. Question: \"{$text}\"";

        return $this->client->generateText($prompt, 0.6);
    }
}
