<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * Fixed-window rate limiter backed by the local filesystem.
 *
 * Designed for low-traffic endpoints (login, register, password reset)
 * where a few-per-minute cap is enough to stop credential stuffing.
 * It is *intentionally* not Redis-backed — production hosts vary.
 */
final class RateLimiter
{
    private const DIR = '/storage/cache/ratelimit';

    /**
     * @return array{ok:bool,remaining:int,retry_after:int}
     */
    public static function hit(string $key, int $max, int $windowSeconds): array
    {
        $dir = self::storageDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . sha1($key) . '.json';
        $now = time();
        $state = ['count' => 0, 'reset_at' => $now + $windowSeconds];

        $fp = @fopen($file, 'c+');
        if ($fp === false) {
            // If the cache dir is unwritable we fail OPEN — better to allow
            // legitimate users in than to lock everyone out due to a
            // permissions glitch.
            return ['ok' => true, 'remaining' => $max - 1, 'retry_after' => 0];
        }
        try {
            @flock($fp, LOCK_EX);
            $raw = stream_get_contents($fp);
            if ($raw !== false && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && isset($decoded['count'], $decoded['reset_at'])) {
                    $state = $decoded;
                }
            }
            if ($state['reset_at'] <= $now) {
                $state = ['count' => 0, 'reset_at' => $now + $windowSeconds];
            }
            $state['count']++;
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string) json_encode($state));
            fflush($fp);
        } finally {
            @flock($fp, LOCK_UN);
            @fclose($fp);
        }

        $ok = $state['count'] <= $max;
        return [
            'ok' => $ok,
            'remaining' => max(0, $max - $state['count']),
            'retry_after' => $ok ? 0 : max(1, $state['reset_at'] - $now),
        ];
    }

    public static function clear(string $key): void
    {
        $file = self::storageDir() . '/' . sha1($key) . '.json';
        if (is_file($file)) {
            @unlink($file);
        }
    }

    public static function clientIp(): string
    {
        // RealIpService trusted_proxies listesini dikkate alır.
        // Bu sayede X-Forwarded-For sahteciliğine bağışıkızdır.
        return RealIpService::ip();
    }

    private static function storageDir(): string
    {
        $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
        return $root . self::DIR;
    }
}
