<?php

namespace App\WhatsApp\Messaging;

use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Keeps a local copy of inbound WhatsApp media so it can be viewed and played
 * later in the admin console.
 *
 * Meta hands us a media *id*; the bytes live behind a short-lived signed URL
 * that needs the bearer token, and the id itself expires. So a media message is
 * only ever viewable if we save it at the moment it arrives.
 *
 * It also carries the downloaded bytes forward on the media array, so the proof
 * intake and the AI vision/audio path reuse them instead of each re-downloading
 * the same file (the webhook runs inline — every extra hop is user-visible).
 */
class MediaArchive
{
    /** Extension per mime, for the stored filename. Anything else keeps .bin. */
    private const EXT = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'video/mp4' => 'mp4',
        'video/3gpp' => '3gp',
        'audio/ogg' => 'ogg',
        'audio/mpeg' => 'mp3',
        'audio/mp4' => 'm4a',
        'audio/aac' => 'aac',
        'audio/amr' => 'amr',
        'application/pdf' => 'pdf',
    ];

    /** Don't archive anything absurd — WhatsApp caps documents at 100 MB. */
    private const MAX_BYTES = 100 * 1024 * 1024;

    public function __construct(private readonly WhatsAppGateway $gateway) {}

    /**
     * Download an inbound media node, store it, and stamp the message row.
     *
     * Returns the media array enriched with 'contents', 'url' and 'mime' so
     * downstream consumers can skip their own download. Always returns the
     * original array on failure — archiving is best-effort and must never cost
     * the user their message.
     */
    public function capture(array $media, string $phone, int $messageId = 0): array
    {
        $mediaId = (string) ($media['id'] ?? '');
        if ($mediaId === '') {
            return $media;
        }

        try {
            $dl = $this->gateway->downloadMedia($mediaId);
            if (empty($dl['ok']) || ($dl['contents'] ?? '') === '') {
                return $media;
            }

            $contents = (string) $dl['contents'];
            if (strlen($contents) > self::MAX_BYTES) {
                return $media;
            }

            // Prefer the mime Meta reports on the download over the webhook's.
            $mime = strtolower(trim(explode(';', (string) ($dl['mime'] ?? $media['mime'] ?? ''))[0]));
            $ext = self::EXT[$mime] ?? 'bin';

            $safePhone = preg_replace('/\D+/', '', $phone) ?: 'unknown';
            $path = "wa-media/{$safePhone}/".Str::uuid()->toString().'.'.$ext;
            Storage::disk('public')->put($path, $contents);

            $url = '/storage/'.$path;

            if ($messageId > 0) {
                WhatsAppMessage::where('id', $messageId)->update([
                    'media_url' => $url,
                    'media_mime' => $mime !== '' ? $mime : null,
                ]);
            }

            return array_merge($media, [
                'contents' => $contents,
                'url' => $url,
                'mime' => $mime !== '' ? $mime : ($media['mime'] ?? ''),
            ]);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp media archive failed', [
                'media_id' => $mediaId,
                'message' => $e->getMessage(),
            ]);

            return $media;
        }
    }

    /**
     * Store bytes we are SENDING so the transcript can show them too.
     * Returns the public URL, or null if it could not be stored.
     */
    public function keepOutbound(string $contents, string $mime, string $phone): ?string
    {
        try {
            $ext = self::EXT[strtolower($mime)] ?? 'bin';
            $safePhone = preg_replace('/\D+/', '', $phone) ?: 'unknown';
            $path = "wa-media/{$safePhone}/".Str::uuid()->toString().'.'.$ext;
            Storage::disk('public')->put($path, $contents);

            return '/storage/'.$path;
        } catch (\Throwable $e) {
            Log::warning('WhatsApp outbound media store failed', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
