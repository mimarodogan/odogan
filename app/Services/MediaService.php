<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * Image upload + on-the-fly WebP/AVIF variants.
 *
 * Output layout (under public/uploads/YYYY/MM/):
 *   <hash>.jpg                  (or .png, normalized fallback)
 *   <hash>-320.webp / .avif
 *   <hash>-768.webp / .avif
 *   <hash>-1280.webp / .avif
 */
final class MediaService
{
    public const SIZES = [320, 768, 1280];
    public const WEBP_QUALITY = 80;
    public const AVIF_QUALITY = 62;
    public const JPEG_QUALITY = 86;
    public const MAX_BYTES = 8 * 1024 * 1024; // 8 MB
    private const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Absolute filesystem path to the web-served directory.
     * Falls back gracefully if older Config classes lack publicRoot().
     */
    private static function publicRoot(): string
    {
        // Even if Config::publicRoot() exists in production, it may be the OLD
        // version that returns /public whenever the dir exists — wrong on cPanel.
        // We compute it ourselves to guarantee files land where URLs resolve.
        $root = method_exists(Config::class, 'root') ? Config::root() : dirname(__DIR__, 2);
        if (is_file($root . '/index.php')) return $root;          // flat (cPanel)
        if (is_file($root . '/public/index.php')) return $root . '/public'; // stock
        return $root;
    }

    /**
     * @param array $file Entry from $_FILES.
     * @return array{ok:bool, error?:string, media?:array}
     */
    public static function uploadFromForm(array $file, ?int $userId = null, ?string $alt = null): array
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Yükleme hatası: kod ' . (int) ($file['error'] ?? -1)];
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'Dosya boyutu 0–8 MB arasında olmalı.'];
        }
        $tmp = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmp) && !is_file($tmp)) {
            return ['ok' => false, 'error' => 'Geçersiz yükleme.'];
        }
        $mime = (string) (mime_content_type($tmp) ?: ($file['type'] ?? ''));
        if (!isset(self::ALLOWED_MIMES[$mime])) {
            return ['ok' => false, 'error' => 'Sadece JPEG/PNG/WebP yükleyebilirsiniz.'];
        }
        return self::store($tmp, $mime, (string) ($file['name'] ?? 'upload'), $size, $userId, $alt);
    }

    private static function store(string $tmp, string $mime, string $origName, int $size, ?int $userId, ?string $alt): array
    {
        if (!extension_loaded('gd')) {
            return ['ok' => false, 'error' => 'Sunucuda GD eklentisi yok. Hosting sağlayıcınızdan GD\'yi etkinleştirmesini isteyin.'];
        }
        $img = self::loadImage($tmp, $mime);
        if ($img === null) {
            return ['ok' => false, 'error' => 'Görsel okunamadı (' . $mime . '). Dosya bozuk olabilir.'];
        }
        [$w, $h] = [imagesx($img), imagesy($img)];
        // SEO-friendly filename: slugify the alt text (or original filename),
        // truncate, then append a 5-digit random suffix to guarantee uniqueness.
        // Result e.g.: "mimari-tasarim-imar-83472.jpg"
        $hash = self::buildSlugName($alt, $origName);
        $relDir = 'uploads/' . date('Y/m');
        $absDir = self::publicRoot() . '/' . $relDir;
        if (!is_dir($absDir)) {
            if (!@mkdir($absDir, 0775, true) && !is_dir($absDir)) {
                imagedestroy($img);
                return ['ok' => false, 'error' => 'uploads/ klasörü oluşturulamadı: ' . $relDir . ' (izinleri kontrol edin: uploads/ dizinine 0775 ya da 0777 verin)'];
            }
        }
        if (!is_writable($absDir)) {
            imagedestroy($img);
            return ['ok' => false, 'error' => 'uploads/ klasörüne yazılamıyor: ' . $relDir . ' — cPanel\'de uploads/ dizinine 0775 izin verin'];
        }

        // Decide master format: WebP if GD supports it (modern hosts), else JPEG.
        $useWebp = function_exists('imagewebp');
        $masterExt = $useWebp ? 'webp' : 'jpg';
        $masterMime = $useWebp ? 'image/webp' : 'image/jpeg';
        $masterRel = $relDir . '/' . $hash . '.' . $masterExt;
        $masterAbs = self::publicRoot() . '/' . $masterRel;
        $writeOk = $useWebp
            ? @imagewebp($img, $masterAbs, self::WEBP_QUALITY)
            : @imagejpeg($img, $masterAbs, self::JPEG_QUALITY);
        if (!$writeOk) {
            imagedestroy($img);
            $err = error_get_last();
            return ['ok' => false, 'error' => strtoupper($masterExt) . ' yazılamadı: ' . ($err['message'] ?? 'bilinmeyen hata') . ' (' . $masterRel . ')'];
        }

        $variants = [];
        try {
            foreach (self::SIZES as $width) {
                if ($w <= $width && $width !== self::SIZES[count(self::SIZES) - 1]) {
                    continue; // skip upscaling, but always keep the largest tier
                }
                $variants[$width] = self::makeVariant($img, $w, $h, $width, $relDir, $hash);
            }
        } catch (\Throwable $e) {
            imagedestroy($img);
            return ['ok' => false, 'error' => 'Variant üretilirken hata: ' . $e->getMessage()];
        }

        // BlurHash üret — feature aktifse, GD image kapatılmadan önce.
        // Encode aşamasını try/catch ile sar — hata olursa null'a düşer, upload bozulmaz.
        $blurhash = null;
        if (function_exists('feature') && feature('blurhash_enabled')) {
            try {
                $blurhash = self::encodeBlurhash($img, $w, $h);
            } catch (\Throwable $e) {
                @error_log('[MediaService] BlurHash encode failed: ' . $e->getMessage());
                $blurhash = null;
            }
        }
        imagedestroy($img);

        $row = [
            'user_id' => $userId,
            'original_name' => mb_substr($origName, 0, 255),
            'path' => $masterRel,
            'mime' => $masterMime,
            'width' => $w,
            'height' => $h,
            // Diskteki GERÇEK (webp/jpeg master) boyutu — orijinal yükleme boyutu ($size) değil.
            'bytes' => (int) (@filesize($masterAbs) ?: $size),
            'variants_json' => (string) json_encode($variants, JSON_UNESCAPED_SLASHES),
            'blurhash' => $blurhash,
            'alt' => $alt !== null ? mb_substr($alt, 0, 255) : null,
        ];
        try {
            $id = (int) Database::instance()->insert('media', $row);
        } catch (\Throwable $e) {
            // Surface the real DB error to the client so we can diagnose
            // schema/permission mismatches on shared hosting.
            @error_log('[MediaService] DB insert failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Veritabanına kayıt başarısız: ' . $e->getMessage()];
        }
        if ($id <= 0) {
            return ['ok' => false, 'error' => 'Veritabanı insert id 0 döndü (media tablosu yok ya da AUTO_INCREMENT sorunu).'];
        }
        $row['id'] = $id;
        $row['variants'] = $variants;
        return ['ok' => true, 'media' => $row];
    }

    /**
     * @return array{webp:string, avif?:string, w:int, h:int}
     */
    private static function makeVariant(\GdImage $src, int $sw, int $sh, int $width, string $relDir, string $hash): array
    {
        $tw = min($width, $sw);
        $th = (int) round($sh * ($tw / $sw));
        $dst = imagecreatetruecolor($tw, $th);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $sw, $sh);
        $base = $relDir . '/' . $hash . '-' . $width;
        $absBase = self::publicRoot() . '/' . $base;
        $out = ['w' => $tw, 'h' => $th];
        // WebP is optional — some shared hosts ship GD without WebP support.
        if (function_exists('imagewebp')) {
            if (@imagewebp($dst, $absBase . '.webp', self::WEBP_QUALITY)) {
                $out['webp'] = $base . '.webp';
            }
        }
        if (function_exists('imageavif')) {
            if (@imageavif($dst, $absBase . '.avif', self::AVIF_QUALITY)) {
                $out['avif'] = $base . '.avif';
            }
        }
        // Always produce a JPEG fallback at this width so the variant is usable
        // even when neither WebP nor AVIF could be written.
        if (!isset($out['webp']) && !isset($out['avif'])) {
            if (@imagejpeg($dst, $absBase . '.jpg', self::JPEG_QUALITY)) {
                $out['jpg'] = $base . '.jpg';
            }
        }
        imagedestroy($dst);
        return $out;
    }

    /**
     * Build a SEO-friendly file basename from alt text (preferred) or the
     * original filename, with a short numeric suffix for uniqueness.
     * Returns just the basename (no extension), e.g. "mimari-tasarim-imar-83472".
     */
    private static function buildSlugName(?string $alt, string $origName): string
    {
        $source = '';
        if ($alt !== null && trim($alt) !== '') {
            $source = $alt;
        } else {
            // Drop extension from the original filename
            $source = (string) preg_replace('/\.[a-zA-Z0-9]+$/', '', $origName);
        }
        $slug = function_exists('slugify') ? slugify($source) : strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $source), '-'));
        if ($slug === '') {
            $slug = 'gorsel';
        }
        // Cap slug length so paths stay sane; keep word boundaries when possible.
        if (mb_strlen($slug) > 60) {
            $head = mb_substr($slug, 0, 60);
            $trimmed = (string) preg_replace('/-[^-]*$/', '', $head);
            $slug = $trimmed !== '' ? $trimmed : $head;
        }
        // 5-digit numeric suffix → 90 000 possible variants per slug per month.
        $relDir = 'uploads/' . date('Y/m');
        for ($i = 0; $i < 6; $i++) {
            $suffix = str_pad((string) random_int(10000, 99999), 5, '0', STR_PAD_LEFT);
            $candidate = $slug . '-' . $suffix;
            $base = self::publicRoot() . '/' . $relDir . '/' . $candidate;
            if (!file_exists($base . '.webp') && !file_exists($base . '.jpg')) {
                return $candidate;
            }
        }
        // Extreme fallback: timestamp-based suffix
        return $slug . '-' . date('YmdHis');
    }

    private static function loadImage(string $tmp, string $mime): ?\GdImage
    {
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmp),
            'image/png'  => @imagecreatefrompng($tmp),
            'image/webp' => @imagecreatefromwebp($tmp),
            default => false,
        };
        if ($img === false) {
            return null;
        }
        // Normalize PNG/WebP transparency onto white when we re-encode as JPEG.
        if ($mime === 'image/png' || $mime === 'image/webp') {
            $w = imagesx($img);
            $h = imagesy($img);
            $bg = imagecreatetruecolor($w, $h);
            $white = imagecolorallocate($bg, 255, 255, 255);
            imagefilledrectangle($bg, 0, 0, $w, $h, $white);
            imagealphablending($bg, true);
            imagecopy($bg, $img, 0, 0, 0, 0, $w, $h);
            imagedestroy($img);
            return $bg;
        }
        return $img;
    }

    public static function findById(int $id): ?array
    {
        $row = Database::instance()->fetch('SELECT * FROM media WHERE id = :id', [':id' => $id]);
        if ($row === null) {
            return null;
        }
        $row['variants'] = $row['variants_json']
            ? (array) json_decode((string) $row['variants_json'], true)
            : [];
        return $row;
    }

    /**
     * BlurHash encode — GD image'i 32×32 (max) küçült, RGB pixel array çıkar,
     * kornrunner/blurhash encode et. 4×3 component matris dengesi: hızlı + yumuşak.
     *
     * Performans notu: 32×32 grid downsample sayesinde encode <100ms büyük görselde.
     * Hata olursa exception fırlatır — caller try/catch ile yakalar.
     */
    private static function encodeBlurhash(\GdImage $src, int $srcW, int $srcH): ?string
    {
        if (!class_exists(\kornrunner\Blurhash\Blurhash::class)) {
            return null;
        }
        // Downsample to max 32×32 — encoder accuracy yeterli, perf çok daha iyi.
        $maxDim = 32;
        if ($srcW > $maxDim || $srcH > $maxDim) {
            $ratio = $srcW > $srcH ? $maxDim / $srcW : $maxDim / $srcH;
            $w = max(8, (int) round($srcW * $ratio));
            $h = max(8, (int) round($srcH * $ratio));
            $small = imagecreatetruecolor($w, $h);
            imagecopyresampled($small, $src, 0, 0, 0, 0, $w, $h, $srcW, $srcH);
        } else {
            $w = $srcW;
            $h = $srcH;
            $small = $src;
        }

        $pixels = [];
        for ($y = 0; $y < $h; $y++) {
            $row = [];
            for ($x = 0; $x < $w; $x++) {
                $rgb = imagecolorat($small, $x, $y);
                $row[] = [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];
            }
            $pixels[] = $row;
        }

        if ($small !== $src) {
            imagedestroy($small);
        }

        // 4 x 3 = 12 component dengeli; daha küçük = daha smooth, daha büyük = daha keskin
        $hash = \kornrunner\Blurhash\Blurhash::encode($pixels, 4, 3);
        return is_string($hash) && $hash !== '' ? mb_substr($hash, 0, 40) : null;
    }
}
