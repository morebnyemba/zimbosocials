<?php

namespace App\WhatsApp\Messaging;

use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin transport over the Meta Cloud API for the conversational assistant.
 *
 * Plain text sends reuse the app's existing WhatsAppService; interactive
 * messages (button / list replies), read receipts and typing indicators are
 * sent here directly because the base service only speaks text + templates.
 */
class WhatsAppGateway
{
    private string $token;
    private string $phoneNumberId;
    private string $graph;

    public function __construct(private readonly WhatsAppService $base)
    {
        $this->token = (string) config('services.whatsapp.api_token', '');
        $this->phoneNumberId = (string) config('services.whatsapp.phone_number_id', '');
        $this->graph = (string) config('services.whatsapp.graph_version', 'v21.0');
    }

    public function configured(): bool
    {
        return $this->token !== '' && $this->phoneNumberId !== '';
    }

    /** Send a plain text message. Returns ['ok'=>bool,'message_id'=>?string]. */
    public function sendText(string $to, string $body): array
    {
        return $this->base->sendMessage($to, $body);
    }

    /**
     * Send an interactive list menu.
     *
     * @param  array  $sections  [['title'=>string,'rows'=>[['id'=>,'title'=>,'description'=>?],...]],...]
     */
    public function sendList(string $to, string $body, string $buttonLabel, array $sections, ?string $header = null, ?string $footer = null): array
    {
        $interactive = [
            'type' => 'list',
            'body' => ['text' => $this->clamp($body, 1024)],
            'action' => [
                'button' => $this->clamp($buttonLabel, 20),
                'sections' => $this->normalizeSections($sections),
            ],
        ];
        if ($header !== null) {
            $interactive['header'] = ['type' => 'text', 'text' => $this->clamp($header, 60)];
        }
        if ($footer !== null) {
            $interactive['footer'] = ['text' => $this->clamp($footer, 60)];
        }

        return $this->postMessage($to, ['type' => 'interactive', 'interactive' => $interactive]);
    }

    /**
     * Send up to 3 reply buttons.
     *
     * @param  array  $buttons  [['id'=>string,'title'=>string],...] (max 3)
     */
    public function sendButtons(string $to, string $body, array $buttons, ?string $header = null, ?string $footer = null): array
    {
        $rows = array_slice(array_map(fn ($b) => [
            'type' => 'reply',
            'reply' => ['id' => (string) $b['id'], 'title' => $this->clamp((string) $b['title'], 20)],
        ], $buttons), 0, 3);

        $interactive = [
            'type' => 'button',
            'body' => ['text' => $this->clamp($body, 1024)],
            'action' => ['buttons' => $rows],
        ];
        if ($header !== null) {
            $interactive['header'] = ['type' => 'text', 'text' => $this->clamp($header, 60)];
        }
        if ($footer !== null) {
            $interactive['footer'] = ['text' => $this->clamp($footer, 60)];
        }

        return $this->postMessage($to, ['type' => 'interactive', 'interactive' => $interactive]);
    }

    /** Mark an inbound message read (blue ticks). Best-effort. */
    public function markRead(?string $waMessageId): void
    {
        if (! $waMessageId || ! $this->configured()) {
            return;
        }
        $this->post([
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $waMessageId,
        ]);
    }

    /** Show a typing indicator against an inbound message. Best-effort. */
    public function typing(?string $waMessageId): void
    {
        if (! $waMessageId || ! $this->configured()) {
            return;
        }
        // Meta ties the typing indicator to marking a message read.
        $this->post([
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $waMessageId,
            'typing_indicator' => ['type' => 'text'],
        ]);
    }

    private function postMessage(string $to, array $body): array
    {
        return $this->post(array_merge([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
        ], $body));
    }

    private function post(array $payload): array
    {
        if (! $this->configured()) {
            Log::warning('WhatsAppGateway: not configured, skipping send.');

            return ['ok' => false, 'message_id' => null, 'error' => 'not configured'];
        }

        try {
            $res = Http::withToken($this->token)
                ->timeout(15)
                ->post("https://graph.facebook.com/{$this->graph}/{$this->phoneNumberId}/messages", $payload);

            if ($res->successful()) {
                return ['ok' => true, 'message_id' => $res->json('messages.0.id'), 'error' => null];
            }

            $err = $res->json('error.message', $res->body());
            Log::error('WhatsAppGateway: send failed', ['error' => $err]);

            return ['ok' => false, 'message_id' => null, 'error' => $err];
        } catch (\Throwable $e) {
            Log::error('WhatsAppGateway: exception', ['message' => $e->getMessage()]);

            return ['ok' => false, 'message_id' => null, 'error' => $e->getMessage()];
        }
    }

    private function normalizeSections(array $sections): array
    {
        return array_map(function ($section) {
            $rows = array_map(fn ($r) => array_filter([
                'id' => $this->clamp((string) $r['id'], 200),
                'title' => $this->clamp((string) $r['title'], 24),
                'description' => isset($r['description']) ? $this->clamp((string) $r['description'], 72) : null,
            ], fn ($v) => $v !== null), $section['rows']);

            return array_filter([
                'title' => isset($section['title']) ? $this->clamp((string) $section['title'], 24) : null,
                'rows' => array_values($rows),
            ], fn ($v) => $v !== null);
        }, $sections);
    }

    private function clamp(string $s, int $max): string
    {
        return mb_strlen($s) <= $max ? $s : mb_substr($s, 0, $max - 1).'…';
    }
}
