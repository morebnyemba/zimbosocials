<?php

namespace App\WhatsApp\Webhook;

/**
 * Verifies Meta's X-Hub-Signature-256 header (HMAC-SHA256 of the raw request
 * body keyed by the app secret). When no app secret is configured we fail
 * open with a warning so local/testing setups still work.
 */
class SignatureVerifier
{
    public function verify(?string $header, string $rawBody): bool
    {
        $secret = (string) config('services.whatsapp.app_secret', '');
        if ($secret === '') {
            return true; // not configured — do not block (logged by caller)
        }
        if (! is_string($header) || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $header);
    }

    public function isConfigured(): bool
    {
        return (string) config('services.whatsapp.app_secret', '') !== '';
    }
}
