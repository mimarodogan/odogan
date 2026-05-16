<?php
/**
 * Geriye yönelik XSS temizliği — `projects.description` kolonu.
 *
 * Admin/Project formu YENİ kayıtlarda `Sanitizer::clean()` uyguluyor
 * (app/Controllers/Admin/ProjectController.php). Bu CLI, sanitizer'ın
 * eklenmesinden ÖNCE girilmiş eski kayıtları aynı allow-list'ten geçirir.
 *
 * Kullanım:
 *   php bin/sanitize-project-descriptions.php --dry-run   # sadece raporla
 *   php bin/sanitize-project-descriptions.php             # gerçek update
 *   php bin/sanitize-project-descriptions.php --limit=500 # tek seferde kaç kayıt
 *   php bin/sanitize-project-descriptions.php --verbose   # her satırın diff'i
 *
 * Çıktı: "X kayıt incelendi, Y tanesi güncellendi"
 *
 * Exit codes:
 *   0 = başarılı (tüm kayıtlar temiz veya update başarılı)
 *   1 = Sanitizer sınıfı yok
 *   2 = DB bağlantısı yok
 *
 * Deploy sonrası BİR KEZ çalıştırılması yeterlidir; tekrar çalıştırmak
 * idempotent — temiz description'lar değişmeden geçer.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/bootstrap.php';

use App\Core\Database;
use App\Services\Logger;
use App\Services\Sanitizer;

if (!class_exists(Sanitizer::class)) {
    fwrite(STDERR, "Hata: App\\Services\\Sanitizer sınıfı bulunamadı.\n");
    exit(1);
}

$opts = getopt('', ['dry-run', 'limit::', 'verbose']);
$dryRun  = array_key_exists('dry-run', $opts);
$verbose = array_key_exists('verbose', $opts);
$limit   = (int) ($opts['limit'] ?? 1000);
if ($limit < 1) {
    $limit = 1000;
}

try {
    $db = Database::instance();
} catch (\Throwable $e) {
    fwrite(STDERR, "DB hatası: " . $e->getMessage() . "\n");
    exit(2);
}

$mode = $dryRun ? 'DRY-RUN' : 'LIVE';
echo "[sanitize-project-descriptions] mode=$mode limit=$limit\n";

try {
    $rows = $db->fetchAll(
        'SELECT id, name, description FROM projects
         WHERE description IS NOT NULL AND description <> ""
         ORDER BY id ASC LIMIT ' . $limit
    );
} catch (\Throwable $e) {
    fwrite(STDERR, "Sorgu hatası: " . $e->getMessage() . "\n");
    exit(2);
}

$count   = count($rows);
$updated = 0;
$clean   = 0;
$errors  = 0;

foreach ($rows as $i => $row) {
    $id   = (int) $row['id'];
    $name = (string) ($row['name'] ?? '');
    $raw  = (string) $row['description'];

    try {
        $sanitized = Sanitizer::clean($raw);
    } catch (\Throwable $e) {
        $errors++;
        fwrite(STDERR, "[$i/$count] #$id ERR: " . $e->getMessage() . "\n");
        continue;
    }

    if ($sanitized === $raw) {
        $clean++;
        if ($verbose) {
            echo "[$i/$count] #$id OK (zaten temiz) — $name\n";
        }
        continue;
    }

    if ($dryRun) {
        $diff = mb_strlen($raw) - mb_strlen($sanitized);
        echo "[$i/$count] #$id DIFF ({$diff}b kaldırılacak) — $name\n";
        if ($verbose) {
            echo "  - eski: " . mb_substr($raw, 0, 200) . (mb_strlen($raw) > 200 ? '…' : '') . "\n";
            echo "  + yeni: " . mb_substr($sanitized, 0, 200) . (mb_strlen($sanitized) > 200 ? '…' : '') . "\n";
        }
        $updated++;
        continue;
    }

    try {
        $db->update(
            'projects',
            ['description' => $sanitized],
            'id = :wid',
            [':wid' => $id]
        );
        $updated++;
        echo "[$i/$count] #$id UPDATED — $name\n";
        try {
            Logger::info('security.retroactive_sanitize', [
                'table' => 'projects',
                'column' => 'description',
                'id' => $id,
                'before_len' => mb_strlen($raw),
                'after_len'  => mb_strlen($sanitized),
            ], 'security');
        } catch (\Throwable) {
            // Logger çökerse migration durmasın.
        }
    } catch (\Throwable $e) {
        $errors++;
        fwrite(STDERR, "[$i/$count] #$id ERR UPDATE: " . $e->getMessage() . "\n");
    }
}

echo "\n=== Tamamlandı ($mode) ===\n";
echo "İncelenen   : $count kayıt\n";
echo "Zaten temiz : $clean\n";
echo "Güncellenen : $updated" . ($dryRun ? ' (dry-run — gerçek update YOK)' : '') . "\n";
echo "Hatalı      : $errors\n";

if (!$dryRun && $updated > 0) {
    try {
        Logger::info('security.retroactive_sanitize.summary', [
            'table' => 'projects',
            'column' => 'description',
            'reviewed' => $count,
            'updated' => $updated,
            'clean' => $clean,
            'errors' => $errors,
        ], 'security');
    } catch (\Throwable) {}
}

exit($errors > 0 && $updated === 0 ? 1 : 0);
