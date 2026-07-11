<?php

namespace App\WhatsApp\Webhook;

/**
 * Flattens Meta Cloud API webhook payloads into a simple list of normalized
 * inbound messages. Delivery/status callbacks (value.statuses) are returned
 * separately so the caller can update delivery_status without treating them
 * as user input.
 *
 * Normalized message shape:
 *   [
 *     'from'          => '2637...',        // E.164 digits, no '+'
 *     'wa_message_id' => 'wamid...',
 *     'type'          => 'text'|'interactive'|'button'|...,
 *     'text'          => ?string,          // body text, if any
 *     'interactive_id'=> ?string,          // selected list/button id, if any
 *     'name'          => ?string,          // contact profile name
 *     'timestamp'     => ?int,
 *     'raw'           => array,            // the original message node
 *   ]
 */
class InboundNormalizer
{
    /** @return array{messages: array<int,array>, statuses: array<int,array>} */
    public function normalize(array $payload): array
    {
        $messages = [];
        $statuses = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                // Map wa_id -> profile name from contacts.
                $names = [];
                foreach ($value['contacts'] ?? [] as $contact) {
                    if (isset($contact['wa_id'])) {
                        $names[$contact['wa_id']] = $contact['profile']['name'] ?? null;
                    }
                }

                foreach ($value['messages'] ?? [] as $m) {
                    $messages[] = $this->normalizeMessage($m, $names);
                }

                foreach ($value['statuses'] ?? [] as $s) {
                    $statuses[] = [
                        'wa_message_id' => $s['id'] ?? null,
                        'status' => $s['status'] ?? null,
                        'recipient' => $s['recipient_id'] ?? null,
                    ];
                }
            }
        }

        return ['messages' => $messages, 'statuses' => $statuses];
    }

    private function normalizeMessage(array $m, array $names): array
    {
        $from = $m['from'] ?? '';
        $type = $m['type'] ?? 'unknown';

        $text = null;
        $interactiveId = null;

        switch ($type) {
            case 'text':
                $text = $m['text']['body'] ?? null;
                break;
            case 'interactive':
                $inter = $m['interactive'] ?? [];
                if (($inter['type'] ?? null) === 'list_reply') {
                    $interactiveId = $inter['list_reply']['id'] ?? null;
                    $text = $inter['list_reply']['title'] ?? null;
                } elseif (($inter['type'] ?? null) === 'button_reply') {
                    $interactiveId = $inter['button_reply']['id'] ?? null;
                    $text = $inter['button_reply']['title'] ?? null;
                }
                break;
            case 'button': // template quick-reply button
                $interactiveId = $m['button']['payload'] ?? null;
                $text = $m['button']['text'] ?? null;
                break;
            default:
                // media, location, etc. — no text/selection extracted for now
                break;
        }

        return [
            'from' => $from,
            'wa_message_id' => $m['id'] ?? null,
            'type' => $type,
            'text' => $text,
            'interactive_id' => $interactiveId,
            'name' => $names[$from] ?? null,
            'timestamp' => isset($m['timestamp']) ? (int) $m['timestamp'] : null,
            'raw' => $m,
        ];
    }
}
