<?php

namespace App\WhatsApp\Messaging;

use Illuminate\Support\Facades\Log;

/**
 * Shrinks a photo before it is billed as AI input.
 *
 * Gemini charges images by tiling them at 768px: a 4000x3000 phone photo is
 * roughly 24 tiles, while the same picture at 1024px wide is 2. Nothing we ask
 * of an image — reading a payment confirmation, seeing a follower count,
 * recognising a profile screenshot — needs more than that, so the extra
 * resolution is money spent on nothing.
 *
 * Best-effort throughout: if GD is unavailable or the image is unreadable, the
 * original bytes are returned and the caller carries on as before.
 */
class MediaDownscaler
{
    /** Wide enough to read a screenshot's small print, far below a phone photo. */
    private const MAX_EDGE = 1024;

    /** Below this there is nothing worth shrinking. */
    private const SKIP_UNDER_BYTES = 200 * 1024;

    public function shrink(string $bytes, string $mime): string
    {
        if (! str_starts_with(strtolower($mime), 'image/')) {
            return $bytes;
        }
        if (strlen($bytes) < self::SKIP_UNDER_BYTES || ! function_exists('imagecreatefromstring')) {
            return $bytes;
        }

        try {
            $image = @imagecreatefromstring($bytes);
            if ($image === false) {
                return $bytes;
            }

            $width = imagesx($image);
            $height = imagesy($image);
            $longest = max($width, $height);

            if ($longest <= self::MAX_EDGE) {
                imagedestroy($image);

                return $bytes;
            }

            $scale = self::MAX_EDGE / $longest;
            $resized = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));
            imagedestroy($image);

            if ($resized === false) {
                return $bytes;
            }

            ob_start();
            // 82 keeps text in a screenshot legible while dropping most of the
            // weight; the model never sees the original either way.
            imagejpeg($resized, null, 82);
            $out = (string) ob_get_clean();
            imagedestroy($resized);

            // Only take it if it actually helped.
            return ($out !== '' && strlen($out) < strlen($bytes)) ? $out : $bytes;
        } catch (\Throwable $e) {
            Log::warning('Image downscale failed; sending original', ['message' => $e->getMessage()]);

            return $bytes;
        }
    }
}
