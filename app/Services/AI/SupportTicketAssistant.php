<?php

namespace App\Services\AI;

use App\Models\Ticket;

/**
 * Uses Gemini to draft a professional support reply from the ticket thread and
 * customer context. The draft is always a suggestion; the admin must review and
 * send it.
 */
class SupportTicketAssistant
{
    public function __construct(private readonly GeminiClient $client) {}

    public function isAvailable(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * Draft a reply for the given ticket.
     */
    public function draftReply(Ticket $ticket, ?string $tone = null): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $tone ??= 'professional, empathetic, concise';

        return $this->client->generateText($this->buildPrompt($ticket, $tone), 0.3);
    }

    private function buildPrompt(Ticket $ticket, string $tone): string
    {
        $subject = $ticket->subject;
        $message = $ticket->message;
        $customer = $ticket->user;
        $customerName = $customer?->name ?? 'Customer';
        $locale = $customer?->locale ?? 'en';

        $replies = $ticket->replies()->with('user')->get()->map(function ($reply) {
            $author = $reply->is_admin ? 'Admin' : ($reply->user?->name ?? 'Customer');

            return [
                'author' => $author,
                'message' => $reply->message,
            ];
        })->toArray();

        $orders = $customer?->orders()
            ->with('service:id,name,category')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'service' => $order->service?->name ?? 'Unknown service',
                    'category' => $order->service?->category ?? '',
                    'status' => $order->status,
                    'quantity' => $order->quantity,
                    'link' => $order->link,
                ];
            })->toArray() ?? [];

        $context = json_encode([
            'customer_name' => $customerName,
            'customer_locale' => $locale,
            'subject' => $subject,
            'original_message' => $message,
            'previous_replies' => $replies,
            'recent_orders' => $orders,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
            You are a senior support agent for Zimbo Socials, a Zimbabwean social-media marketing (SMM) platform.
            Write a single reply to the customer in English.

            Tone: {$tone}

            Guidelines:
            - Address the customer by name when appropriate.
            - Be empathetic, clear, and concise.
            - Reference relevant recent orders only if they help answer the question.
            - Never make up guarantees, delivery times, refunds, or policies.
            - If the issue needs escalation, say so politely.
            - Do not include a signature; the system adds it automatically.

            Context:
            {$context}

            Reply:
            PROMPT;
    }
}
