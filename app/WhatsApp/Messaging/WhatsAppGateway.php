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

    /**
     * Media kinds WhatsApp accepts on an outbound message, with Meta's size cap
     * for each (bytes). We validate against these before uploading so an
     * oversized file fails here with a clear reason instead of at the API.
     */
    public const MEDIA_KINDS = [
        'image' => 5 * 1024 * 1024,      // jpeg, png
        'video' => 16 * 1024 * 1024,     // mp4, 3gp (H.264 + AAC)
        'audio' => 16 * 1024 * 1024,     // aac, amr, mp3, m4a, ogg (opus only)
        'document' => 100 * 1024 * 1024, // any type
        'sticker' => 500 * 1024,         // webp
    ];

    /** Kinds WhatsApp refuses a caption on — it would be silently dropped. */
    private const NO_CAPTION = ['audio', 'sticker'];

    /**
     * Send an image / video / audio / document / sticker.
     *
     * $source is either a public **https** URL (Meta fetches it itself) or a
     * media id previously returned by uploadMedia(). Note this is a FREE-FORM
     * message: like any non-template message it only reaches someone inside the
     * 24-hour customer service window. Outside it, media must ride a template
     * with a media header.
     *
     * @param  string  $kind  image|video|audio|document|sticker
     */
    public function sendMedia(string $to, string $kind, string $source, ?string $caption = null, ?string $filename = null): array
    {
        $kind = strtolower(trim($kind));
        if (! array_key_exists($kind, self::MEDIA_KINDS)) {
            return ['ok' => false, 'error' => "unsupported media kind: {$kind}"];
        }
        if (trim($source) === '') {
            return ['ok' => false, 'error' => 'no media source'];
        }

        // Meta only fetches https links; anything else is an uploaded media id.
        $node = preg_match('#^https://#i', $source)
            ? ['link' => $source]
            : ['id' => $source];

        if ($caption !== null && trim($caption) !== '' && ! in_array($kind, self::NO_CAPTION, true)) {
            $node['caption'] = $this->clamp($caption, 1024);
        }
        if ($kind === 'document' && $filename !== null && trim($filename) !== '') {
            $node['filename'] = $filename;
        }

        return $this->postMessage($to, ['type' => $kind, $kind => $node]);
    }

    /**
     * Upload bytes to Meta and get a reusable media id. Needed for anything not
     * publicly reachable over https (Meta fetches a `link` itself, so it can't
     * see local or private storage). Ids are reusable and last ~30 days.
     *
     * @return array{ok:bool, media_id?:string, reason?:string, error?:string}
     */
    public function uploadMedia(string $contents, string $mime, string $filename = 'file', ?string $kind = null): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'reason' => 'not_configured'];
        }

        if ($contents === '') {
            return ['ok' => false, 'reason' => 'empty', 'error' => 'the file is empty'];
        }

        // Fail fast on Meta's per-kind size cap rather than after a long upload.
        $cap = $kind !== null ? (self::MEDIA_KINDS[$kind] ?? null) : null;
        if ($cap !== null && strlen($contents) > $cap) {
            return ['ok' => false, 'reason' => 'too_large', 'error' => "{$kind} exceeds ".round($cap / 1048576, 1).'MB'];
        }

        try {
            // Multipart upload: once a file is attached every other field has to
            // ride as its own part too, not as a JSON body.
            $res = Http::withToken($this->token)
                ->timeout(60)
                ->attach('file', $contents, $filename, ['Content-Type' => $mime])
                ->attach('messaging_product', 'whatsapp')
                ->attach('type', $mime)
                ->post("https://graph.facebook.com/{$this->graph}/{$this->phoneNumberId}/media");

            if (! $res->successful()) {
                $error = (string) $res->json('error.message', $res->body());
                Log::warning('WhatsAppGateway: media upload failed', ['error' => $error, 'mime' => $mime]);

                return ['ok' => false, 'reason' => 'upload_failed', 'error' => $error];
            }

            return ['ok' => true, 'media_id' => (string) $res->json('id')];
        } catch (\Throwable $e) {
            Log::warning('WhatsAppGateway: media upload exception', ['message' => $e->getMessage()]);

            return ['ok' => false, 'reason' => 'exception', 'error' => $e->getMessage()];
        }
    }

    private function postMessage(string $to, array $body): array
    {
        return $this->post(array_merge([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
        ], $body));
    }

    /**
     * Download an inbound media object by its id. Two hops per Meta's API:
     * resolve the media id to a short-lived signed URL, then fetch the bytes
     * (both require the bearer token).
     *
     * @return array{ok:bool, contents?:string, mime?:string, size?:int, reason?:string}
     */
    public function downloadMedia(string $mediaId): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'reason' => 'not_configured'];
        }

        try {
            $lookup = Http::withToken($this->token)->timeout(15)
                ->get("https://graph.facebook.com/{$this->graph}/{$mediaId}");
            if (! $lookup->successful()) {
                Log::warning('WhatsAppGateway: media lookup failed', ['error' => $lookup->json('error.message', $lookup->body())]);

                return ['ok' => false, 'reason' => 'lookup_failed'];
            }

            $url = (string) $lookup->json('url');
            if ($url === '') {
                return ['ok' => false, 'reason' => 'no_url'];
            }

            $mime = (string) $lookup->json('mime_type');
            $size = (int) $lookup->json('file_size');

            // The signed CDN URL still needs the bearer token.
            $bin = Http::withToken($this->token)->timeout(30)->get($url);
            if (! $bin->successful()) {
                return ['ok' => false, 'reason' => 'download_failed'];
            }

            $contents = $bin->body();

            return [
                'ok' => true,
                'contents' => $contents,
                'mime' => $mime !== '' ? $mime : (string) $bin->header('Content-Type'),
                'size' => $size > 0 ? $size : strlen($contents),
            ];
        } catch (\Throwable $e) {
            Log::warning('WhatsAppGateway: media download exception', ['media_id' => $mediaId, 'message' => $e->getMessage()]);

            return ['ok' => false, 'reason' => 'exception'];
        }
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
