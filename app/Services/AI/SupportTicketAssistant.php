<?php

namespace App\Services\AI;

use App\Models\Ticket;

/**
 * Gemini-backed helpers for admin support replies. Every output is a
 * suggestion; the admin reviews and sends.
 *
 *  - draftReply():   writes a customer-facing reply built around the admin's
 *                    own talking points (the AI never decides WHAT to say,
 *                    only how to say it well).
 *  - enhanceReply(): polishes a reply the admin already wrote — grammar,
 *                    clarity, tone — without adding or changing facts.
 *  - summarizeThread(): quick context for long tickets.
 */
class SupportTicketAssistant
{
    public function __construct(private readonly GeminiClient $client) {}

    public function isAvailable(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * Draft a reply built around the admin's talking points.
     *
     * @param  string  $intent  What the admin wants to tell the customer.
     */
    public function draftReply(Ticket $ticket, string $intent, ?string $tone = null): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $tone ??= 'professional, empathetic, concise';

        $prompt = <<<PROMPT
            You are a senior support agent for Zimbo Socials, a Zimbabwean social-media marketing (SMM) platform.
            Write a single reply to the customer in English.

            THE AGENT'S INSTRUCTIONS — the reply MUST communicate exactly these points, nothing more:
            {$intent}

            Tone: {$tone}

            Guidelines:
            - Build the reply around the agent's instructions above; do not invent additional promises, policies, refunds, or delivery times.
            - Address the customer by name when appropriate.
            - Be clear and concise.
            - Do not include a signature; the system adds it automatically.

            Context:
            {$this->buildContext($ticket)}

            Reply:
            PROMPT;

        return $this->client->generateText($prompt, 0.3);
    }

    /**
     * Improve a reply the admin already wrote: grammar, clarity, structure,
     * and tone — while preserving every fact and commitment exactly as-is.
     */
    public function enhanceReply(Ticket $ticket, string $draft, ?string $tone = null): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $tone ??= 'professional, empathetic, concise';

        $prompt = <<<PROMPT
            You are an editor for customer support replies at Zimbo Socials, a Zimbabwean social-media marketing (SMM) platform.
            Rewrite the agent's draft below to be well-structured, grammatical, clear, and {$tone}.

            STRICT RULES:
            - Preserve every fact, amount, promise, and instruction from the draft EXACTLY. Do not add, remove, or soften commitments.
            - Do not invent policies, refunds, or delivery times that are not in the draft.
            - Keep roughly the same length or shorter.
            - Output only the improved reply, no commentary, no signature.

            Ticket context (for understanding only, not for adding content):
            {$this->buildContext($ticket)}

            Agent's draft:
            {$draft}

            Improved reply:
            PROMPT;

        return $this->client->generateText($prompt, 0.2);
    }

    /**
     * Summarize the ticket thread for a fast catch-up: what the customer
     * wants, what has been done, and the suggested next step.
     */
    public function summarizeThread(Ticket $ticket): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $prompt = <<<PROMPT
            You are a support team lead at Zimbo Socials, a Zimbabwean social-media marketing (SMM) platform.
            Summarize this support ticket for an agent picking it up. Output plain text with exactly these three short sections:

            Issue: (1-2 sentences — what the customer wants)
            So far: (1-2 sentences — what has already been said or done, or "No replies yet")
            Suggested next step: (1 sentence — do not promise refunds or policies, just the sensible next action)

            Context:
            {$this->buildContext($ticket)}
            PROMPT;

        return $this->client->generateText($prompt, 0.2);
    }

    private function buildContext(Ticket $ticket): string
    {
        $customer = $ticket->user;

        $replies = $ticket->replies()->with('user')->get()->map(function ($reply) {
            return [
                'author' => $reply->is_admin ? 'Admin' : ($reply->user?->name ?? 'Customer'),
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

        return json_encode([
            'customer_name' => $customer?->name ?? 'Customer',
            'customer_locale' => $customer?->locale ?? 'en',
            'subject' => $ticket->subject,
            'original_message' => $ticket->message,
            'previous_replies' => $replies,
            'recent_orders' => $orders,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
