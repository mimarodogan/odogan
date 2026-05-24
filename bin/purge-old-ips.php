<?php
declare(strict_types=1);

/**
 * KVKK IP Retention Cron — eski log/yorum kayıtlarındaki IP adreslerini
 * anonimleştirir (NULL'a çevirir). Veri verisi kalır (analytics için),
 * sadece kişisel veri yan ürünü (IP) silinir.
 *
 * KVKK m.4 ve m.5: kişisel veriler "amaç sınırlılığı" ve "muhafaza
 * süresinin gerekli olanla sınırlı tutulması" ilkelerine tabidir.
 *
 * Saklama süreleri:
 *   - logs.ip_address           → 90 gün
 *   - comments.ip_address       → 180 gün (yasal tartışma için biraz daha)
 *   - consent_logs.ip_address   → 5 yıl (rıza ispat süresi — IP korunur)
 *   - login_attempts.ip_address → 30 gün (kaba kuvvet izleme için)
 *
 * Çağrı: bin/purge-old-ips.php (günde 1 kez cron tetiklemeli)
 *
 *   0 3 * * * /usr/bin/php /var/www/odogan.com.tr/bin/purge-old-ips.php
 *
 * Çıktı: silinen IP sayısı her tablo için.
 */

// CLI guard — web'den çağrılmasın
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Services\Logger;

$db = Database::instance();
$results = [];

// 1) logs.ip_address — 90 gün
try {
    $sql = 'UPDATE logs SET ip_address = NULL
            WHERE ip_address IS NOT NULL
              AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)';
    $stmt = $db->run($sql);
    $results['logs'] = $stmt->rowCount();
} catch (\Throwable $e) {
    $results['logs'] = 'ERROR: ' . $e->getMessage();
}

// 2) comments.ip_address — 180 gün (sadece onaylanmış yorumlar; spam/pending
//    incelemeye konu olabilir, eldeki delil saklansın)
try {
    $sql = "UPDATE comments SET ip_address = NULL
            WHERE ip_address IS NOT NULL
              AND status = 'approved'
              AND created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)";
    $stmt = $db->run($sql);
    $results['comments'] = $stmt->rowCount();
} catch (\Throwable $e) {
    $results['comments'] = 'ERROR: ' . $e->getMessage();
}

// 3) consent_logs.ip_address — 5 yıl
//    Tabloyu yeni oluşturduk; migration uygulanmadıysa hata vermeden geç.
try {
    $sql = 'UPDATE consent_logs SET ip_address = NULL
            WHERE ip_address IS NOT NULL
              AND created_at < DATE_SUB(NOW(), INTERVAL 5 YEAR)';
    $stmt = $db->run($sql);
    $results['consent_logs'] = $stmt->rowCount();
} catch (\Throwable $e) {
    $results['consent_logs'] = $e->getCode() === '42S02' ? 'SKIP: table missing' : ('ERROR: ' . $e->getMessage());
}

// 4) login_attempts.ip_address — 30 gün
try {
    $sql = 'DELETE FROM login_attempts
            WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)';
    $stmt = $db->run($sql);
    $results['login_attempts_deleted'] = $stmt->rowCount();
} catch (\Throwable $e) {
    $results['login_attempts_deleted'] = $e->getCode() === '42S02' ? 'SKIP: table missing' : ('ERROR: ' . $e->getMessage());
}

// Log + stdout
$summary = 'IP retention purge: ' . json_encode($results, JSON_UNESCAPED_UNICODE);
Logger::info('kvkk.ip_purge', $results, 'kvkk');

echo $summary . PHP_EOL;
exit(0);
