<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\Setting;

/**
 * OG image (1200×630 PNG) otomatik üretim.
 *
 * Strateji:
 *  - Post'ta cover_image varsa → onu kullan + üst overlay + başlık
 *  - Cover yoksa → cobalt gradient + başlık + brand
 *
 * Cache: {publicRoot}/uploads/og/post-{id}-{mtime}.png
 * mtime invalidation — post update edilirse cache otomatik bayatlar.
 *
 * Web URL: /uploads/og/post-{id}-{mtime}.png
 *
 * GD eklentisi gerekir. Imagick varsa daha iyi font rendering.
 */
final class OgImageGenerator
{
    private const WIDTH = 1200;
    private const HEIGHT = 630;
    private const URL_SUBPATH = '/uploads/og';

    /**
     * Bir post için OG image URL'i sağlar. Cache'te yoksa üretir.
     * Başarısız olursa null döner (caller fallback'e düşer).
     */
    public static function ensureFor(array $post): ?string
    {
        if (!extension_loaded('gd')) {
            return null;
        }
        $postId = (int) ($post['id'] ?? 0);
        $title  = trim((string) ($post['title'] ?? ''));
        if ($postId <= 0 || $title === '') {
            return null;
        }
        $mtime = strtotime((string) ($post['updated_at'] ?? 'now')) ?: time();

        $cacheDir = Config::publicRoot() . self::URL_SUBPATH;
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        $filename = sprintf('post-%d-%d.png', $postId, $mtime);
        $filepath = $cacheDir . '/' . $filename;

        // Eski mtime cache'leri sil (housekeeping)
        self::pruneOld($cacheDir, $postId, $mtime);

        if (!is_file($filepath)) {
            $ok = self::generate($filepath, $post);
            if (!$ok) {
                return null;
            }
        }

        return self::URL_SUBPATH . '/' . $filename;
    }

    /**
     * Image'i üret. Başarılıysa true.
     */
    private static function generate(string $filepath, array $post): bool
    {
        $img = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        if ($img === false) return false;

        // 1) Arka plan
        $coverPath = self::resolveCoverPath((string) ($post['cover_image'] ?? ''));
        if ($coverPath && self::drawCover($img, $coverPath)) {
            // overlay (yarı saydam koyu)
            $overlay = imagecolorallocatealpha($img, 0, 0, 0, 50); // ~%60 opaklık
            imagefilledrectangle($img, 0, 0, self::WIDTH, self::HEIGHT, $overlay);
        } else {
            // Cobalt gradient fallback
            self::drawGradient($img);
        }

        // 2) Başlık
        $title = (string) ($post['title'] ?? '');
        self::drawTitle($img, $title);

        // 3) Brand + author
        $brand = (string) Setting::get('site_name', Config::get('APP_NAME', 'Odogan'));
        $author = (string) ($post['author_name'] ?? '');
        self::drawFooter($img, $brand, $author);

        $ok = imagepng($img, $filepath, 6); // compression 6/9
        imagedestroy($img);
        return $ok;
    }

    private static function resolveCoverPath(string $coverRel): ?string
    {
        if ($coverRel === '') return null;
        $coverRel = ltrim($coverRel, '/');
        $candidates = [
            Config::root() . '/' . $coverRel,
            Config::root() . '/public/' . $coverRel,
        ];
        foreach ($candidates as $p) {
            if (is_file($p)) return $p;
        }
        return null;
    }

    private static function drawCover(\GdImage $img, string $path): bool
    {
        $info = @getimagesize($path);
        if (!$info) return false;
        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default        => false,
        };
        if (!$src) return false;

        // Cover'ı 1200x630'a fit et (object-fit: cover gibi)
        $sw = imagesx($src);
        $sh = imagesy($src);
        $ratio = max(self::WIDTH / $sw, self::HEIGHT / $sh);
        $dw = (int) ($sw * $ratio);
        $dh = (int) ($sh * $ratio);
        $dx = (int) ((self::WIDTH - $dw) / 2);
        $dy = (int) ((self::HEIGHT - $dh) / 2);
        imagecopyresampled($img, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
        imagedestroy($src);
        return true;
    }

    private static function drawGradient(\GdImage $img): void
    {
        // Top-left cobalt → bottom-right midnight
        $c1 = [0x1F, 0x3A, 0x8A]; // cobalt
        $c2 = [0x0F, 0x1C, 0x4D]; // midnight
        for ($y = 0; $y < self::HEIGHT; $y++) {
            $t = $y / self::HEIGHT;
            $r = (int) ($c1[0] + ($c2[0] - $c1[0]) * $t);
            $g = (int) ($c1[1] + ($c2[1] - $c1[1]) * $t);
            $b = (int) ($c1[2] + ($c2[2] - $c1[2]) * $t);
            $col = imagecolorallocate($img, $r, $g, $b);
            imageline($img, 0, $y, self::WIDTH, $y, $col);
        }
    }

    private static function drawTitle(\GdImage $img, string $title): void
    {
        $white = imagecolorallocate($img, 255, 255, 255);
        $font = self::findFont();
        $title = mb_substr($title, 0, 220);

        if ($font !== null) {
            // 64pt başlık, max 3 satır
            $lines = self::wrapText($title, $font, 56, self::WIDTH - 160);
            $lines = array_slice($lines, 0, 3);
            $lineHeight = 80;
            $totalH = count($lines) * $lineHeight;
            $startY = (int) ((self::HEIGHT - $totalH) / 2);
            foreach ($lines as $i => $line) {
                imagettftext($img, 56, 0, 80, $startY + ($i + 1) * $lineHeight - 18, $white, $font, $line);
            }
        } else {
            // Fallback: builtin font (5 = largest, ~12px)
            $maxChars = 60;
            $lines = explode("\n", wordwrap($title, $maxChars, "\n", true));
            $lines = array_slice($lines, 0, 4);
            $startY = (int) (self::HEIGHT / 2 - count($lines) * 18 / 2);
            foreach ($lines as $i => $line) {
                imagestring($img, 5, 80, $startY + $i * 22, $line, $white);
            }
        }
    }

    private static function drawFooter(\GdImage $img, string $brand, string $author): void
    {
        $cream = imagecolorallocate($img, 240, 234, 220);
        $font = self::findFont();
        $line = $author !== '' ? ($author . '  ·  ' . $brand) : $brand;

        if ($font !== null) {
            imagettftext($img, 24, 0, 80, self::HEIGHT - 50, $cream, $font, $line);
        } else {
            imagestring($img, 4, 80, self::HEIGHT - 60, $line, $cream);
        }
    }

    /**
     * Bilinen yerlerde TTF font ara.
     */
    private static function findFont(): ?string
    {
        $candidates = [
            Config::root() . '/assets/fonts/og.ttf',
            Config::root() . '/assets/fonts/og-font.ttf',
            // Linux yaygın
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSerif-Bold.ttf',
            // macOS sistem
            '/System/Library/Fonts/Supplemental/Times New Roman Bold.ttf',
            '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
            '/System/Library/Fonts/Helvetica.ttc',
        ];
        foreach ($candidates as $f) {
            if (is_file($f)) return $f;
        }
        return null;
    }

    /**
     * Text wrap (word boundaries) — TTF font metrik ile.
     * @return string[]
     */
    private static function wrapText(string $text, string $font, int $size, int $maxWidth): array
    {
        $words = preg_split('/\s+/u', $text) ?: [];
        $lines = [];
        $current = '';
        foreach ($words as $w) {
            $try = $current === '' ? $w : ($current . ' ' . $w);
            $bbox = @imagettfbbox($size, 0, $font, $try);
            $w_ = $bbox ? ($bbox[2] - $bbox[0]) : (strlen($try) * 30);
            if ($w_ > $maxWidth && $current !== '') {
                $lines[] = $current;
                $current = $w;
            } else {
                $current = $try;
            }
        }
        if ($current !== '') $lines[] = $current;
        return $lines;
    }

    /**
     * Bir post için eski mtime cache dosyalarını sil.
     */
    private static function pruneOld(string $cacheDir, int $postId, int $currentMtime): void
    {
        foreach (glob($cacheDir . '/post-' . $postId . '-*.png') ?: [] as $f) {
            $base = basename($f, '.png');
            $parts = explode('-', $base);
            $mtime = (int) end($parts);
            if ($mtime !== $currentMtime) {
                @unlink($f);
            }
        }
    }
}
