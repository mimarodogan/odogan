<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Cache\CacheManager;
use App\Core\Database;

/**
 * 301 Redirect Manager (Tier 7 — Analytics).
 *
 * Admin'in yönlendirme tanımladığı eski → yeni URL eşleştirme.
 * Router 404'ten önce check eder.
 */
final class Redirect
{
    /**
     * Path'e göre aktif redirect'i getir. Cache'lenir (1 saat).
     */
    public static function findByPath(string $path): ?array
    {
        $path = '/' . ltrim($path, '/');
        try {
            $cache = CacheManager::driver();
            return $cache->remember(
                'redirect:' . md5($path),
                3600,
                static function () use ($path) {
                    return Database::instance()->fetch(
                        'SELECT * FROM redirects WHERE from_path = :p AND is_active = 1 LIMIT 1',
                        [':p' => $path]
                    );
                },
                ['redirects']
            );
        } catch (\Throwable) {
            try {
                return Database::instance()->fetch(
                    'SELECT * FROM redirects WHERE from_path = :p AND is_active = 1 LIMIT 1',
                    [':p' => $path]
                );
            } catch (\Throwable) {
                return null;
            }
        }
    }

    public static function bumpHit(int $id): void
    {
        try {
            Database::instance()->run(
                'UPDATE redirects SET hit_count = hit_count + 1 WHERE id = :id',
                [':id' => $id]
            );
        } catch (\Throwable) {}
    }

    public static function all(int $limit = 200): array
    {
        try {
            return Database::instance()->fetchAll(
                'SELECT * FROM redirects ORDER BY hit_count DESC, id DESC LIMIT ' . max(1, $limit)
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public static function findById(int $id): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM redirects WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
    }

    public static function create(array $data): int
    {
        $id = (int) Database::instance()->insert('redirects', $data);
        self::flushCache();
        return $id;
    }

    public static function update(int $id, array $patch): int
    {
        $n = Database::instance()->update('redirects', $patch, 'id = :wid', [':wid' => $id]);
        self::flushCache();
        return $n;
    }

    public static function delete(int $id): int
    {
        $n = Database::instance()->delete('redirects', 'id = :wid', [':wid' => $id]);
        self::flushCache();
        return $n;
    }

    private static function flushCache(): void
    {
        try {
            CacheManager::driver()->invalidateTags(['redirects']);
        } catch (\Throwable) {}
    }
}
