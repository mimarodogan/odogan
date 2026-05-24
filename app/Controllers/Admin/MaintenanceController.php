<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\Logger;
use App\Services\MigrationRunner;

final class MaintenanceController
{
    public const STALE_MONTHS_DEFAULT = 3;

    public function index(Request $req): Response
    {
        $months = max(1, (int) $req->input('months', self::STALE_MONTHS_DEFAULT));
        return view('admin.maintenance', [
            'title' => 'Bakım & Tazelik',
            'months' => $months,
            'stale_posts' => self::stalePosts($months),
            'log_stats' => self::logStats(),
            'cache_stats' => self::cacheStats(),
        ]);
    }

    public function purgeLogs(Request $req): Response
    {
        $olderThanDays = max(1, (int) $req->input('older_than_days', 30));
        $deleted = (int) Database::instance()->run(
            'DELETE FROM logs WHERE log_date < DATE_SUB(CURDATE(), INTERVAL :n DAY)',
            [':n' => $olderThanDays]
        )->rowCount();

        // Also drop matching daily files.
        $filesDeleted = self::purgeLogFiles($olderThanDays);

        Logger::warning('logs.purged', [
            'older_than_days' => $olderThanDays,
            'db_rows' => $deleted,
            'files' => $filesDeleted,
        ], 'admin');

        flash('success', "$deleted log satırı ve $filesDeleted log dosyası silindi.");
        return Response::redirect(url('/admin/bakim'));
    }

    public function flushCache(Request $req): Response
    {
        \App\Core\Cache\CacheManager::driver()->flush();
        Logger::warning('cache.flushed', [], 'admin');
        flash('success', 'Önbellek tamamen temizlendi.');
        return Response::redirect(url('/admin/bakim'));
    }

    public function refreshStale(Request $req, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        Database::instance()->run(
            'UPDATE posts SET updated_at = NOW() WHERE id = :id AND status = "published"',
            [':id' => $id]
        );
        \App\Core\Cache\CacheManager::driver()->invalidateTags(['post:' . $id, 'home', 'sitemap']);
        flash('success', 'İçerik tazelik tarihi güncellendi.');
        return Response::redirect(url('/admin/bakim'));
    }

    // ─────────────────────────────────────────────────────────────
    // YEDEKLEME (BACKUP)
    // ─────────────────────────────────────────────────────────────

    public function backups(Request $req): Response
    {
        $dir = Config::root() . '/storage/backups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $files = [];
        $pattern1 = $dir . '/db-*.sql.gz';
        $pattern2 = $dir . '/uploads-*.tar.gz';
        foreach (array_merge(glob($pattern1) ?: [], glob($pattern2) ?: []) as $f) {
            $base = basename($f);
            $files[] = [
                'name'  => $base,
                'size'  => (int) (@filesize($f) ?: 0),
                'mtime' => (int) (@filemtime($f) ?: 0),
                'type'  => str_starts_with($base, 'db-') ? 'db' : 'uploads',
            ];
        }
        usort($files, fn($a, $b) => $b['mtime'] <=> $a['mtime']);

        return view('admin.maintenance.backups', [
            'title' => 'Yedekler',
            'files' => $files,
            'dir' => $dir,
            'writable' => is_writable($dir),
            'php_bin' => PHP_BINARY,
        ]);
    }

    public function runBackupDb(Request $req): Response
    {
        return $this->runScript('backup-db.php', 'DB yedeği');
    }

    public function runBackupUploads(Request $req): Response
    {
        return $this->runScript('backup-uploads.php', 'Uploads yedeği');
    }

    public function downloadBackup(Request $req, array $args): Response
    {
        $name = (string) ($args['name'] ?? '');
        // Path traversal koruması + sadece bilinen pattern'ler
        if (!preg_match('#^(db|uploads)-[A-Za-z0-9_\-]+\.(sql|tar)\.gz$#', $name)) {
            return Response::notFound('Geçersiz dosya adı.');
        }
        $path = Config::root() . '/storage/backups/' . $name;
        if (!is_file($path)) {
            return Response::notFound('Yedek bulunamadı.');
        }

        Logger::info('backup.download', [
            'file' => $name,
            'by' => AuthService::user()['id'] ?? null,
        ], 'admin');

        // Direkt streaming (Response class file desteklemiyor)
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: no-store');
        readfile($path);
        exit;
    }

    public function deleteBackup(Request $req, array $args): Response
    {
        $name = (string) ($args['name'] ?? '');
        if (!preg_match('#^(db|uploads)-[A-Za-z0-9_\-]+\.(sql|tar)\.gz$#', $name)) {
            flash('error', 'Geçersiz dosya adı.');
            return Response::redirect(url('/admin/bakim/yedekler'));
        }
        $path = Config::root() . '/storage/backups/' . $name;
        if (is_file($path) && @unlink($path)) {
            Logger::warning('backup.deleted', [
                'file' => $name,
                'by' => AuthService::user()['id'] ?? null,
            ], 'admin');
            flash('success', $name . ' silindi.');
        } else {
            flash('error', 'Silinemedi.');
        }
        return Response::redirect(url('/admin/bakim/yedekler'));
    }

    // ─────────────────────────────────────────────────────────────
    // MIGRATIONS
    // ─────────────────────────────────────────────────────────────

    public function migrations(Request $req): Response
    {
        $list = MigrationRunner::list();
        $lastResults = $_SESSION['_migration_results'] ?? null;
        unset($_SESSION['_migration_results']);

        $applied = 0;
        $pending = 0;
        foreach ($list as $m) {
            $m['applied'] ? $applied++ : $pending++;
        }

        return view('admin.maintenance.migrations', [
            'title' => 'Migrasyonlar',
            'migrations' => $list,
            'applied_count' => $applied,
            'pending_count' => $pending,
            'last_batch' => MigrationRunner::lastBatch(),
            'last_results' => $lastResults,
        ]);
    }

    public function runPendingMigrations(Request $req): Response
    {
        $smart = (string) $req->input('smart_mode', '') === '1';
        // Kullanıcıyı runner'dan ÖNCE çek — runner DB bağlantısını busy bırakırsa
        // bu fetch'i tetikleme. (Eski sürümlerde PDOException: unbuffered queries.)
        $byUserId = null;
        try {
            $byUserId = AuthService::user()['id'] ?? null;
        } catch (\Throwable) { /* en kötü durumda null */ }

        $report = MigrationRunner::runPending($smart);

        Logger::info('migration.batch_run', [
            'by' => $byUserId,
            'smart_mode' => $smart,
            'total' => $report['total'],
            'ok' => $report['ok'],
            'failed' => $report['failed'],
            'auto_skipped' => $report['auto_skipped'] ?? 0,
            'batch' => $report['batch'],
        ], 'admin');

        $autoSkipped = (int) ($report['auto_skipped'] ?? 0);
        if ($report['failed'] === 0 && $report['total'] === 0) {
            flash('warning', 'Çalıştırılacak bekleyen migration yok.');
        } elseif ($report['failed'] === 0) {
            $msg = $report['ok'] . ' migration başarıyla işlendi (batch #' . $report['batch'] . ').';
            if ($autoSkipped > 0) {
                $msg .= ' ' . $autoSkipped . ' tanesi "zaten var" tespit edildi ve uygulanmış olarak işaretlendi.';
            }
            flash('success', $msg);
        } else {
            flash('error', $report['ok'] . ' başarılı, ' . $report['failed'] . ' hata. Detayları görmek için aşağı bakın.');
        }

        $_SESSION['_migration_results'] = $report;
        return Response::redirect(url('/admin/bakim/migrasyonlar'));
    }

    public function runOneMigration(Request $req, array $args): Response
    {
        $name = (string) ($args['name'] ?? '');
        $result = MigrationRunner::runFile($name);

        Logger::info('migration.single_run', [
            'by' => AuthService::user()['id'] ?? null,
            'name' => $name,
            'ok' => $result['ok'],
        ], 'admin');

        if ($result['ok']) {
            flash('success', $name . ' uygulandı (' . ($result['statements_run'] ?? 0) . ' statement, ' . ($result['duration_ms'] ?? 0) . 'ms).');
        } else {
            flash('error', $name . ' başarısız: ' . substr((string) ($result['error'] ?? ''), 0, 300));
        }

        $_SESSION['_migration_results'] = [
            'results' => [$result],
            'total' => 1,
            'ok' => $result['ok'] ? 1 : 0,
            'failed' => $result['ok'] ? 0 : 1,
            'batch' => MigrationRunner::lastBatch(),
        ];
        return Response::redirect(url('/admin/bakim/migrasyonlar'));
    }

    /**
     * Bir migration'ı çalıştırmadan "uygulanmış" işaretler.
     * Senaryo: dosya daha önce manuel uygulanmış (ör. Duplicate column hatası).
     */
    public function markMigrationApplied(Request $req, array $args): Response
    {
        $name = (string) ($args['name'] ?? '');
        $result = MigrationRunner::markAsApplied($name);

        Logger::warning('migration.mark_applied', [
            'by' => AuthService::user()['id'] ?? null,
            'name' => $name,
            'ok' => $result['ok'],
        ], 'admin');

        if ($result['ok']) {
            flash('success', $name . ' uygulanmış olarak işaretlendi (dosya çalıştırılmadı).');
        } else {
            flash('error', $name . ' işaretlenemedi: ' . ($result['error'] ?? 'bilinmeyen hata'));
        }
        return Response::redirect(url('/admin/bakim/migrasyonlar'));
    }

    /**
     * Bir migration'ı "uygulanmamış" hale getirir — kaydı siler, dosya yeniden çalıştırılabilir.
     */
    public function unmarkMigration(Request $req, array $args): Response
    {
        $name = (string) ($args['name'] ?? '');
        $result = MigrationRunner::unmark($name);

        Logger::warning('migration.unmark', [
            'by' => AuthService::user()['id'] ?? null,
            'name' => $name,
            'ok' => $result['ok'],
        ], 'admin');

        if ($result['ok']) {
            flash('success', $name . ' kaydı silindi. Dosya tekrar uygulanabilir.');
        } else {
            flash('error', $name . ' kaydı silinemedi: ' . ($result['error'] ?? 'bulunamadı'));
        }
        return Response::redirect(url('/admin/bakim/migrasyonlar'));
    }

    /**
     * Ortak CLI helper — bin/<name>.php scriptini çalıştırır.
     */
    private function runScript(string $script, string $label): Response
    {
        $path = Config::root() . '/bin/' . $script;
        if (!is_file($path)) {
            flash('error', $label . ': script bulunamadı (bin/' . $script . ').');
            return Response::redirect(url('/admin/bakim/yedekler'));
        }
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($path) . ' 2>&1';
        $out = []; $rc = 0;
        exec($cmd, $out, $rc);

        if ($rc === 0) {
            Logger::info('backup.manual.ok', [
                'script' => $script,
                'by' => AuthService::user()['id'] ?? null,
                'tail' => array_slice($out, -3),
            ], 'admin');
            flash('success', $label . ' başarıyla oluşturuldu.');
        } else {
            Logger::warning('backup.manual.fail', [
                'script' => $script,
                'rc' => $rc,
                'out' => array_slice($out, -10),
            ], 'admin');
            flash('error', $label . ' başarısız (rc=' . $rc . '): ' . implode(' | ', array_slice($out, -3)));
        }
        return Response::redirect(url('/admin/bakim/yedekler'));
    }

    /**
     * @return array<int,array>
     */
    public static function stalePosts(int $months = 3, int $limit = 100): array
    {
        return Database::instance()->fetchAll(
            'SELECT p.id, p.title, p.slug, p.published_at, p.updated_at, p.view_count,
                    c.name AS category_name, c.slug AS category_slug,
                    u.name AS author_name,
                    TIMESTAMPDIFF(DAY, p.updated_at, NOW()) AS days_old
             FROM posts p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN users u ON u.id = p.user_id
             WHERE p.status = "published"
               AND p.updated_at < DATE_SUB(NOW(), INTERVAL :m MONTH)
             ORDER BY p.updated_at ASC
             LIMIT ' . max(1, $limit),
            [':m' => $months]
        );
    }

    public static function staleCount(int $months = 3): int
    {
        try {
            return (int) Database::instance()->fetchColumn(
                'SELECT COUNT(*) FROM posts
                 WHERE status = "published" AND updated_at < DATE_SUB(NOW(), INTERVAL :m MONTH)',
                [':m' => $months]
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function logStats(): array
    {
        $rows = Database::instance()->fetchAll(
            'SELECT level, COUNT(*) AS n FROM logs
             WHERE log_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
             GROUP BY level ORDER BY n DESC'
        );
        $total = (int) Database::instance()->fetchColumn('SELECT COUNT(*) FROM logs');
        $oldest = (string) Database::instance()->fetchColumn('SELECT MIN(log_date) FROM logs');
        return ['total' => $total, 'oldest_date' => $oldest, 'last_7d_by_level' => $rows];
    }

    private static function cacheStats(): array
    {
        $driver = \App\Core\Cache\CacheManager::driver();
        $stats = ['driver' => get_class($driver)];
        if ($driver instanceof \App\Core\Cache\RedisCache) {
            try {
                $info = $driver->client()->info('keyspace');
                $stats['keyspace'] = $info;
            } catch (\Throwable) {}
        }
        return $stats;
    }

    private static function purgeLogFiles(int $olderThanDays): int
    {
        $dir = \App\Core\Config::root() . '/storage/logs';
        if (!is_dir($dir)) {
            return 0;
        }
        $cutoff = time() - ($olderThanDays * 86400);
        $deleted = 0;
        foreach (glob($dir . '/*.log') ?: [] as $f) {
            if (@filemtime($f) < $cutoff) {
                @unlink($f) && $deleted++;
            }
        }
        return $deleted;
    }
}
