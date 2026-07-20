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
        // Cast: config() returns null (not the default) when the key exists
        // but the env var is unset, and null fatals on these string properties —
        // which used to 500 registration on any install without WhatsApp set up.
        $this->provider = (string) (config('services.whatsapp.provider') ?? 'meta');
        $this->apiToken = (string) config('services.whatsapp.api_token', '');
        $this->phoneNumberId = (string) config('services.whatsapp.phone_number_id', '');
        $this->wabAccountId = (string) config('services.whatsapp.waba_id', '');
        $this->twilioSid = (string) config('services.whatsapp.twilio_sid', '');
        $this->twilioFrom = (string) config('services.whatsapp.twilio_from', '');
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
        bool $marketing = false,
    ): array {
        if (empty($this->apiToken)) {
            return ['ok' => false, 'message_id' => null, 'error' => 'API token not configured'];
        }

        if ($this->provider === 'twilio') {
            // Twilio uses Content SID for templates, fallback to text
            return $this->sendViaTwilio($to, $this->interpolateTemplate($templateName, $bodyParams));
        }

        // Marketing Messages (MM Lite): Meta routes marketing templates through
        // its own delivery-optimising infrastructure — it picks the moment the
        // recipient is most likely to engage (within a 24h window) and gives
        // higher-engagement templates more reach. Same payload as Cloud API,
        // different endpoint, and it must be enabled on the WABA first.
        // Off by default; falls back to the normal endpoint on any failure so
        // an un-enabled account can never lose a send.
        if ($marketing && config('services.whatsapp.mm_lite', false)) {
            $result = $this->sendTemplateViaMeta($to, $templateName, $language, $bodyParams, $headerParams, marketing: true);

            if ($result['ok']) {
                return $result;
            }

            Log::warning('MM Lite send failed — retrying on the standard Cloud API endpoint', [
                'template' => $templateName,
                'error' => $result['error'] ?? null,
                'code' => $result['error_code'] ?? null,
            ]);
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
        bool $marketing = false,
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

            // MM Lite exposes the same payload on its own endpoint; the graph
            // version is configurable because the two can move independently.
            $version = (string) config('services.whatsapp.graph_version', 'v21.0');
            $path = $marketing
                ? (string) config('services.whatsapp.mm_lite_path', 'marketing_messages')
                : 'messages';

            $response = Http::withToken($this->apiToken)
                ->timeout(15)
                ->post("https://graph.facebook.com/{$version}/{$this->phoneNumberId}/{$path}", $payload);

            if ($response->successful()) {
                $msgId = $response->json('messages.0.id');
                $via = $marketing ? 'MM Lite' : 'Template';
                Log::info("WhatsApp [{$via}]: Sent '{$templateName}' to {$to}", ['message_id' => $msgId]);

                return ['ok' => true, 'message_id' => $msgId, 'error' => null];
            }

            $err = $response->json('error.message', $response->body());
            $code = $response->json('error.code');
            Log::error("WhatsApp [Template]: Failed '{$templateName}' to {$to}", ['error' => $err, 'code' => $code]);

            return ['ok' => false, 'message_id' => null, 'error' => $err, 'error_code' => $code];
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

            $err = $this->templateError($response);
            Log::error("WhatsApp Template Create Failed: {$templateDef['name']}", ['error' => $response->json('error')]);

            return ['ok' => false, 'error' => $err];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update an existing template by its Meta template id. Editing a REJECTED
     * (or PAUSED) template resubmits it for review — preferable to delete +
     * recreate, since a deleted name is blocked for 30 days.
     */
    public function updateTemplate(string $templateId, array $templateDef): array
    {
        if (empty($this->apiToken)) {
            return ['ok' => false, 'error' => 'API token not configured'];
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->timeout(30)
                ->post("https://graph.facebook.com/v21.0/{$templateId}", [
                    'category' => $templateDef['category'],
                    'components' => $templateDef['components'],
                ]);

            if ($response->successful()) {
                Log::info("WhatsApp Template Updated: {$templateId}", $response->json());

                return ['ok' => true, 'data' => $response->json()];
            }

            Log::error("WhatsApp Template Update Failed: {$templateId}", ['error' => $response->json('error')]);

            return ['ok' => false, 'error' => $this->templateError($response)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Meta buries the actionable reason in error_user_msg — "Invalid
     * parameter" alone is the generic wrapper. Surface the specific text.
     */
    private function templateError(\Illuminate\Http\Client\Response $response): string
    {
        $error = (array) $response->json('error', []);
        $parts = array_filter([
            $error['error_user_title'] ?? null,
            $error['error_user_msg'] ?? null,
        ]);

        if ($parts !== []) {
            return implode(' — ', $parts);
        }

        return (string) ($error['message'] ?? $response->body());
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
