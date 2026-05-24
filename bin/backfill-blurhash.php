<?php
/**
 * BlurHash backfill — `media` tablosundaki blurhash IS NULL olan eski kayıtlar için
 * GD ile dosyayı oku, BlurHash üret, kolonu güncelle.
 *
 * Kullanım:
 *   php bin/backfill-blurhash.php           # tüm eksik kayıtlar (limit 200)
 *   php bin/backfill-blurhash.php --limit=50
 *   php bin/backfill-blurhash.php --force   # blurhash dolu olanları da yeniden üret
 *
 * Çıktı: işlenen kayıt sayısı + atlanan/hata raporu.
 *
 * Exit codes:
 *   0 = başarılı
 *   1 = GD eksik veya hash sınıfı yok
 *   2 = DB bağlantısı yok
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/bootstrap.php';

use App\Core\Database;

if (!extension_loaded('gd')) {
    fwrite(STDERR, "Hata: GD eklentisi yok.\n");
    exit(1);
}
if (!class_exists(\kornrunner\Blurhash\Blurhash::class)) {
    fwrite(STDERR, "Hata: kornrunner/blurhash paketi yüklü değil. composer require kornrunner/blurhash\n");
    exit(1);
}

$opts = getopt('', ['limit::', 'force']);
$limit = (int) ($opts['limit'] ?? 200);
$force = isset($opts['force']);

try {
    $db = Database::instance();
} catch (\Throwable $e) {
    fwrite(STDERR, "DB hatası: " . $e->getMessage() . "\n");
    exit(2);
}

$sql = $force
    ? 'SELECT id, path, mime FROM media ORDER BY id DESC LIMIT ' . max(1, $limit)
    : 'SELECT id, path, mime FROM media WHERE blurhash IS NULL ORDER BY id DESC LIMIT ' . max(1, $limit);

$rows = $db->fetchAll($sql);
$count = count($rows);
$publicRoot = realpath($root . '/public') ?: ($root . '/public');

$updated = 0;
$skipped = 0;
$errors  = 0;

foreach ($rows as $i => $row) {
    $abs = $publicRoot . '/' . ltrim((string) $row['path'], '/');
    if (!is_file($abs)) {
        $skipped++;
        echo "[$i/$count] SKIP (file missing): {$row['path']}\n";
        continue;
    }
    $mime = (string) $row['mime'];
    $img = null;
    try {
        switch ($mime) {
            case 'image/webp':
                $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($abs) : null;
                break;
            case 'image/jpeg':
                $img = @imagecreatefromjpeg($abs);
                break;
            case 'image/png':
                $img = @imagecreatefrompng($abs);
                break;
            default:
                // Try jpeg as last resort
                $img = @imagecreatefromjpeg($abs);
        }
        if (!$img) {
            $skipped++;
            echo "[$i/$count] SKIP (gd decode failed): {$row['path']}\n";
            continue;
        }
        $sw = imagesx($img);
        $sh = imagesy($img);
        // Downsample to 32×32
        $maxDim = 32;
        if ($sw > $maxDim || $sh > $maxDim) {
            $ratio = $sw > $sh ? $maxDim / $sw : $maxDim / $sh;
            $w = max(8, (int) round($sw * $ratio));
            $h = max(8, (int) round($sh * $ratio));
            $small = imagecreatetruecolor($w, $h);
            imagecopyresampled($small, $img, 0, 0, 0, 0, $w, $h, $sw, $sh);
        } else {
            $w = $sw; $h = $sh; $small = $img;
        }
        $pixels = [];
        for ($y = 0; $y < $h; $y++) {
            $r = [];
            for ($x = 0; $x < $w; $x++) {
                $rgb = imagecolorat($small, $x, $y);
                $r[] = [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];
            }
            $pixels[] = $r;
        }
        if ($small !== $img) imagedestroy($small);
        imagedestroy($img);

        $hash = \kornrunner\Blurhash\Blurhash::encode($pixels, 4, 3);
        if (!is_string($hash) || $hash === '') {
            $errors++;
            echo "[$i/$count] ERR encode failed: {$row['path']}\n";
            continue;
        }
        $db->update('media', ['blurhash' => mb_substr($hash, 0, 40)], 'id = :wid', [':wid' => (int) $row['id']]);
        $updated++;
        echo "[$i/$count] OK #{$row['id']} → $hash\n";
    } catch (\Throwable $e) {
        $errors++;
        echo "[$i/$count] ERR " . $e->getMessage() . "\n";
        if ($img) @imagedestroy($img);
    }
}

echo "\n=== Backfill tamamlandı ===\n";
echo "İncelenen : $count\n";
echo "Güncellenen: $updated\n";
echo "Atlanan   : $skipped\n";
echo "Hatalı    : $errors\n";
exit($errors > 0 ? 0 : 0);
