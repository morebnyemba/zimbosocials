<?php

namespace App\WhatsApp\Persistence;

use App\Models\WhatsAppMessage;

/**
 * Reads/writes the unified WhatsApp transcript and provides idempotency:
 * recordInbound() returns 0 when Meta redelivers a message we've already seen
 * (deduped on the unique wa_message_id), so the router can bail early.
 */
class MessageStore
{
    /** @return int inserted row id, or 0 if this is a duplicate delivery. */
    public function recordInbound(array $msg): int
    {
        $waId = $msg['wa_message_id'] ?? null;

        if ($waId !== null && WhatsAppMessage::where('wa_message_id', $waId)->exists()) {
            return 0;
        }

        $row = WhatsAppMessage::create([
            'wa_phone' => $msg['from'] ?? '',
            'direction' => 'in',
            'wa_message_id' => $waId,
            'msg_type' => $msg['type'] ?? 'text',
            'body' => $msg['text'] ?? null,
            'payload' => $msg['raw'] ?? null,
        ]);

        return (int) $row->id;
    }

    public function recordOutbound(string $phone, string $body, array $meta = [], ?string $waMessageId = null): void
    {
        WhatsAppMessage::create(array_merge([
            'wa_phone' => $phone,
            'direction' => 'out',
            'wa_message_id' => $waMessageId,
            'msg_type' => $meta['msg_type'] ?? 'text',
            'body' => $body,
        ], array_intersect_key($meta, array_flip(['flow', 'handled_by', 'intent', 'confidence', 'ai_used', 'payload']))));
    }

    public function tagInbound(int $id, array $meta): void
    {
        if ($id <= 0) {
            return;
        }
        WhatsAppMessage::where('id', $id)->update(
            array_intersect_key($meta, array_flip(['flow', 'handled_by', 'intent', 'confidence', 'ai_used']))
        );
    }

    public function updateDeliveryStatus(string $waMessageId, ?string $status): void
    {
        if ($status === null) {
            return;
        }
        WhatsAppMessage::where('wa_message_id', $waMessageId)->update(['delivery_status' => $status]);
    }
}
