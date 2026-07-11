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
