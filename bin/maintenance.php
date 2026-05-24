<?php
/**
 * Maintenance mode toggle.
 *
 * `storage/.maintenance` adlı bir bayrak dosyası varsa bootstrap.php
 * tüm web isteklerine 503 + maintenance view döndürür (CLI etkilenmez).
 *
 * Kullanım:
 *   php bin/maintenance.php on                  # bayrağı oluştur (boş mesaj)
 *   php bin/maintenance.php on "DB upgrade sürüyor — 10 dk"  # custom mesaj
 *   php bin/maintenance.php off                 # bayrağı sil
 *   php bin/maintenance.php status              # durumu yazdır
 *
 * Exit codes:
 *   0 = OK
 *   1 = Yanlış kullanım
 *   2 = Storage dizini yazılabilir değil
 *
 * bootstrap.php integration snippet (uygulanması GEREKİR — bkz. rapor):
 *
 *     if (PHP_SAPI !== 'cli' && is_file(__DIR__ . '/storage/.maintenance')) {
 *         http_response_code(503);
 *         header('Retry-After: 600');
 *         header('Content-Type: text/html; charset=utf-8');
 *         require __DIR__ . '/app/Views/errors/503-maintenance.php';
 *         exit;
 *     }
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$flag = $root . '/storage/.maintenance';
$dir  = dirname($flag);

if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
}
if (!is_dir($dir) || !is_writable($dir)) {
    fwrite(STDERR, "[maintenance] $dir yazılabilir değil.\n");
    exit(2);
}

$action  = $argv[1] ?? '';
$message = $argv[2] ?? '';

switch ($action) {
    case 'on':
    case 'enable':
        $body = $message !== '' ? $message . "\n" : '';
        if (@file_put_contents($flag, $body, LOCK_EX) === false) {
            fwrite(STDERR, "[maintenance] Bayrak yazılamadı: $flag\n");
            exit(2);
        }
        echo "[maintenance] AÇIK — site 503 dönecek.\n";
        echo "Flag: $flag\n";
        if ($message !== '') {
            echo "Mesaj: $message\n";
        }
        echo "Kapatmak için: php bin/maintenance.php off\n";
        exit(0);

    case 'off':
    case 'disable':
        if (!is_file($flag)) {
            echo "[maintenance] Zaten KAPALI.\n";
            exit(0);
        }
        if (!@unlink($flag)) {
            fwrite(STDERR, "[maintenance] Bayrak silinemedi: $flag\n");
            exit(2);
        }
        echo "[maintenance] KAPALI — site normal hizmette.\n";
        exit(0);

    case 'status':
    case '':
        if (is_file($flag)) {
            $mtime = @filemtime($flag);
            $age = $mtime ? round((time() - $mtime) / 60, 1) : 0;
            $msg = trim((string) @file_get_contents($flag));
            echo "[maintenance] DURUM: AÇIK ({$age} dk önce başladı)\n";
            if ($msg !== '') {
                echo "Mesaj: $msg\n";
            }
        } else {
            echo "[maintenance] DURUM: KAPALI\n";
        }
        exit(0);

    default:
        fwrite(STDERR, "Kullanım: php bin/maintenance.php {on|off|status} [\"mesaj\"]\n");
        exit(1);
}
