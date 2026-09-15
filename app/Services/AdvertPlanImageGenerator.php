<?php

namespace App\Services;

use App\Models\AdvertBooking;
use App\WhatsApp\AI\GeminiProvider;

/**
 * Renders the sponsored-advert packages as a single PNG "plans card" — sent by
 * the WhatsApp assistant so a customer sees the packages as a real graphic
 * instead of a wall of text.
 *
 * Drawn live with GD from config('adverts.packages') on every request (cheap —
 * five rows of text) rather than baked into a static asset, so a price change
 * in config/adverts.php is reflected immediately with nothing to regenerate or
 * go stale. Ships its own bundled Inter font (resources/fonts) since the
 * production image has no system fonts installed (see Dockerfile).
 */
class AdvertPlanImageGenerator
{
    private const WIDTH = 1080;

    private const GREEN = [11, 62, 9];

    private const ORANGE = [220, 113, 18];

    private const CREAM = [251, 248, 242];

    private const INK = [30, 34, 28];

    private const GRAY = [110, 116, 108];

    private const WHITE = [255, 255, 255];

    private string $bold;

    private string $regular;

    public function __construct()
    {
        $this->bold = resource_path('fonts/Inter-Bold.ttf');
        $this->regular = resource_path('fonts/Inter-Regular.ttf');
    }

    /** PNG bytes for the current packages, ready to serve or upload. */
    public function render(): string
    {
        $packages = AdvertBooking::packages();
        $site = GeminiProvider::siteName();

        $headerH = 280;
        $rowH = 250;
        $footerH = 150;
        $height = $headerH + (count($packages) * $rowH) + $footerH;

        $im = imagecreatetruecolor(self::WIDTH, $height);
        imageantialias($im, true);
        $this->fillRect($im, 0, 0, self::WIDTH, $height, self::CREAM);

        $this->drawHeader($im, $site, $headerH);

        $y = $headerH;
        foreach ($packages as $pkg) {
            $this->drawRow($im, $y, $rowH, $pkg);
            $y += $rowH;
        }

        $this->drawFooter($im, $y, $footerH, $site);

        ob_start();
        imagepng($im, null, 6);
        $bytes = (string) ob_get_clean();
        imagedestroy($im);

        return $bytes;
    }

    private function drawHeader($im, string $site, int $h): void
    {
        $this->fillRect($im, 0, 0, self::WIDTH, $h, self::GREEN);
        $white = $this->color($im, self::WHITE);
        $orange = $this->color($im, self::ORANGE);

        $this->text($im, $this->bold, 30, 60, 65, $white, mb_strtoupper($site));
        $this->text($im, $this->bold, 50, 60, 142, $white, 'Sponsored Advertising Plans');
        $this->wrappedText($im, $this->regular, 27, 60, 185, self::WIDTH - 120, $orange,
            'Put your business in front of new customers on Facebook & Instagram');
    }

    private function drawRow($im, int $y, int $h, array $pkg): void
    {
        $pad = 40;
        $recommended = ! empty($pkg['recommended']);
        $video = ! empty($pkg['includes_video']);

        // Card background, with an accent border on the recommended package.
        $border = $recommended ? self::ORANGE : [225, 221, 210];
        $this->fillRect($im, $pad, $y + 14, self::WIDTH - ($pad * 2), $h - 28, self::WHITE);
        $this->strokeRect($im, $pad, $y + 14, self::WIDTH - ($pad * 2), $h - 28, $border, $recommended ? 3 : 1);

        $inkColor = $this->color($im, self::INK);
        $grayColor = $this->color($im, self::GRAY);
        $orangeColor = $this->color($im, self::ORANGE);

        $left = $pad + 36;
        $label = (string) ($pkg['label'] ?? '');
        $this->text($im, $this->bold, 34, $left, $y + 66, $inkColor, $label);

        $price = '$'.number_format((float) ($pkg['price'] ?? 0), 2);
        $priceBox = imagettfbbox(44, 0, $this->bold, $price);
        $priceW = abs($priceBox[2] - $priceBox[0]);
        $this->text($im, $this->bold, 44, self::WIDTH - $pad - 36 - $priceW, $y + 66, $orangeColor, $price);

        $badgeY = $y + 90;
        $textTop = $y + 96; // where the blurb starts if there's no badge row
        if ($recommended || $video) {
            $bx = $left;
            if ($recommended) {
                $bx = $this->pill($im, $bx, $badgeY, 'MOST POPULAR', self::ORANGE, self::WHITE) + 16;
            }
            if ($video) {
                $this->pill($im, $bx, $badgeY, 'INCLUDES AI VIDEO', self::GREEN, self::WHITE);
            }
            $textTop = $badgeY + 62;
        }

        $blurb = (string) ($pkg['blurb'] ?? '');
        $this->wrappedText($im, $this->regular, 22, $left, $textTop, self::WIDTH - $left - $pad - 20, $grayColor, $blurb, 2);
    }

    private function drawFooter($im, int $y, int $h, string $site): void
    {
        $this->fillRect($im, 0, $y, self::WIDTH, $h, self::GREEN);
        $white = $this->color($im, self::WHITE);
        $orange = $this->color($im, self::ORANGE);
        $this->text($im, $this->bold, 34, 60, $y + 55, $orange, 'Reply "advertise" to book your campaign');
        $this->text($im, $this->regular, 24, 60, $y + 95, $white, $site.' — flat price, no hidden extras');
    }

    // ── Drawing primitives ──────────────────────────────────────────────────

    private function color($im, array $rgb): int
    {
        return (int) imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
    }

    private function fillRect($im, int $x, int $y, int $w, int $h, array $rgb): void
    {
        imagefilledrectangle($im, $x, $y, $x + $w, $y + $h, $this->color($im, $rgb));
    }

    private function strokeRect($im, int $x, int $y, int $w, int $h, array $rgb, int $thickness = 1): void
    {
        $c = $this->color($im, $rgb);
        for ($i = 0; $i < $thickness; $i++) {
            imagerectangle($im, $x + $i, $y + $i, $x + $w - $i, $y + $h - $i, $c);
        }
    }

    /** Baseline-anchored text (imagettftext draws from the text baseline, not the top). */
    private function text($im, string $font, int $size, int $x, int $y, int $color, string $text): void
    {
        imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
    }

    /**
     * A small rounded label — approximated with a filled rect (GD has no
     * native rounded-rect). Returns the pill's right edge x, so callers can
     * chain a second pill after it.
     */
    private function pill($im, int $x, int $y, string $label, array $bg, array $fg): int
    {
        $size = 18;
        $box = imagettfbbox($size, 0, $this->bold, $label);
        $w = abs($box[2] - $box[0]) + 32;
        $h = 38;
        $this->fillRect($im, $x, $y, $w, $h, $bg);
        $this->text($im, $this->bold, $size, $x + 16, $y + 25, $this->color($im, $fg), $label);

        return $x + $w;
    }

    /** Wraps $text to $maxWidth, drawing up to $maxLines lines (dropping the rest). */
    private function wrappedText($im, string $font, int $size, int $x, int $y, int $maxWidth, int $color, string $text, int $maxLines = 3): void
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            $box = imagettfbbox($size, 0, $font, $candidate);
            $w = abs($box[2] - $box[0]);
            if ($w > $maxWidth && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        $lines = array_slice($lines, 0, $maxLines);
        $lineHeight = (int) round($size * 1.5);
        foreach ($lines as $i => $line) {
            $this->text($im, $font, $size, $x, $y + ($i * $lineHeight), $color, $line);
        }
    }
}
