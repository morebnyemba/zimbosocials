<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Business API integration.
 *
 * Supports two providers out-of-the-box:
 *  1. Meta Official Cloud API (default)
 *  2. Twilio WhatsApp
 *
 * Set WHATSAPP_PROVIDER in .env ('meta' or 'twilio').
 */
class WhatsAppService
{
    private string $provider;

    private string $apiToken;

    private string $phoneNumberId;   // Meta Cloud API

    private string $wabAccountId;    // WhatsApp Business Account ID (for templates)

    private string $twilioSid;       // Twilio

    private string $twilioFrom;      // Twilio "whatsapp:+1234..."

    public function __construct()
    {
        $this->provider = config('services.whatsapp.provider', 'meta');
        $this->apiToken = config('services.whatsapp.api_token', '');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id', '');
        $this->wabAccountId = config('services.whatsapp.waba_id', '');
        $this->twilioSid = config('services.whatsapp.twilio_sid', '');
        $this->twilioFrom = config('services.whatsapp.twilio_from', '');
    }

    // ─── Text Messages ───────────────────────────────────────────────────────

    /**
     * Send a plain text message.
     */
    public function sendMessage(string $to, string $message): array
    {
        if (empty($this->apiToken)) {
            Log::warning('WhatsApp: API token not configured, skipping send.');

            return ['ok' => false, 'message_id' => null, 'error' => 'API token not configured'];
        }

        return match ($this->provider) {
            'twilio' => $this->sendViaTwilio($to, $message),
            default => $this->sendViaMeta($to, $message),
        };
    }

    // ─── Template Messages ───────────────────────────────────────────────────

    /**
     * Send a template message via Meta Cloud API.
     *
     * Meta requires pre-approved templates for business-initiated conversations.
     * Templates can contain variable parameters: {{1}}, {{2}}, etc.
     *
     * @param  string  $to  E.164 format without +
     * @param  string  $templateName  The template name registered in Meta
     * @param  string  $language  Language code (e.g., 'en_US', 'en')
     * @param  array  $bodyParams  Ordered array of body variable values ['John', '$10.00']
     * @param  array  $headerParams  Optional header variable values
     */
    public function sendTemplate(
        string $to,
        string $templateName,
        string $language = 'en',
        array $bodyParams = [],
        array $headerParams = [],
    ): array {
        if (empty($this->apiToken)) {
            return ['ok' => false, 'message_id' => null, 'error' => 'API token not configured'];
        }

        if ($this->provider === 'twilio') {
            // Twilio uses Content SID for templates, fallback to text
            return $this->sendViaTwilio($to, $this->interpolateTemplate($templateName, $bodyParams));
        }

        return $this->sendTemplateViaMeta($to, $templateName, $language, $bodyParams, $headerParams);
    }

    /**
     * Meta Cloud API — send template message.
     */
    private function sendTemplateViaMeta(
        string $to,
        string $templateName,
        string $language,
        array $bodyParams,
        array $headerParams,
    ): array {
        $components = [];

        if (! empty($headerParams)) {
            $components[] = [
                'type' => 'header',
                'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], $headerParams),
            ];
        }

        if (! empty($bodyParams)) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], $bodyParams),
            ];
        }

        try {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => $language],
                ],
            ];

            if (! empty($components)) {
                $payload['template']['components'] = $components;
            }

            $response = Http::withToken($this->apiToken)
                ->timeout(15)
                ->post("https://graph.facebook.com/v21.0/{$this->phoneNumberId}/messages", $payload);

            if ($response->successful()) {
                $msgId = $response->json('messages.0.id');
                Log::info("WhatsApp [Template]: Sent '{$templateName}' to {$to}", ['message_id' => $msgId]);

                return ['ok' => true, 'message_id' => $msgId, 'error' => null];
            }

            $err = $response->json('error.message', $response->body());
            Log::error("WhatsApp [Template]: Failed '{$templateName}' to {$to}", ['error' => $err]);

            return ['ok' => false, 'message_id' => null, 'error' => $err];
        } catch (\Throwable $e) {
            Log::error("WhatsApp [Template]: Exception — {$e->getMessage()}");

            return ['ok' => false, 'message_id' => null, 'error' => $e->getMessage()];
        }
    }

    // ─── Template Management (Meta Cloud API) ────────────────────────────────

    /**
     * Create a message template in Meta Business.
     */
    public function createTemplate(array $templateDef): array
    {
        if (empty($this->wabAccountId) || empty($this->apiToken)) {
            return ['ok' => false, 'error' => 'WABA ID or API token not configured'];
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->timeout(30)
                ->post("https://graph.facebook.com/v21.0/{$this->wabAccountId}/message_templates", $templateDef);

            if ($response->successful()) {
                Log::info("WhatsApp Template Created: {$templateDef['name']}", $response->json());

                return ['ok' => true, 'data' => $response->json()];
            }

            $err = $response->json('error.message', $response->body());
            Log::error("WhatsApp Template Create Failed: {$templateDef['name']}", ['error' => $err]);

            return ['ok' => false, 'error' => $err];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * List all templates from the WhatsApp Business Account.
     */
    public function listTemplates(): array
    {
        if (empty($this->wabAccountId) || empty($this->apiToken)) {
            return ['ok' => false, 'templates' => [], 'error' => 'WABA ID or API token not configured'];
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->timeout(15)
                ->get("https://graph.facebook.com/v21.0/{$this->wabAccountId}/message_templates", [
                    'limit' => 100,
                ]);

            if ($response->successful()) {
                return ['ok' => true, 'templates' => $response->json('data', []), 'error' => null];
            }

            return ['ok' => false, 'templates' => [], 'error' => $response->json('error.message', $response->body())];
        } catch (\Throwable $e) {
            return ['ok' => false, 'templates' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete a template by name.
     */
    public function deleteTemplate(string $name): array
    {
        if (empty($this->wabAccountId) || empty($this->apiToken)) {
            return ['ok' => false, 'error' => 'WABA ID or API token not configured'];
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->timeout(15)
                ->delete("https://graph.facebook.com/v21.0/{$this->wabAccountId}/message_templates", [
                    'name' => $name,
                ]);

            return ['ok' => $response->successful(), 'error' => $response->successful() ? null : $response->body()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // ─── Plain text sending ──────────────────────────────────────────────────

    private function sendViaMeta(string $to, string $message): array
    {
        try {
            $response = Http::withToken($this->apiToken)
                ->timeout(15)
                ->post("https://graph.facebook.com/v21.0/{$this->phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'text',
                    'text' => ['body' => $message],
                ]);

            if ($response->successful()) {
                $msgId = $response->json('messages.0.id');
                Log::info("WhatsApp [Meta]: Sent to {$to}", ['message_id' => $msgId]);

                return ['ok' => true, 'message_id' => $msgId, 'error' => null];
            }

            $err = $response->json('error.message', $response->body());
            Log::error("WhatsApp [Meta]: Failed to {$to}", ['error' => $err]);

            return ['ok' => false, 'message_id' => null, 'error' => $err];
        } catch (\Throwable $e) {
            Log::error("WhatsApp [Meta]: Exception — {$e->getMessage()}");

            return ['ok' => false, 'message_id' => null, 'error' => $e->getMessage()];
        }
    }

    private function sendViaTwilio(string $to, string $message): array
    {
        try {
            $response = Http::withBasicAuth($this->twilioSid, $this->apiToken)
                ->timeout(15)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->twilioSid}/Messages.json", [
                    'From' => $this->twilioFrom,
                    'To' => "whatsapp:+{$to}",
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                $msgId = $response->json('sid');
                Log::info("WhatsApp [Twilio]: Sent to {$to}", ['sid' => $msgId]);

                return ['ok' => true, 'message_id' => $msgId, 'error' => null];
            }

            $err = $response->json('message', $response->body());
            Log::error("WhatsApp [Twilio]: Failed to {$to}", ['error' => $err]);

            return ['ok' => false, 'message_id' => null, 'error' => $err];
        } catch (\Throwable $e) {
            Log::error("WhatsApp [Twilio]: Exception — {$e->getMessage()}");

            return ['ok' => false, 'message_id' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build a text message from a template name + params (fallback for non-Meta providers).
     */
    private function interpolateTemplate(string $templateName, array $params): string
    {
        $templates = config('whatsapp-templates.templates', []);
        $tpl = $templates[$templateName] ?? null;

        if (! $tpl) {
            return 'Notification: '.implode(', ', $params);
        }

        $body = $tpl['body'];
        foreach ($params as $i => $val) {
            $body = str_replace('{{'.($i + 1).'}}', $val, $body);
        }

        return $body;
    }
}
