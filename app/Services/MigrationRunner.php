<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use Throwable;

/**
 * `database/migrations/*.sql` dosyalarını sıralı olarak çalıştırır.
 *
 * - `migrations` tablosu (000_migrations.sql) kayıt tutar.
 * - Her dosya bağımsız — DDL (CREATE/ALTER) statement'ları transaction
 *   destekleyemez, o yüzden statement-by-statement çalıştırılır.
 * - Bir statement fail ederse o dosya "failed" olarak işaretlenir; sonraki
 *   dosyalar çalıştırılmaz (zincir kırılır).
 * - Başarılı olan statement'lar veritabanında kalır (rollback yok — bu DDL
 *   doğası gereği; idempotent yapılar için IF NOT EXISTS kullanılır).
 */
final class MigrationRunner
{
    private const DIR = '/database/migrations';

    /**
     * Tüm migration dosyalarını listele + durum bilgisiyle birlikte döner.
     *
     * @return array<int, array{
     *   name: string,
     *   applied: bool,
     *   executed_at: ?string,
     *   batch: ?int,
     *   size: int
     * }>
     */
    public static function list(): array
    {
        self::ensureMigrationsTable();

        // Dosyalar
        $files = self::filesOnDisk();

        // DB'de kayıtlı olanlar
        $appliedRows = Database::instance()->fetchAll(
            'SELECT name, batch, executed_at FROM migrations ORDER BY id ASC'
        );
        $applied = [];
        foreach ($appliedRows as $r) {
            $applied[(string) $r['name']] = $r;
        }

        $out = [];
        foreach ($files as $f) {
            $name = basename($f);
            $row = $applied[$name] ?? null;
            $out[] = [
                'name' => $name,
                'applied' => $row !== null,
                'executed_at' => $row['executed_at'] ?? null,
                'batch' => $row !== null ? (int) $row['batch'] : null,
                'size' => (int) (@filesize($f) ?: 0),
            ];
        }
        return $out;
    }

    /**
     * Henüz çalıştırılmamış migration dosyalarını döner.
     * @return string[]  basename listesi
     */
    public static function pending(): array
    {
        $list = self::list();
        $out = [];
        foreach ($list as $row) {
            if (!$row['applied']) {
                $out[] = $row['name'];
            }
        }
        return $out;
    }

    /**
     * Tek bir migration dosyasını çalıştır.
     *
     * @return array{
     *   ok: bool,
     *   name: string,
     *   statements_run: int,
     *   duration_ms: int,
     *   error?: string,
     *   error_sql?: string
     * }
     */
    public static function runFile(string $name, ?int $batch = null): array
    {
        self::ensureMigrationsTable();

        // Path traversal koruması — sadece migrations/ altında, .sql uzantısı
        if (!preg_match('/^[A-Za-z0-9_\-]+\.sql$/', $name)) {
            return [
                'ok' => false,
                'name' => $name,
                'statements_run' => 0,
                'duration_ms' => 0,
                'error' => 'Geçersiz migration dosya adı: ' . $name,
            ];
        }
        $path = self::dir() . '/' . $name;
        if (!is_file($path)) {
            return [
                'ok' => false,
                'name' => $name,
                'statements_run' => 0,
                'duration_ms' => 0,
                'error' => 'Migration dosyası bulunamadı: ' . $name,
            ];
        }

        // Zaten uygulanmış mı?
        $exists = (int) Database::instance()->fetchColumn(
            'SELECT COUNT(*) FROM migrations WHERE name = :n',
            [':n' => $name]
        );
        if ($exists > 0) {
            return [
                'ok' => true,
                'name' => $name,
                'statements_run' => 0,
                'duration_ms' => 0,
                'error' => 'Zaten uygulanmış (skipped).',
            ];
        }

        $sql = (string) file_get_contents($path);
        $statements = self::splitStatements($sql);

        $start = microtime(true);
        $pdo = Database::instance()->pdo();
        $ran = 0;
        $batch = $batch ?? (self::lastBatch() + 1);

        foreach ($statements as $idx => $stmt) {
            try {
                // prepare/execute/closeCursor — exec() bazı sürümlerde MariaDB
                // bağlantısını unbuffered state'te bırakabiliyor. Bu üçlü garantili
                // şekilde cursor'u kapatır → sonraki SELECT'ler patlamaz.
                $st = $pdo->prepare($stmt);
                $st->execute();
                // Statement bir result set döndürse de (rare in DDL) tüketmek için:
                do {
                    try { $st->fetchAll(); } catch (\Throwable) { /* result set yok */ }
                } while ($st->nextRowset() ?? false);
                $st->closeCursor();
                $ran++;
            } catch (Throwable $e) {
                Logger::warning('migration.failed', [
                    'name' => $name,
                    'statement_index' => $idx,
                    'statements_run' => $ran,
                    'error' => $e->getMessage(),
                ], 'migration');
                return [
                    'ok' => false,
                    'name' => $name,
                    'statements_run' => $ran,
                    'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                    'error' => $e->getMessage(),
                    'error_sql' => $stmt,
                ];
            }
        }

        // Başarılı — migrations tablosuna kaydet
        Database::instance()->insert('migrations', [
            'name' => $name,
            'batch' => $batch,
        ]);

        $duration = (int) ((microtime(true) - $start) * 1000);
        Logger::info('migration.ok', [
            'name' => $name,
            'statements_run' => $ran,
            'duration_ms' => $duration,
            'batch' => $batch,
        ], 'migration');

        return [
            'ok' => true,
            'name' => $name,
            'statements_run' => $ran,
            'duration_ms' => $duration,
        ];
    }

    /**
     * Tüm pending migration'ları sırayla çalıştır.
     *
     * @param bool $smartMode  true ise "duplicate column/table" gibi
     *                          "zaten var" hataları otomatik mark-as-applied
     *                          ile çözülür ve zincir devam eder.
     *                          false ise herhangi bir hatada durur.
     *
     * @return array{
     *   results: array<int, array>,
     *   total: int,
     *   ok: int,
     *   failed: int,
     *   auto_skipped: int,
     *   batch: int
     * }
     */
    public static function runPending(bool $smartMode = false): array
    {
        $pending = self::pending();
        $batch = self::lastBatch() + 1;
        $results = [];
        $ok = 0;
        $failed = 0;
        $autoSkipped = 0;

        foreach ($pending as $name) {
            $r = self::runFile($name, $batch);

            if ($r['ok']) {
                $results[] = $r;
                $ok++;
                continue;
            }

            // Hata var. Smart mode aktif + "zaten var" hatası mı?
            if ($smartMode && self::isAlreadyExistsError((string) ($r['error'] ?? ''))) {
                $mark = self::markAsApplied($name);
                if ($mark['ok']) {
                    $r['auto_skipped'] = true;
                    $r['ok'] = true;
                    $r['original_error'] = $r['error'] ?? '';
                    $r['error'] = null;
                    $results[] = $r;
                    $autoSkipped++;
                    continue;
                }
            }

            // Gerçek hata — zincir kırıldı
            $results[] = $r;
            $failed++;
            break;
        }

        return [
            'results' => $results,
            'total' => count($pending),
            'ok' => $ok,
            'failed' => $failed,
            'auto_skipped' => $autoSkipped,
            'batch' => $batch,
            'smart_mode' => $smartMode,
        ];
    }

    public static function lastBatch(): int
    {
        try {
            return (int) Database::instance()->fetchColumn('SELECT COALESCE(MAX(batch), 0) FROM migrations');
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Bir migration'ı çalıştırmadan "uygulanmış" olarak işaretle.
     * Dosyadaki SQL daha önce manuel veya başka bir yolla DB'ye uygulanmışsa
     * kullanılır — örn. "Duplicate column" hatası alındığında bu butonla
     * çözülür (zaten ekli olduğu için).
     *
     * @return array{ok:bool, error?:string, name:string}
     */
    public static function markAsApplied(string $name): array
    {
        self::ensureMigrationsTable();

        if (!preg_match('/^[A-Za-z0-9_\-]+\.sql$/', $name)) {
            return ['ok' => false, 'name' => $name, 'error' => 'Geçersiz dosya adı: ' . $name];
        }
        if (!is_file(self::dir() . '/' . $name)) {
            return ['ok' => false, 'name' => $name, 'error' => 'Dosya bulunamadı: ' . $name];
        }

        $exists = (int) Database::instance()->fetchColumn(
            'SELECT COUNT(*) FROM migrations WHERE name = :n',
            [':n' => $name]
        );
        if ($exists > 0) {
            return ['ok' => true, 'name' => $name, 'error' => 'Zaten kayıtlı (skipped).'];
        }

        $batch = self::lastBatch() + 1;
        try {
            Database::instance()->insert('migrations', [
                'name' => $name,
                'batch' => $batch,
            ]);
            Logger::info('migration.marked', [
                'name' => $name,
                'batch' => $batch,
            ], 'migration');
            return ['ok' => true, 'name' => $name];
        } catch (Throwable $e) {
            return ['ok' => false, 'name' => $name, 'error' => $e->getMessage()];
        }
    }

    /**
     * Bir migration kaydını migrations tablosundan siler (re-run mümkün hale getirir).
     * Dikkat: dosya tekrar çalıştırıldığında "zaten var" hatası verebilir.
     *
     * @return array{ok:bool, name:string, error?:string}
     */
    public static function unmark(string $name): array
    {
        if (!preg_match('/^[A-Za-z0-9_\-]+\.sql$/', $name)) {
            return ['ok' => false, 'name' => $name, 'error' => 'Geçersiz dosya adı.'];
        }
        try {
            $affected = Database::instance()->delete('migrations', 'name = :n', [':n' => $name]);
            Logger::warning('migration.unmarked', [
                'name' => $name,
                'affected' => $affected,
            ], 'migration');
            return ['ok' => $affected > 0, 'name' => $name];
        } catch (Throwable $e) {
            return ['ok' => false, 'name' => $name, 'error' => $e->getMessage()];
        }
    }

    /**
     * Bir MySQL hata kodunun "zaten var" anlamına gelip gelmediğini sınar.
     * Bu kodlar genelde migration'ın daha önce kısmen veya tamamen uygulandığını
     * gösterir → mark-as-applied güvenli bir çözümdür.
     */
    public static function isAlreadyExistsError(string $errorMessage): bool
    {
        $codes = [
            '1050', // Table already exists
            '1060', // Duplicate column name
            '1061', // Duplicate key name
            '1091', // Can't DROP — check that column/key exists
            '1146', // Table doesn't exist (DROP IF NOT EXISTS gibi)
        ];
        foreach ($codes as $code) {
            if (str_contains($errorMessage, $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * `migrations` tablosu yoksa oluştur.
     */
    public static function ensureMigrationsTable(): void
    {
        try {
            Database::instance()->pdo()->exec(
                'CREATE TABLE IF NOT EXISTS `migrations` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `name` VARCHAR(190) NOT NULL,
                    `batch` INT UNSIGNED NOT NULL,
                    `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `migrations_name_unique` (`name`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable) {
            // sessiz — bir sonraki run'da bu kontrol kullanıcıya yansır
        }
    }

    /**
     * SQL dosyasını statement'lara böler.
     *
     * String-aware: tek/çift tırnak ve backtick içindeki `;` ignore edilir.
     * `--` ve `/* *\/` yorumları temizlenir. HTML/Türkçe metin içinde
     * `;` geçtiğinde statement yanlış split olmaz.
     *
     * @return string[]  Boş olmayan SQL statement listesi
     */
    public static function splitStatements(string $sql): array
    {
        // Multi-line yorumları kaldır
        $sql = (string) preg_replace('!/\*.*?\*/!s', '', $sql);
        // Tek satır yorumları kaldır (`-- ...` to end of line)
        $sql = (string) preg_replace('/^\s*--.*$/m', '', $sql);

        // State-aware tarama: string/quote içindeyken `;` statement sonu DEĞİL
        $statements = [];
        $buf = '';
        $len = strlen($sql);
        $quote = null;          // null | "'" | '"' | '`'
        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];

            if ($quote !== null) {
                // String literal içindeyiz. Escape ('' veya \') handle et.
                if ($ch === '\\' && $i + 1 < $len) {
                    $buf .= $ch . $sql[$i + 1];
                    $i++;
                    continue;
                }
                if ($ch === $quote) {
                    // Doubled quote? ('' SQL escape) — string'i kapatma
                    if ($i + 1 < $len && $sql[$i + 1] === $quote) {
                        $buf .= $ch . $sql[$i + 1];
                        $i++;
                        continue;
                    }
                    $quote = null;
                }
                $buf .= $ch;
                continue;
            }

            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $quote = $ch;
                $buf .= $ch;
                continue;
            }

            if ($ch === ';') {
                $t = trim($buf);
                if ($t !== '') $statements[] = $t;
                $buf = '';
                continue;
            }

            $buf .= $ch;
        }
        $t = trim($buf);
        if ($t !== '') $statements[] = $t;
        return $statements;
    }

    /**
     * Disk üzerindeki migration dosyalarını alfabetik sırayla listele.
     * @return string[]  Tam path
     */
    private static function filesOnDisk(): array
    {
        $dir = self::dir();
        if (!is_dir($dir)) return [];
        $files = glob($dir . '/*.sql') ?: [];
        sort($files);
        return $files;
    }

    private static function dir(): string
    {
        return Config::root() . self::DIR;
    }
}
