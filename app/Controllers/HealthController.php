<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Cache\CacheManager;
use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\MailService;
use Throwable;

/**
 * /health endpoint — uptime monitoring (UptimeRobot, StatusCake, vb.) için.
 *
 * Cevap formatı:
 *   {
 *     "status": "ok" | "degraded",
 *     "time": "2026-05-14T14:32:11+03:00",
 *     "uptime_ms": 12,
 *     "cache_driver": "App\\Core\\Cache\\FileCache",
 *     "checks": { ... }
 *   }
 *
 * HTTP 200 herhangi bir kritik check fail olmadığında, aksi 503.
 * Cache + mail "soft" kontroller (fail'i degrade saymayız).
 */
final class HealthController
{
    public function index(Request $req): Response
    {
        $checks = [
            'db'               => self::checkDb(),                // critical
            'storage_writable' => self::checkStorage(),           // critical
            'cache'            => self::checkCache(),             // soft
            'mail_configured'  => self::checkMail(),              // soft
        ];

        // Critical: db + storage. Diğerleri soft (status degrade etmez).
        $critical = ($checks['db'] === 'ok') && ($checks['storage_writable'] === true);
        $status = $critical ? 'ok' : 'degraded';
        $http   = $critical ? 200 : 503;

        return Response::json([
            'status'       => $status,
            'time'         => date('c'),
            'uptime_ms'    => (int) ((microtime(true) - APP_START) * 1000),
            'cache_driver' => CacheManager::driver()::class,
            'checks'       => $checks,
        ], $http)->header('Cache-Control', 'no-store');
    }

    private static function checkDb(): string
    {
        try {
            $r = Database::instance()->fetchColumn('SELECT 1');
            return ((int) $r === 1) ? 'ok' : 'fail';
        } catch (Throwable $e) {
            return 'fail';
        }
    }

    private static function checkCache(): string
    {
        try {
            $key = 'health:probe:' . bin2hex(random_bytes(4));
            $cache = CacheManager::driver();
            $cache->set($key, 'pong', 30);
            $value = $cache->get($key);
            $cache->delete($key);
            return $value === 'pong' ? 'ok' : 'fail';
        } catch (Throwable $e) {
            return 'fail';
        }
    }

    private static function checkStorage(): bool
    {
        $dir = Config::root() . '/storage/cache';
        if (!is_dir($dir)) {
            return false;
        }
        return is_writable($dir);
    }

    /**
     * Mail config'in anlamlı olup olmadığını döner.
     * 'ok'         — SMTP host tanımlı ve example placeholder değil
     * 'log_only'   — driver "log" (mail dosyaya yazılıyor)
     * 'unconfigured' — host eksik veya placeholder
     * 'fail'       — config yüklenemedi
     */
    private static function checkMail(): string
    {
        try {
            $cfg = MailService::loadConfig();
            if (($cfg['driver'] ?? '') === 'log') {
                return 'log_only';
            }
            $host = (string) ($cfg['host'] ?? '');
            return ($host !== '' && !str_contains($host, 'example')) ? 'ok' : 'unconfigured';
        } catch (Throwable $e) {
            return 'fail';
        }
    }
}
