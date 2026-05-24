<?php
/**
 * SMTP password migration — settings tablosundaki `mail.password` plaintext'i
 * Crypto::encrypt() ile yerine yaz.
 *
 * MailService::loadConfig() yeni kayıt save'lerinde otomatik şifreliyor;
 * bu CLI, şifrelemeden ÖNCE girilmiş eski satırı bir kez migrate eder.
 *
 * Davranış (idempotent):
 *   - Değer boşsa → skip
 *   - `enc:v1:` ile başlıyorsa → zaten şifreli, skip
 *   - Plaintext ise → Crypto::encrypt(...) ile yerine yaz
 *
 * Kullanım:
 *   php bin/encrypt-smtp-password.php --dry-run
 *   php bin/encrypt-smtp-password.php
 *
 * Exit codes:
 *   0 = OK (migrated veya skip)
 *   1 = Crypto kütüphanesi yok / APP_KEY eksik
 *   2 = DB hatası
 *   3 = Mail.password ayarı hiç tanımlı değil (önce admin panelden bir kez SMTP doldur)
 *
 * Deploy sonrası BİR KEZ çalıştırılır; sonraki save'ler otomatik şifreli.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/bootstrap.php';

use App\Core\Database;
use App\Services\Crypto;
use App\Services\Logger;

if (!class_exists(Crypto::class)) {
    fwrite(STDERR, "Hata: App\\Services\\Crypto bulunamadı.\n");
    exit(1);
}

$opts = getopt('', ['dry-run']);
$dryRun = array_key_exists('dry-run', $opts);
$mode = $dryRun ? 'DRY-RUN' : 'LIVE';

// APP_KEY var mı?
try {
    // Boş bir round-trip ile key doğrulaması
    $probe = Crypto::encrypt('probe');
    if (Crypto::decrypt($probe) !== 'probe') {
        throw new \RuntimeException('Crypto self-test başarısız.');
    }
} catch (\Throwable $e) {
    fwrite(STDERR, "Hata: " . $e->getMessage() . "\n");
    fwrite(STDERR, "APP_KEY'i .env'de tanımla:\n  openssl rand -base64 32\n");
    exit(1);
}

try {
    $db = Database::instance();
} catch (\Throwable $e) {
    fwrite(STDERR, "DB hatası: " . $e->getMessage() . "\n");
    exit(2);
}

// settings tablosunda mail.password var mı?
$row = null;
try {
    $row = $db->fetch(
        'SELECT id, value FROM settings WHERE group_name = :g AND key_name = :k LIMIT 1',
        [':g' => 'mail', ':k' => 'password']
    );
} catch (\Throwable $e) {
    fwrite(STDERR, "Sorgu hatası: " . $e->getMessage() . "\n");
    exit(2);
}

if ($row === null) {
    echo "[encrypt-smtp-password] $mode\n";
    echo "Ayar bulunamadı: settings.mail.password\n";
    echo "Önce admin panelden (/admin/mail) SMTP parolasını bir kez kaydet.\n";
    exit(3);
}

$value = (string) ($row['value'] ?? '');
$id    = (int) $row['id'];

echo "[encrypt-smtp-password] mode=$mode settings.id=$id\n";

if ($value === '') {
    echo "Değer boş — yapılacak iş yok.\n";
    exit(0);
}

if (Crypto::isEncrypted($value)) {
    echo "Zaten şifreli (enc:v1:…) — skip.\n";
    exit(0);
}

// Plaintext — şifrele
try {
    $encrypted = Crypto::encrypt($value);
} catch (\Throwable $e) {
    fwrite(STDERR, "Encrypt hatası: " . $e->getMessage() . "\n");
    exit(1);
}

// Self-verify
if (Crypto::decrypt($encrypted) !== $value) {
    fwrite(STDERR, "Encrypt/decrypt round-trip uyumsuz. Migration iptal.\n");
    exit(1);
}

$beforeLen = strlen($value);
$afterLen  = strlen($encrypted);
echo "Plaintext tespit edildi ($beforeLen byte) → şifreli envelope ($afterLen byte).\n";

if ($dryRun) {
    echo "DRY-RUN — DB değişmedi. Gerçek migration için --dry-run flag'ını kaldır.\n";
    exit(0);
}

try {
    $db->update(
        'settings',
        ['value' => $encrypted],
        'id = :wid',
        [':wid' => $id]
    );
} catch (\Throwable $e) {
    fwrite(STDERR, "Update hatası: " . $e->getMessage() . "\n");
    exit(2);
}

echo "OK — settings.id=$id şifrelendi.\n";

try {
    Logger::info('security.smtp_password_encrypted', [
        'settings_id' => $id,
        'before_len' => $beforeLen,
        'after_len' => $afterLen,
    ], 'security');
} catch (\Throwable) {}

exit(0);
