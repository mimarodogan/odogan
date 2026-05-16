<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Tag (etiket) — yazılara çoklu eklenebilen kısa anahtar kelimeler.
 * Slug otomatik üretilir (Türkçe karakter normalize).
 */
final class Tag
{
    public static function findById(int $id): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM tags WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM tags WHERE slug = :s LIMIT 1',
            [':s' => $slug]
        );
    }

    public static function findOrCreate(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Tag name boş olamaz');
        }
        $slug = self::slugify($name);
        $existing = self::findBySlug($slug);
        if ($existing) {
            return $existing;
        }
        $id = (int) Database::instance()->insert('tags', [
            'name' => mb_substr($name, 0, 64),
            'slug' => $slug,
            'post_count' => 0,
        ]);
        return self::findById($id) ?? [
            'id' => $id, 'name' => $name, 'slug' => $slug, 'post_count' => 0,
        ];
    }

    /**
     * Bir post'un tag'lerini senkronize et — eksikleri ekle, fazlaları sil.
     * @param string[] $names
     * @return array<int,array>  Bağlanmış tag rows
     */
    public static function syncForPost(int $postId, array $names): array
    {
        $db = Database::instance();

        // Eski bağları sil — basit yaklaşım (yüksek tag count'lu post yok)
        $db->run('DELETE FROM post_tag WHERE post_id = :pid', [':pid' => $postId]);

        $clean = [];
        foreach ($names as $n) {
            $t = trim((string) $n);
            if ($t === '' || mb_strlen($t) > 64) continue;
            if (in_array(mb_strtolower($t), array_map('mb_strtolower', $clean), true)) continue;
            $clean[] = $t;
        }
        // En fazla 10 etiket
        $clean = array_slice($clean, 0, 10);

        $attached = [];
        foreach ($clean as $name) {
            $tag = self::findOrCreate($name);
            $db->run(
                'INSERT IGNORE INTO post_tag (post_id, tag_id) VALUES (:pid, :tid)',
                [':pid' => $postId, ':tid' => (int) $tag['id']]
            );
            $attached[] = $tag;
        }

        // post_count'ları güncelle (etkilenen tüm tag'ler için)
        self::recountAll();

        return $attached;
    }

    /**
     * Bir post'un tag listesi.
     */
    public static function listForPost(int $postId): array
    {
        return Database::instance()->fetchAll(
            'SELECT t.id, t.name, t.slug
             FROM tags t
             INNER JOIN post_tag pt ON pt.tag_id = t.id
             WHERE pt.post_id = :pid
             ORDER BY t.name ASC',
            [':pid' => $postId]
        );
    }

    /**
     * Bir tag'in yazılarını getir (kategori arşivi mantığı).
     */
    public static function postsForTag(int $tagId, int $limit = 30, int $offset = 0): array
    {
        return Database::instance()->fetchAll(
            'SELECT p.id, p.title, p.slug, p.excerpt, p.cover_image, p.published_at,
                    p.reading_minutes,
                    c.name AS category_name, c.slug AS category_slug,
                    u.name AS author_name, u.slug AS author_slug
             FROM posts p
             INNER JOIN post_tag pt ON pt.post_id = p.id
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN users u ON u.id = p.user_id
             WHERE pt.tag_id = :tid AND p.status = "published"
             ORDER BY p.published_at DESC
             LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset),
            [':tid' => $tagId]
        );
    }

    public static function popular(int $limit = 20): array
    {
        return Database::instance()->fetchAll(
            'SELECT id, name, slug, post_count
             FROM tags
             WHERE post_count > 0
             ORDER BY post_count DESC, name ASC
             LIMIT ' . max(1, $limit)
        );
    }

    /**
     * Tüm tag'lerin post_count alanını yeniden hesaplar.
     */
    public static function recountAll(): void
    {
        Database::instance()->run(
            'UPDATE tags t
             LEFT JOIN (
                SELECT pt.tag_id, COUNT(*) AS n
                FROM post_tag pt
                INNER JOIN posts p ON p.id = pt.post_id
                WHERE p.status = "published"
                GROUP BY pt.tag_id
             ) c ON c.tag_id = t.id
             SET t.post_count = COALESCE(c.n, 0)'
        );
    }

    /**
     * Türkçe-uyumlu slug üretici.
     */
    public static function slugify(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $tr = ['ç'=>'c','ğ'=>'g','ı'=>'i','ö'=>'o','ş'=>'s','ü'=>'u',
               'Ç'=>'c','Ğ'=>'g','İ'=>'i','Ö'=>'o','Ş'=>'s','Ü'=>'u'];
        $s = strtr($s, $tr);
        $s = (string) preg_replace('/[^a-z0-9]+/', '-', $s);
        $s = trim($s, '-');
        return mb_substr($s, 0, 80) ?: 'tag';
    }
}
