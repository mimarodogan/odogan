<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * 404 Logger (Tier 7 — Analytics).
 *
 * Bulunamayan URL'leri kaydeder, admin'e en yakın eşleşmeleri önerir.
 * Hit counter ile tekrarlı 404'leri ön plana çıkarır.
 */
final class NotFoundLog
{
    public static function record(string $path, ?string $referer = null, ?string $userAgent = null): void
    {
        $path = '/' . ltrim($path, '/');
        if (mb_strlen($path) > 500) {
            $path = mb_substr($path, 0, 500);
        }
        try {
            $db = Database::instance();
            $existing = $db->fetch(
                'SELECT id FROM not_found_log WHERE path = :p LIMIT 1',
                [':p' => $path]
            );
            if ($existing) {
                $db->run(
                    'UPDATE not_found_log SET hit_count = hit_count + 1, last_seen = NOW(),
                     referer = COALESCE(:ref, referer)
                     WHERE id = :id',
                    [':id' => (int) $existing['id'], ':ref' => $referer]
                );
            } else {
                $db->insert('not_found_log', [
                    'path'       => $path,
                    'referer'    => $referer ? mb_substr($referer, 0, 500) : null,
                    'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
                ]);
            }
        } catch (\Throwable) {
            // Logger fail-safe
        }
    }

    public static function list(int $limit = 100, bool $unresolvedOnly = true): array
    {
        $sql = 'SELECT * FROM not_found_log';
        if ($unresolvedOnly) {
            $sql .= ' WHERE resolved = 0';
        }
        $sql .= ' ORDER BY hit_count DESC, last_seen DESC LIMIT ' . max(1, $limit);
        try {
            return Database::instance()->fetchAll($sql);
        } catch (\Throwable) {
            return [];
        }
    }

    public static function findById(int $id): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM not_found_log WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
    }

    public static function markResolved(int $id): void
    {
        try {
            Database::instance()->update('not_found_log', ['resolved' => 1], 'id = :id', [':id' => $id]);
        } catch (\Throwable) {}
    }

    public static function delete(int $id): void
    {
        try {
            Database::instance()->delete('not_found_log', 'id = :id', [':id' => $id]);
        } catch (\Throwable) {}
    }

    /**
     * 404 yoluna en yakın yayında yazıyı/kategoriyi Levenshtein ile öner.
     * Hızlı approximation — production'da query büyük olursa cache'lenebilir.
     */
    public static function suggestSimilar(string $path, int $limit = 3): array
    {
        $candidates = [];
        try {
            $db = Database::instance();
            // Yazı slug'larını çek
            $posts = $db->fetchAll(
                'SELECT CONCAT("/", c.slug, "/", p.slug) AS url, p.title
                 FROM posts p
                 INNER JOIN categories c ON c.id = p.category_id
                 WHERE p.status = "published"
                 LIMIT 1000'
            );
            foreach ($posts as $p) {
                $distance = levenshtein($path, (string) $p['url']);
                if ($distance < 20) {
                    $candidates[] = [
                        'url'      => (string) $p['url'],
                        'title'    => (string) $p['title'],
                        'distance' => $distance,
                    ];
                }
            }
            usort($candidates, static fn($a, $b) => $a['distance'] <=> $b['distance']);
            return array_slice($candidates, 0, $limit);
        } catch (\Throwable) {
            return [];
        }
    }
}
