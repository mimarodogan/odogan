<?php
declare(strict_types=1);

namespace App\Core\Cache;

use App\Core\Config;
use Predis\Client as PredisClient;

final class CacheManager
{
    private static ?CacheInterface $instance = null;

    public static function driver(): CacheInterface
    {
        if (self::$instance !== null) {
            return self::$instance;
        }
        $driver = (string) Config::get('CACHE_DRIVER', 'file');
        if ($driver === 'redis') {
            $redis = self::makeRedis();
            if ($redis !== null) {
                return self::$instance = $redis;
            }
        }
        return self::$instance = new FileCache(self::cachePath());
    }

    public static function redis(): ?RedisCache
    {
        $d = self::driver();
        return $d instanceof RedisCache ? $d : null;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    private static function makeRedis(): ?RedisCache
    {
        if (!class_exists(PredisClient::class)) {
            return null;
        }
        try {
            $client = new PredisClient([
                'scheme' => 'tcp',
                'host' => (string) Config::get('REDIS_HOST', '127.0.0.1'),
                'port' => (int) Config::get('REDIS_PORT', 6379),
                'database' => (int) Config::get('REDIS_DB', 0),
                'password' => (string) Config::get('REDIS_PASSWORD', '') ?: null,
                'timeout' => 1.0,
            ]);
            // Smoke-test the connection so failure stays out of the request path.
            $client->connect();
            $client->ping();
            $prefix = (string) Config::get('REDIS_PREFIX', 'odogan:');
            return new RedisCache($client, $prefix);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function cachePath(): string
    {
        $relative = (string) Config::get('CACHE_PATH', 'storage/cache');
        if ($relative !== '' && $relative[0] === '/') {
            return $relative;
        }
        return Config::root() . '/' . trim($relative, '/');
    }
}
