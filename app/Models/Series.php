<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Cache\CacheManager;
use App\Core\Database;

/**
 * Series / Dizi yazılar (Tier 5).
 *
 * Bir seri birden çok post içerir; her post `series_id` + `series_position` ile bağlanır.
 * `post_count` denormal kolon `recountPosts($id)` ile senkronize tutulur.
 */
final class Series
{
    public static function findById(int $id): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM series WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM series WHERE slug = :s LIMIT 1',
            [':s' => $slug]
        );
    }

    /**
     * Tüm seriler — admin liste için (post_count dahil).
     */
    public static function all(int $limit = 200): array
    {
        return Database::instance()->fetchAll(
            'SELECT * FROM series ORDER BY updated_at DESC LIMIT ' . max(1, $limit)
        );
    }

    /**
     * Yayına çıkmış serileri post_count > 0 ile listele (public/dropdown için).
     */
    public static function listActive(int $limit = 100): array
    {
        return Database::instance()->fetchAll(
            'SELECT * FROM series WHERE post_count > 0 ORDER BY name ASC LIMIT ' . max(1, $limit)
        );
    }

    public static function create(array $data): int
    {
        $data['slug'] = self::uniqueSlug((string) ($data['slug'] ?? $data['name'] ?? ''));
        return (int) Database::instance()->insert('series', $data);
    }

    public static function update(int $id, array $data): int
    {
        if (isset($data['slug'])) {
            $seed = trim((string) $data['slug']);
            if ($seed === '') {
                $seed = (string) ($data['name'] ?? '');
            }
            $data['slug'] = self::uniqueSlug($seed, $id);
        }
        $n = Database::instance()->update('series', $data, 'id = :wid', [':wid' => $id]);
        CacheManager::driver()->invalidateTags(['series:' . $id, 'home']);
        return $n;
    }

    public static function delete(int $id): int
    {
        // FK ON DELETE SET NULL — postlar zarar görmez, series_id null'a düşer
        $n = Database::instance()->delete('series', 'id = :wid', [':wid' => $id]);
        CacheManager::driver()->invalidateTags(['series:' . $id, 'home']);
        return $n;
    }

    /**
     * Seriye atfedilen yazıları sıra ile getir (series_position ASC).
     * Yalnızca yayında olanları döndürür (public listing için).
     */
    public static function postsFor(int $seriesId, bool $publishedOnly = true): array
    {
        $sql = 'SELECT p.id, p.title, p.slug, p.excerpt, p.cover_image, p.published_at,
                       p.reading_minutes, p.series_position, p.status,
                       c.name AS category_name, c.slug AS category_slug,
                       u.name AS author_name, u.slug AS author_slug
                FROM posts p
                INNER JOIN categories c ON c.id = p.category_id
                INNER JOIN users u ON u.id = p.user_id
                WHERE p.series_id = :sid';
        if ($publishedOnly) {
            $sql .= ' AND p.status = "published"';
        }
        $sql .= ' ORDER BY p.series_position ASC, p.published_at ASC';
        return Database::instance()->fetchAll($sql, [':sid' => $seriesId]);
    }

    /**
     * Bir yazının seri içindeki önceki ve sonraki bölümü.
     *
     * @return array{prev: ?array, next: ?array, position: int, total: int}
     */
    public static function navFor(int $postId, int $seriesId): array
    {
        $posts = self::postsFor($seriesId, true);
        $idx = -1;
        foreach ($posts as $i => $p) {
            if ((int) $p['id'] === $postId) {
                $idx = $i;
                break;
            }
        }
        $total = count($posts);
        if ($idx < 0) {
            return ['prev' => null, 'next' => null, 'position' => 0, 'total' => $total];
        }
        return [
            'prev' => $idx > 0 ? $posts[$idx - 1] : null,
            'next' => $idx < $total - 1 ? $posts[$idx + 1] : null,
            'position' => $idx + 1,
            'total' => $total,
        ];
    }

    /**
     * Seriye atfedilen yayında post sayısını yeniden hesapla — denormal kolonu günceller.
     */
    public static function recountPosts(int $seriesId): int
    {
        $n = (int) Database::instance()->fetchColumn(
            'SELECT COUNT(*) FROM posts WHERE series_id = :sid AND status = "published"',
            [':sid' => $seriesId]
        );
        Database::instance()->update('series', ['post_count' => $n], 'id = :wid', [':wid' => $seriesId]);
        return $n;
    }

    /**
     * Slug benzersizlik garantisi. İhtilaf varsa "-2", "-3" eklenir.
     */
    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = slugify($base);
        if ($slug === '') $slug = 'dizi';
        $candidate = $slug;
        $i = 1;
        while (true) {
            $sql = 'SELECT id FROM series WHERE slug = :s';
            $params = [':s' => $candidate];
            if ($ignoreId !== null) {
                $sql .= ' AND id <> :id';
                $params[':id'] = $ignoreId;
            }
            $sql .= ' LIMIT 1';
            if (Database::instance()->fetch($sql, $params) === null) {
                return $candidate;
            }
            $i++;
            $candidate = $slug . '-' . $i;
        }
    }
}
