<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Cache\CacheManager;
use App\Core\Database;

final class Post
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ARCHIVED = 'archived';

    public static function findById(int $id): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM posts WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
    }

    public static function findBySlugInCategory(string $categorySlug, string $slug): ?array
    {
        return Database::instance()->fetch(
            'SELECT p.*, c.slug AS category_slug, c.name AS category_name,
                    u.name AS author_name, u.slug AS author_slug
             FROM posts p
             INNER JOIN categories c ON c.id = p.category_id
             INNER JOIN users u ON u.id = p.user_id
             WHERE c.slug = :cs AND p.slug = :ps
             LIMIT 1',
            [':cs' => $categorySlug, ':ps' => $slug]
        );
    }

    /**
     * Admin/editor panel listesi — tüm yazılar (durumu ne olursa olsun).
     * Bulk action ve quick edit'i destekleyebilmek için sahibin kim olduğunu
     * controller ayrıca kontrol eder.
     */
    public static function listAllForPanel(int $limit = 200): array
    {
        // Panel listesinde body LONGTEXT'i çekmiyoruz — 200 satırda MB'larca veri demek.
        // Bulk action / quick edit görünümleri sadece meta-alanları kullanır.
        return Database::instance()->fetchAll(
            'SELECT p.id, p.title, p.slug, p.status, p.featured, p.published_at,
                    p.updated_at, p.created_at, p.user_id, p.category_id, p.editor_id,
                    p.cover_image, p.excerpt, p.reading_minutes, p.view_count, p.comment_count,
                    c.name AS category_name, c.slug AS category_slug,
                    u.name AS author_name, u.slug AS author_slug
             FROM posts p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN users u ON u.id = p.user_id
             ORDER BY p.updated_at DESC
             LIMIT ' . max(1, $limit)
        );
    }

    public static function listByUser(int $userId, ?string $status = null): array
    {
        $sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug
                FROM posts p
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE p.user_id = :uid';
        $params = [':uid' => $userId];
        if ($status !== null) {
            $sql .= ' AND p.status = :st';
            $params[':st'] = $status;
        }
        $sql .= ' ORDER BY p.updated_at DESC';
        return Database::instance()->fetchAll($sql, $params);
    }

    public static function listByStatus(string $status, int $limit = 50): array
    {
        return Database::instance()->fetchAll(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug,
                    u.name AS author_name, u.email AS author_email, u.slug AS author_slug
             FROM posts p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN users u ON u.id = p.user_id
             WHERE p.status = :st
             ORDER BY p.updated_at DESC
             LIMIT ' . max(1, $limit),
            [':st' => $status]
        );
    }

    public static function listPublishedInCategory(int $categoryId, int $limit = 20): array
    {
        return Database::instance()->fetchAll(
            'SELECT p.*, u.name AS author_name, u.slug AS author_slug
             FROM posts p
             INNER JOIN users u ON u.id = p.user_id
             WHERE p.category_id = :cid AND p.status = "published"
             ORDER BY p.published_at DESC
             LIMIT ' . max(1, $limit),
            [':cid' => $categoryId]
        );
    }

    /**
     * Pagination destekli kategori listesi.
     * @return array {posts: array<int,array>, total: int, page: int, per_page: int, total_pages: int}
     */
    public static function listByCategoryPaged(int $categoryId, int $page = 1, int $perPage = 12): array
    {
        $perPage = max(1, min(50, $perPage));
        $page    = max(1, $page);
        $total   = self::countByCategory($categoryId);
        $totalPages = (int) max(1, ceil($total / $perPage));
        // Sayfa numarası overflow ise son sayfaya gönder
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $posts = Database::instance()->fetchAll(
            'SELECT p.*, u.name AS author_name, u.slug AS author_slug
             FROM posts p
             INNER JOIN users u ON u.id = p.user_id
             WHERE p.category_id = :cid AND p.status = "published"
             ORDER BY p.published_at DESC
             LIMIT ' . $perPage . ' OFFSET ' . $offset,
            [':cid' => $categoryId]
        );

        return [
            'posts'       => $posts,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    public static function countByCategory(int $categoryId): int
    {
        return (int) Database::instance()->fetchColumn(
            'SELECT COUNT(*) FROM posts
             WHERE category_id = :cid AND status = "published"',
            [':cid' => $categoryId]
        );
    }

    public static function create(array $data): int
    {
        $categoryId = (int) ($data['category_id'] ?? 0);
        // Slug öncelik sırası: explicit slug → title → boş (uniqueSlug fallback "icerik" kullanır)
        $slugSeed = trim((string) ($data['slug'] ?? ''));
        if ($slugSeed === '') {
            $slugSeed = (string) ($data['title'] ?? '');
        }
        $data['slug'] = self::uniqueSlug($slugSeed, $categoryId);
        return (int) Database::instance()->insert('posts', $data);
    }

    public static function update(int $id, array $data, ?int $categoryId = null): int
    {
        if (isset($data['slug'])) {
            $cid = $categoryId ?? (int) ($data['category_id'] ?? 0);
            $slugSeed = trim((string) $data['slug']);
            if ($slugSeed === '') {
                $slugSeed = (string) ($data['title'] ?? '');
            }
            $data['slug'] = self::uniqueSlug($slugSeed, $cid, $id);
        }
        return Database::instance()->update('posts', $data, 'id = :wid', [':wid' => $id]);
    }

    public static function delete(int $id): int
    {
        return Database::instance()->delete('posts', 'id = :wid', [':wid' => $id]);
    }

    public static function uniqueSlug(string $base, int $categoryId, ?int $ignoreId = null): string
    {
        $slug = slugify($base);
        if ($slug === '') {
            $slug = 'icerik';
        }
        $candidate = $slug;
        $i = 1;
        while (true) {
            $sql = 'SELECT id FROM posts WHERE slug = :s AND category_id = :cid';
            $params = [':s' => $candidate, ':cid' => $categoryId];
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

    public static function transition(int $id, string $from, string $to, ?int $actorId, ?string $note = null): void
    {
        $patch = ['status' => $to];
        // Set published_at to "now" only when this is a fresh publish.
        // Scheduled→published keeps the user-chosen publish time.
        if ($to === self::STATUS_PUBLISHED && $from !== self::STATUS_SCHEDULED) {
            $patch['published_at'] = date('Y-m-d H:i:s');
        }
        if (in_array($to, [self::STATUS_PENDING, self::STATUS_PUBLISHED, self::STATUS_REJECTED], true) && $actorId) {
            $patch['editor_id'] = ($to === self::STATUS_PUBLISHED || $to === self::STATUS_REJECTED) ? $actorId : null;
        }
        Database::instance()->update('posts', $patch, 'id = :wid', [':wid' => $id]);
        Database::instance()->insert('post_status_history', [
            'post_id' => $id,
            'actor_id' => $actorId,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
        ]);
        // Tag-based invalidation: only the directly affected surfaces.
        $row = self::findById($id);
        $tags = ['post:' . $id, 'home', 'sitemap'];
        if ($row && !empty($row['category_id'])) {
            $tags[] = 'category:' . (int) $row['category_id'];
        }
        // Series post_count denormal kolonunu güncel tut (Tier 5).
        if ($row && !empty($row['series_id']) && class_exists(\App\Models\Series::class)) {
            \App\Models\Series::recountPosts((int) $row['series_id']);
            $tags[] = 'series:' . (int) $row['series_id'];
        }
        CacheManager::driver()->invalidateTags($tags);

        \App\Services\Logger::info(
            'post.transition',
            ['post_id' => $id, 'from' => $from, 'to' => $to, 'actor_id' => $actorId, 'tags' => $tags],
            'editorial'
        );

        // IndexNow — published transition'da Bing/Yandex/Cloudflare'e anında bildir
        if ($to === 'published' && $row && !empty($row['slug']) && !empty($row['category_slug'])) {
            \App\Services\IndexNow::ping(url('/' . $row['category_slug'] . '/' . $row['slug']));
        }
    }

    public static function recent(int $limit = 8, ?int $excludeCategoryId = null): array
    {
        $sql = 'SELECT p.id, p.title, p.slug, p.excerpt, p.cover_image, p.published_at,
                       p.reading_minutes, p.view_count,
                       c.name AS category_name, c.slug AS category_slug,
                       u.id AS author_id, u.name AS author_name, u.slug AS author_slug, u.avatar AS author_avatar
                FROM posts p
                INNER JOIN categories c ON c.id = p.category_id
                INNER JOIN users u ON u.id = p.user_id
                WHERE p.status = "published"';
        $params = [];
        if ($excludeCategoryId !== null) {
            $sql .= ' AND p.category_id <> :ex';
            $params[':ex'] = $excludeCategoryId;
        }
        $sql .= ' ORDER BY p.published_at DESC LIMIT ' . max(1, $limit);
        return Database::instance()->fetchAll($sql, $params);
    }

    /**
     * Most-viewed posts in the last `$daysWindow` days (decays naturally
     * with `published_at`). Add live Redis counters in the controller.
     */
    public static function trending(int $limit = 6, int $daysWindow = 30): array
    {
        return Database::instance()->fetchAll(
            'SELECT p.id, p.title, p.slug, p.cover_image, p.published_at, p.view_count, p.reading_minutes,
                    c.name AS category_name, c.slug AS category_slug,
                    u.name AS author_name, u.slug AS author_slug
             FROM posts p
             INNER JOIN categories c ON c.id = p.category_id
             INNER JOIN users u ON u.id = p.user_id
             WHERE p.status = "published"
               AND p.published_at >= DATE_SUB(NOW(), INTERVAL ' . max(1, $daysWindow) . ' DAY)
             ORDER BY p.view_count DESC, p.published_at DESC
             LIMIT ' . max(1, $limit)
        );
    }

    public static function mostCommented(int $limit = 6): array
    {
        return Database::instance()->fetchAll(
            'SELECT p.id, p.title, p.slug, p.comment_count, p.published_at,
                    c.name AS category_name, c.slug AS category_slug
             FROM posts p
             INNER JOIN categories c ON c.id = p.category_id
             WHERE p.status = "published" AND p.comment_count > 0
             ORDER BY p.comment_count DESC, p.published_at DESC
             LIMIT ' . max(1, $limit)
        );
    }

    /**
     * Editörün Seçimi — admin'in elle featured işaretlediği yayında yazılar.
     * `posts.featured TINYINT(1)` kolonunu kullanır (index'li).
     */
    public static function editorsPicks(int $limit = 4, ?int $excludeId = null): array
    {
        $sql = 'SELECT p.id, p.title, p.slug, p.excerpt, p.cover_image, p.published_at,
                       p.reading_minutes, p.view_count,
                       c.name AS category_name, c.slug AS category_slug,
                       u.name AS author_name, u.slug AS author_slug, u.avatar AS author_avatar
                FROM posts p
                INNER JOIN categories c ON c.id = p.category_id
                INNER JOIN users u ON u.id = p.user_id
                WHERE p.status = "published" AND p.featured = 1';
        $params = [];
        if ($excludeId !== null) {
            $sql .= ' AND p.id <> :ex';
            $params[':ex'] = $excludeId;
        }
        $sql .= ' ORDER BY p.published_at DESC LIMIT ' . max(1, $limit);
        return Database::instance()->fetchAll($sql, $params);
    }

    /**
     * Aynı kategoride önceki & sonraki yayınlanmış yazı (published_at sıralı).
     * Yazı sayfası altında "← Önceki | Sonraki →" navigasyonu için.
     *
     * @return array{prev: ?array, next: ?array}
     */
    public static function prevNextInCategory(int $postId, int $categoryId, string $publishedAt): array
    {
        $db = Database::instance();
        $prev = $db->fetch(
            'SELECT p.id, p.title, p.slug, p.published_at, c.slug AS category_slug
             FROM posts p
             INNER JOIN categories c ON c.id = p.category_id
             WHERE p.status = "published"
               AND p.category_id = :cid
               AND p.id <> :ex
               AND p.published_at < :pa
             ORDER BY p.published_at DESC
             LIMIT 1',
            [':cid' => $categoryId, ':ex' => $postId, ':pa' => $publishedAt]
        );
        $next = $db->fetch(
            'SELECT p.id, p.title, p.slug, p.published_at, c.slug AS category_slug
             FROM posts p
             INNER JOIN categories c ON c.id = p.category_id
             WHERE p.status = "published"
               AND p.category_id = :cid
               AND p.id <> :ex
               AND p.published_at > :pa
             ORDER BY p.published_at ASC
             LIMIT 1',
            [':cid' => $categoryId, ':ex' => $postId, ':pa' => $publishedAt]
        );
        return ['prev' => $prev, 'next' => $next];
    }

    /**
     * Bir yazarın son yayınlanmış yazıları (mevcut yazıyı hariç tutarak).
     * "Yazar Bio Kartı" partial'ında "Diğer Yazıları" listesi için.
     */
    public static function recentByAuthor(int $userId, int $limit = 3, ?int $excludeId = null): array
    {
        $sql = 'SELECT p.id, p.title, p.slug, p.cover_image, p.published_at, p.reading_minutes,
                       c.slug AS category_slug, c.name AS category_name
                FROM posts p
                INNER JOIN categories c ON c.id = p.category_id
                WHERE p.user_id = :uid AND p.status = "published"';
        $params = [':uid' => $userId];
        if ($excludeId !== null) {
            $sql .= ' AND p.id <> :ex';
            $params[':ex'] = $excludeId;
        }
        $sql .= ' ORDER BY p.published_at DESC LIMIT ' . max(1, $limit);
        return Database::instance()->fetchAll($sql, $params);
    }

    /**
     * Same-category published siblings, used by post.php "Related" block.
     */
    public static function related(int $excludeId, int $categoryId, int $limit = 4): array
    {
        return Database::instance()->fetchAll(
            'SELECT p.id, p.title, p.slug, p.excerpt, p.cover_image, p.published_at,
                    p.reading_minutes, c.slug AS category_slug
             FROM posts p
             INNER JOIN categories c ON c.id = p.category_id
             WHERE p.status = "published"
               AND p.category_id = :cid
               AND p.id <> :ex
             ORDER BY p.published_at DESC
             LIMIT ' . max(1, $limit),
            [':cid' => $categoryId, ':ex' => $excludeId]
        );
    }

    /**
     * Tag-aware ilgili yazılar (Tier 2.3).
     * Sıra:
     *   1) Aynı tag'i paylaşan yazılar (eşleşen tag count desc → en yakın)
     *   2) Aynı kategori (yeterli değilse)
     *   3) Son yayınlananlar (geri kalan boşluk)
     *
     * @param array $post  ['id','category_id'] zorunlu
     * @return array<int,array>
     */
    public static function relatedSmart(array $post, int $limit = 4): array
    {
        $excludeId = (int) ($post['id'] ?? 0);
        $categoryId = (int) ($post['category_id'] ?? 0);
        if ($excludeId <= 0) {
            return [];
        }
        $db = Database::instance();
        $collected = [];
        $seen = [$excludeId];

        // 1) Tag-bazlı (aynı tag'i paylaşan yazılar, paylaşılan tag sayısına göre sırala)
        $tagRows = $db->fetchAll(
            'SELECT p.id, p.title, p.slug, p.excerpt, p.cover_image, p.published_at,
                    p.reading_minutes, c.slug AS category_slug,
                    COUNT(pt2.tag_id) AS shared_tags
             FROM post_tag pt1
             INNER JOIN post_tag pt2 ON pt2.tag_id = pt1.tag_id AND pt2.post_id != pt1.post_id
             INNER JOIN posts p ON p.id = pt2.post_id
             INNER JOIN categories c ON c.id = p.category_id
             WHERE pt1.post_id = :pid AND p.status = "published"
             GROUP BY p.id
             ORDER BY shared_tags DESC, p.published_at DESC
             LIMIT ' . max(1, $limit),
            [':pid' => $excludeId]
        );
        foreach ($tagRows as $r) {
            if (in_array((int) $r['id'], $seen, true)) continue;
            $collected[] = $r;
            $seen[] = (int) $r['id'];
            if (count($collected) >= $limit) return $collected;
        }

        // 2) Aynı kategori
        if ($categoryId > 0) {
            $place = implode(',', array_map('intval', $seen));
            $catRows = $db->fetchAll(
                'SELECT p.id, p.title, p.slug, p.excerpt, p.cover_image, p.published_at,
                        p.reading_minutes, c.slug AS category_slug
                 FROM posts p
                 INNER JOIN categories c ON c.id = p.category_id
                 WHERE p.status = "published"
                   AND p.category_id = :cid
                   AND p.id NOT IN (' . $place . ')
                 ORDER BY p.published_at DESC
                 LIMIT ' . max(1, $limit - count($collected)),
                [':cid' => $categoryId]
            );
            foreach ($catRows as $r) {
                $collected[] = $r;
                $seen[] = (int) $r['id'];
                if (count($collected) >= $limit) return $collected;
            }
        }

        // 3) Son yayınlananlar
        if (count($collected) < $limit) {
            $place = implode(',', array_map('intval', $seen));
            $recentRows = $db->fetchAll(
                'SELECT p.id, p.title, p.slug, p.excerpt, p.cover_image, p.published_at,
                        p.reading_minutes, c.slug AS category_slug
                 FROM posts p
                 INNER JOIN categories c ON c.id = p.category_id
                 WHERE p.status = "published"
                   AND p.id NOT IN (' . $place . ')
                 ORDER BY p.published_at DESC
                 LIMIT ' . max(1, $limit - count($collected))
            );
            foreach ($recentRows as $r) {
                $collected[] = $r;
                if (count($collected) >= $limit) break;
            }
        }

        return $collected;
    }

    public static function bumpViewCount(int $id, int $delta = 1): void
    {
        if ($delta <= 0) {
            return;
        }
        Database::instance()->run(
            'UPDATE posts SET view_count = view_count + :n, updated_at = updated_at WHERE id = :id',
            [':n' => $delta, ':id' => $id]
        );
    }

    /**
     * Full-text site içi arama (MySQL FULLTEXT, BOOLEAN MODE + prefix match).
     * Migration 018 (FULLTEXT key) gerektirir.
     *
     * @return array<int,array>  id, title, slug, excerpt, cover_image, published_at,
     *                            reading_minutes, view_count, category_*, author_*, score
     */
    public static function search(string $q, int $limit = 20, int $offset = 0): array
    {
        $q = trim($q);
        if ($q === '' || mb_strlen($q) < 2) {
            return [];
        }

        // Kelimeleri ayır, prefix match için * ekle, boolean MODE'a uygun hale getir
        $words = array_filter((array) preg_split('/\s+/u', $q));
        $tokens = [];
        foreach ($words as $w) {
            $clean = (string) preg_replace('/[^\p{L}\p{N}]/u', '', $w);
            if (mb_strlen($clean) >= 2) {
                $tokens[] = '+' . $clean . '*';
            }
        }
        if (!$tokens) {
            return [];
        }
        $boolean = implode(' ', $tokens);

        return Database::instance()->fetchAll(
            'SELECT p.id, p.title, p.slug, p.excerpt, p.cover_image, p.published_at,
                    p.reading_minutes, p.view_count,
                    c.name AS category_name, c.slug AS category_slug,
                    u.name AS author_name, u.slug AS author_slug,
                    MATCH(p.title, p.excerpt, p.body) AGAINST (:q1 IN BOOLEAN MODE) AS score
             FROM posts p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN users u ON u.id = p.user_id
             WHERE p.status = "published"
               AND MATCH(p.title, p.excerpt, p.body) AGAINST (:q2 IN BOOLEAN MODE)
             ORDER BY score DESC, p.published_at DESC
             LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset),
            [':q1' => $boolean, ':q2' => $boolean]
        );
    }
}
