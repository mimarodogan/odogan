<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Cache\CacheManager;
use App\Core\Cache\RedisCache;
use App\Core\Config;
use App\Core\Database;

/**
 * View tracking with Redis-side increments. Each post id has its own
 * counter; once the counter passes the flush threshold, we add it onto
 * `posts.view_count` and reset to 0.
 *
 * The point: every page load is one INCR (cheap), the DB only writes
 * once every N views, and the homepage's "trending" block can read
 * the *running* count by combining the DB column with the live Redis
 * counters.
 */
final class ViewCounter
{
    private const PREFIX = 'view:post:';
    public const FLUSH_THRESHOLD = 25;
    public const TRACKER_TTL = 600; // dedupe window per ip+post (sec)

    private static function nsPrefix(): string
    {
        return (string) Config::get('REDIS_PREFIX', 'odogan:');
    }

    private static function counterKey(int $postId): string
    {
        return self::nsPrefix() . self::PREFIX . $postId;
    }

    private static function dedupeKey(int $postId, string $fingerprint): string
    {
        return self::nsPrefix() . 'view:dedupe:' . $postId . ':' . sha1($fingerprint);
    }

    public static function record(int $postId, ?string $fingerprint = null): void
    {
        if ($postId <= 0) {
            return;
        }
        $redis = CacheManager::redis();
        if ($fingerprint === null) {
            try {
                $fingerprint = \App\Services\RealIpService::ip() ?: 'anon';
            } catch (\Throwable) {
                $fingerprint = (string) ($_SERVER['REMOTE_ADDR'] ?? 'anon');
            }
        }

        if ($redis !== null) {
            $client = $redis->client();
            $dedupe = self::dedupeKey($postId, $fingerprint);
            // SETNX returns 1 only the first time within the window.
            if ((int) $client->setnx($dedupe, '1') !== 1) {
                return;
            }
            $client->expire($dedupe, self::TRACKER_TTL);
            $key = self::counterKey($postId);
            $count = (int) $client->incr($key);
            if ($count >= self::FLUSH_THRESHOLD) {
                self::flushOne($postId, $count);
            }
            return;
        }
        // No Redis: just update DB directly (cost-acceptable fallback).
        // Preserve updated_at so reads don't make a post "fresh again".
        Database::instance()->run(
            'UPDATE posts SET view_count = view_count + 1, updated_at = updated_at WHERE id = :id',
            [':id' => $postId]
        );
    }

    public static function liveCount(int $postId): int
    {
        $redis = CacheManager::redis();
        if ($redis === null) {
            return 0;
        }
        $val = $redis->client()->get(self::counterKey($postId));
        return $val === null ? 0 : (int) $val;
    }

    /**
     * Drain all pending counters into the DB. Safe to call from cron or
     * on shutdown. Returns number of rows written.
     */
    public static function flushAll(): int
    {
        $redis = CacheManager::redis();
        if ($redis === null) {
            return 0;
        }
        $client = $redis->client();
        $pattern = self::nsPrefix() . self::PREFIX . '*';
        $iter = null;
        $written = 0;
        do {
            [$iter, $keys] = $client->scan($iter ?? 0, [
                'MATCH' => $pattern,
                'COUNT' => 200,
            ]);
            foreach ((array) $keys as $key) {
                if (preg_match('#:(\d+)$#', (string) $key, $m)) {
                    $val = (int) $client->get($key);
                    if ($val > 0) {
                        self::flushOne((int) $m[1], $val, $client, (string) $key);
                        $written++;
                    }
                }
            }
        } while ((int) $iter !== 0);
        return $written;
    }

    private static function flushOne(int $postId, int $count, mixed $client = null, ?string $key = null): void
    {
        $redis = CacheManager::redis();
        $client = $client ?: ($redis ? $redis->client() : null);
        $key = $key ?: self::counterKey($postId);
        Database::instance()->run(
            'UPDATE posts SET view_count = view_count + :n, updated_at = updated_at WHERE id = :id',
            [':n' => $count, ':id' => $postId]
        );
        if ($client !== null) {
            $client->decrby($key, $count);
        }
    }
}
