<?php

namespace App\WhatsApp\Messaging;

use App\WhatsApp\Persistence\MessageStore;

/**
 * Sends assistant replies (text or interactive menus) and records every
 * outbound message to the transcript with routing metadata.
 */
class Responder
{
    public function __construct(
        private readonly WhatsAppGateway $gateway,
        private readonly MessageStore $messages,
    ) {}

    public function send(string $phone, string $text, array $meta = []): void
    {
        $res = $this->gateway->sendText($phone, $text);
        $this->messages->recordOutbound($phone, $text, $meta, $res['message_id'] ?? null);
    }

    /**
     * Send an interactive list menu built by MenuProvider.
     *
     * @param  array  $spec  ['body'=>, 'button'=>, 'sections'=>, 'header'=>?, 'footer'=>?]
     */
    public function sendMenu(string $phone, array $spec, array $meta = []): void
    {
        $res = $this->gateway->sendList(
            $phone,
            $spec['body'] ?? '',
            $spec['button'] ?? 'Menu',
            $spec['sections'] ?? [],
            $spec['header'] ?? null,
            $spec['footer'] ?? null,
        );

        // Fall back to a plain-text menu if interactive send failed (e.g. list
        // constraints) so the user is never left without options.
        if (empty($res['ok'])) {
            $this->send($phone, $this->flatten($spec), array_merge(['handled_by' => 'menu'], $meta));

            return;
        }

        $this->messages->recordOutbound($phone, $spec['body'] ?? '[menu]', array_merge(['msg_type' => 'interactive', 'handled_by' => 'menu'], $meta), $res['message_id'] ?? null);
    }

    /**
     * Send up to 3 reply buttons.
     *
     * @param  array  $buttons  [['id'=>string,'title'=>string],...]
     */
    public function sendButtons(string $phone, string $body, array $buttons, array $meta = []): void
    {
        $res = $this->gateway->sendButtons($phone, $body, $buttons);
        if (empty($res['ok'])) {
            $this->send($phone, $body, $meta);

            return;
        }
        $this->messages->recordOutbound($phone, $body, array_merge(['msg_type' => 'interactive'], $meta), $res['message_id'] ?? null);
    }

    /**
     * Send an image / video / audio / document / sticker, and record it in the
     * transcript so the conversation view shows what the customer received.
     *
     * $source is a public https URL or a media id from WhatsAppGateway::
     * uploadMedia(). This is a free-form message, so it only reaches someone
     * inside the 24-hour service window — check before sending anything
     * proactive (WhatsAppAccount::serviceWindowOpen).
     *
     * Returns false when the send failed, so callers can fall back to text
     * rather than leave the customer with nothing.
     */
    public function sendMedia(string $phone, string $kind, string $source, ?string $caption = null, array $meta = [], ?string $filename = null): bool
    {
        $res = $this->gateway->sendMedia($phone, $kind, $source, $caption, $filename);

        if (empty($res['ok'])) {
            return false;
        }

        $this->messages->recordOutbound(
            $phone,
            // Captions carry the meaning; without one, label the attachment.
            $caption !== null && trim($caption) !== '' ? $caption : "[{$kind}]",
            array_merge(['msg_type' => $kind], $meta),
            $res['message_id'] ?? null,
        );

        return true;
    }

    public function markRead(?string $waMessageId): void
    {
        $this->gateway->markRead($waMessageId);
    }

    public function typing(?string $waMessageId): void
    {
        $this->gateway->typing($waMessageId);
    }

    /** Render a menu spec as numbered plain text (fallback path). */
    private function flatten(array $spec): string
    {
        $out = ($spec['header'] ?? '')."\n\n".($spec['body'] ?? '');
        foreach ($spec['sections'] ?? [] as $section) {
            $out .= "\n\n*".($section['title'] ?? '')."*";
            foreach ($section['rows'] as $row) {
                $out .= "\n• ".$row['title'].(isset($row['description']) ? ' — '.$row['description'] : '');
            }
        }

        return trim($out);
    }
}
