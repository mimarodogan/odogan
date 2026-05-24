<?php
/**
 * Database yedekleme — gece cron veya admin panelden manuel çalıştırılır.
 *
 * Cron örneği (cPanel):
 *   0 3 * * * /usr/bin/php /home/user/public_html/bin/backup-db.php
 *
 * Çıktı: storage/backups/db-YYYY-MM-DD-HHMM.sql.gz
 * Retention: 30 günden eski dosyalar silinir.
 *
 * Exit codes:
 *   0 = başarılı
 *   1 = mysqldump bulunamadı veya komut hatası
 *   2 = DB credentials yüklenemedi
 *   3 = backup dizini yazılabilir değil
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/bootstrap.php';

$cfg = (array) require $root . '/config/db.php';
$host = (string) ($cfg['host'] ?? '127.0.0.1');
$port = (int)    ($cfg['port'] ?? 3306);
$db   = (string) ($cfg['database'] ?? '');
$user = (string) ($cfg['username'] ?? '');
$pass = (string) ($cfg['password'] ?? '');

if ($db === '' || $user === '') {
    fwrite(STDERR, "[backup-db] DB credentials eksik (config/db.php / .env).\n");
    exit(2);
}

$backupDir = $root . '/storage/backups';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0775, true);
}
if (!is_writable($backupDir)) {
    fwrite(STDERR, "[backup-db] $backupDir yazılabilir değil.\n");
    exit(3);
}

$stamp = date('Y-m-d-Hi');
$outFile = $backupDir . "/db-$stamp.sql.gz";

// mysqldump bul
$mysqldump = trim((string) shell_exec('which mysqldump 2>/dev/null'));
if ($mysqldump === '') {
    // Yaygın yollarda ara
    foreach (['/usr/bin/mysqldump', '/usr/local/bin/mysqldump', '/opt/homebrew/bin/mysqldump'] as $p) {
        if (is_executable($p)) { $mysqldump = $p; break; }
    }
}
if ($mysqldump === '') {
    fwrite(STDERR, "[backup-db] mysqldump bulunamadı. PATH'i kontrol edin.\n");
    exit(1);
}

// Komutu güvenli kur. Password env değişkeni ile geçilir (komut satırında parlamasın).
$cmd = sprintf(
    'MYSQL_PWD=%s %s --single-transaction --quick --skip-lock-tables --default-character-set=utf8mb4 -h %s -P %d -u %s %s | gzip -9 > %s',
    escapeshellarg($pass),
    escapeshellarg($mysqldump),
    escapeshellarg($host),
    $port,
    escapeshellarg($user),
    escapeshellarg($db),
    escapeshellarg($outFile)
);

$rc = 0;
$output = [];
exec($cmd . ' 2>&1', $output, $rc);

if ($rc !== 0 || !is_file($outFile) || filesize($outFile) < 256) {
    fwrite(STDERR, "[backup-db] mysqldump FAILED (rc=$rc): " . implode("\n", $output) . "\n");
    @unlink($outFile);
    exit(1);
}

$size = filesize($outFile);
echo "[backup-db] OK $outFile ($size bytes)\n";

// Retention: 30 günden eski db-*.sql.gz dosyaları sil
$cutoff = time() - (30 * 86400);
$purged = 0;
foreach (glob($backupDir . '/db-*.sql.gz') ?: [] as $f) {
    if (@filemtime($f) < $cutoff) {
        @unlink($f) && $purged++;
    }
}
if ($purged > 0) {
    echo "[backup-db] purged $purged eski yedek\n";
}

// Logger varsa kaydet
try {
    \App\Services\Logger::info('backup.db.ok', [
        'file' => basename($outFile),
        'size' => $size,
        'purged' => $purged,
    ], 'backup');
} catch (\Throwable) {}

exit(0);
