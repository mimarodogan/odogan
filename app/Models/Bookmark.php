<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Sunucu-tarafı bookmark (Tier 7 — Engagement).
 *
 * Giriş yapmış kullanıcılar yazıları DB'ye kaydeder. LocalStorage'dan
 * (Tier 5 save_post) farklı: çoklu cihaz senkron + abone bildirimleri.
 */
final class Bookmark
{
    public static function toggle(int $userId, int $postId): bool
    {
        $db = Database::instance();
        try {
            $existing = $db->fetch(
                'SELECT id FROM post_bookmarks WHERE user_id = :uid AND post_id = :pid LIMIT 1',
                [':uid' => $userId, ':pid' => $postId]
            );
            if ($existing) {
                $db->delete('post_bookmarks', 'id = :id', [':id' => (int) $existing['id']]);
                return false; // unsaved
            }
            $db->insert('post_bookmarks', ['user_id' => $userId, 'post_id' => $postId]);
            return true; // saved
        } catch (\Throwable) {
            return false;
        }
    }

    public static function isBookmarked(int $userId, int $postId): bool
    {
        try {
            return (bool) Database::instance()->fetchColumn(
                'SELECT id FROM post_bookmarks WHERE user_id = :uid AND post_id = :pid LIMIT 1',
                [':uid' => $userId, ':pid' => $postId]
            );
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Kullanıcının bookmark'larını yazı detayıyla getir.
     */
    public static function forUser(int $userId, int $limit = 50, int $offset = 0): array
    {
        try {
            return Database::instance()->fetchAll(
                'SELECT p.id, p.title, p.slug, p.excerpt, p.cover_image, p.published_at,
                        c.slug AS category_slug, c.name AS category_name,
                        u.name AS author_name, u.slug AS author_slug,
                        pb.created_at AS bookmarked_at
                 FROM post_bookmarks pb
                 INNER JOIN posts p ON p.id = pb.post_id
                 LEFT JOIN categories c ON c.id = p.category_id
                 LEFT JOIN users u ON u.id = p.user_id
                 WHERE pb.user_id = :uid AND p.status = "published"
                 ORDER BY pb.created_at DESC
                 LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset),
                [':uid' => $userId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public static function countFor(int $userId): int
    {
        try {
            return (int) Database::instance()->fetchColumn(
                'SELECT COUNT(*) FROM post_bookmarks WHERE user_id = :uid',
                [':uid' => $userId]
            );
        } catch (\Throwable) {
            return 0;
        }
    }
}
