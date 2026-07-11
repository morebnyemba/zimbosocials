<?php

namespace App\Http\Controllers;

use App\WhatsApp\Persistence\MessageStore;
use App\WhatsApp\Routing\MessageRouter;
use App\WhatsApp\Webhook\InboundNormalizer;
use App\WhatsApp\Webhook\SignatureVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Meta Cloud API webhook for the WhatsApp conversational assistant.
 *
 *   GET  /webhooks/whatsapp  — one-time verification handshake
 *   POST /webhooks/whatsapp  — inbound messages & delivery statuses
 */
class WhatsAppWebhookController extends Controller
{
    public function __construct(
        private readonly SignatureVerifier $signatures,
        private readonly InboundNormalizer $normalizer,
        private readonly MessageRouter $router,
        private readonly MessageStore $messages,
    ) {}

    /** Verification handshake: echo hub.challenge when the token matches. */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $expected = (string) config('services.whatsapp.webhook_verify_token', '');

        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, (string) $token)) {
            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('WhatsApp webhook verify rejected', ['mode' => $mode]);

        return response('Forbidden', 403);
    }

    /** Receive inbound events. Always 200 quickly so Meta does not retry. */
    public function receive(Request $request)
    {
        $raw = $request->getContent();

        if (! $this->signatures->verify($request->header('X-Hub-Signature-256'), $raw)) {
            Log::warning('WhatsApp webhook signature mismatch');

            return response('Invalid signature', 403);
        }

        if (! config('services.whatsapp.assistant_enabled', true)) {
            return response('', 200);
        }

        try {
            $payload = json_decode($raw, true) ?: [];
            $parsed = $this->normalizer->normalize($payload);

            foreach ($parsed['messages'] as $message) {
                $this->router->handle($message, $message['name'] ?? null);
            }

            foreach ($parsed['statuses'] as $status) {
                if (! empty($status['wa_message_id'])) {
                    $this->messages->updateDeliveryStatus($status['wa_message_id'], $status['status'] ?? null);
                }
            }
        } catch (\Throwable $e) {
            // Never surface a 500 to Meta — that triggers aggressive retries.
            Log::error('WhatsApp webhook processing error', ['message' => $e->getMessage()]);
        }

        return response('', 200);
    }
}
