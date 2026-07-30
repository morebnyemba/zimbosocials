<?php

namespace Tests\Feature;

use App\WhatsApp\Messaging\MediaDownscaler;
use Tests\TestCase;

/**
 * Gemini bills images by tiling them at 768px, so a full-resolution phone photo
 * costs several times what the same picture costs at 1024px wide — and nothing
 * we ask of an image (reading a payment confirmation, seeing a follower count)
 * needs the difference.
 */
class MediaDownscalerTest extends TestCase
{
    private function jpeg(int $width, int $height): string
    {
        $img = imagecreatetruecolor($width, $height);
        // Some noise, so it doesn't compress to almost nothing and skip the
        // size threshold we're trying to exercise.
        for ($i = 0; $i < 4000; $i++) {
            imagesetpixel(
                $img,
                random_int(0, $width - 1),
                random_int(0, $height - 1),
                imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255))
            );
        }
        ob_start();
        imagejpeg($img, null, 95);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        return $bytes;
    }

    public function test_a_large_photo_is_shrunk(): void
    {
        if (! function_exists('imagecreatefromstring')) {
            $this->markTestSkipped('GD not available');
        }

        $original = $this->jpeg(3000, 2200);
        $shrunk = app(MediaDownscaler::class)->shrink($original, 'image/jpeg');

        $this->assertLessThan(strlen($original), strlen($shrunk));

        $img = imagecreatefromstring($shrunk);
        $this->assertLessThanOrEqual(1024, max(imagesx($img), imagesy($img)));
        // Aspect ratio preserved — a squashed screenshot is harder to read.
        $this->assertEqualsWithDelta(3000 / 2200, imagesx($img) / imagesy($img), 0.01);
    }

    public function test_a_small_image_is_left_alone(): void
    {
        if (! function_exists('imagecreatefromstring')) {
            $this->markTestSkipped('GD not available');
        }

        $original = $this->jpeg(400, 300);

        $this->assertSame($original, app(MediaDownscaler::class)->shrink($original, 'image/jpeg'));
    }

    public function test_audio_is_never_touched(): void
    {
        $audio = str_repeat('OGGDATA', 50000);

        $this->assertSame($audio, app(MediaDownscaler::class)->shrink($audio, 'audio/ogg'));
    }

    public function test_unreadable_bytes_fall_back_to_the_original(): void
    {
        // Must never cost the customer their reply.
        $junk = str_repeat('not-an-image', 30000);

        $this->assertSame($junk, app(MediaDownscaler::class)->shrink($junk, 'image/jpeg'));
    }
}
