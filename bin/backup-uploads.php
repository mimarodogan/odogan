<?php
/**
 * Uploads klasörü yedekleme — haftalık cron önerilir (büyük dosyalar nedeniyle).
 *
 * Cron örneği (cPanel, Pazar 04:00):
 *   0 4 * * 0 /usr/bin/php /home/user/public_html/bin/backup-uploads.php
 *
 * Çıktı: storage/backups/uploads-YYYY-MM-DD.tar.gz
 * Retention: 30 günden eski dosyalar silinir.
 *
 * Kaynak konumu otomatik bulunur:
 *   - public/uploads (stock layout)
 *   - uploads        (cPanel flat layout)
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/bootstrap.php';

// Uploads dizini nerede?
$uploadsCandidates = [
    $root . '/public/uploads',
    $root . '/uploads',
];
$uploads = null;
foreach ($uploadsCandidates as $p) {
    if (is_dir($p)) { $uploads = $p; break; }
}
if ($uploads === null) {
    fwrite(STDERR, "[backup-uploads] uploads dizini bulunamadı.\n");
    exit(2);
}

$backupDir = $root . '/storage/backups';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0775, true);
}
if (!is_writable($backupDir)) {
    fwrite(STDERR, "[backup-uploads] $backupDir yazılabilir değil.\n");
    exit(3);
}

$stamp = date('Y-m-d');
$outFile = $backupDir . "/uploads-$stamp.tar.gz";

// tar bul
$tar = trim((string) shell_exec('which tar 2>/dev/null'));
if ($tar === '') {
    foreach (['/usr/bin/tar', '/bin/tar'] as $p) {
        if (is_executable($p)) { $tar = $p; break; }
    }
}
if ($tar === '') {
    fwrite(STDERR, "[backup-uploads] tar bulunamadı.\n");
    exit(1);
}

// tar -czf out.tar.gz -C <parent> <basename>
$parent = dirname($uploads);
$base   = basename($uploads);
$cmd = sprintf(
    '%s -czf %s -C %s %s',
    escapeshellarg($tar),
    escapeshellarg($outFile),
    escapeshellarg($parent),
    escapeshellarg($base)
);

$rc = 0;
$output = [];
exec($cmd . ' 2>&1', $output, $rc);

if ($rc !== 0 || !is_file($outFile) || filesize($outFile) < 64) {
    fwrite(STDERR, "[backup-uploads] tar FAILED (rc=$rc): " . implode("\n", $output) . "\n");
    @unlink($outFile);
    exit(1);
}

$size = filesize($outFile);
echo "[backup-uploads] OK $outFile ($size bytes)\n";

// Retention
$cutoff = time() - (30 * 86400);
$purged = 0;
foreach (glob($backupDir . '/uploads-*.tar.gz') ?: [] as $f) {
    if (@filemtime($f) < $cutoff) {
        @unlink($f) && $purged++;
    }
}
if ($purged > 0) {
    echo "[backup-uploads] purged $purged eski yedek\n";
}

try {
    \App\Services\Logger::info('backup.uploads.ok', [
        'file' => basename($outFile),
        'size' => $size,
        'purged' => $purged,
    ], 'backup');
} catch (\Throwable) {}

exit(0);
