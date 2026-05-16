<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Comment
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SPAM = 'spam';

    public static function findById(int $id): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM comments WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
    }

    public static function approvedForPost(int $postId): array
    {
        return Database::instance()->fetchAll(
            'SELECT c.*, u.name AS user_name, u.slug AS user_slug
             FROM comments c
             LEFT JOIN users u ON u.id = c.user_id
             WHERE c.post_id = :pid AND c.status = "approved"
             ORDER BY c.parent_id IS NOT NULL, c.created_at ASC',
            [':pid' => $postId]
        );
    }

    public static function pending(int $limit = 50): array
    {
        return Database::instance()->fetchAll(
            'SELECT c.*, p.title AS post_title, p.slug AS post_slug,
                    cat.slug AS category_slug,
                    u.name AS user_name
             FROM comments c
             INNER JOIN posts p ON p.id = c.post_id
             LEFT JOIN categories cat ON cat.id = p.category_id
             LEFT JOIN users u ON u.id = c.user_id
             WHERE c.status = "pending"
             ORDER BY c.created_at ASC
             LIMIT ' . max(1, $limit)
        );
    }

    public static function create(array $data): int
    {
        $data['status'] = $data['status'] ?? self::STATUS_PENDING;
        $id = (int) Database::instance()->insert('comments', $data);
        return $id;
    }

    public static function setStatus(int $id, string $status): void
    {
        if (!in_array($status, [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_SPAM], true)) {
            return;
        }
        $row = self::findById($id);
        if ($row === null) {
            return;
        }
        Database::instance()->update('comments', ['status' => $status], 'id = :wid', [':wid' => $id]);
        self::recountForPost((int) $row['post_id']);
        \App\Core\Cache\CacheManager::driver()->invalidateTags([
            'post:' . (int) $row['post_id'], 'home',
        ]);
    }

    public static function delete(int $id): void
    {
        $row = self::findById($id);
        if ($row === null) {
            return;
        }
        Database::instance()->delete('comments', 'id = :wid', [':wid' => $id]);
        self::recountForPost((int) $row['post_id']);
    }

    /**
     * Recalculate post.comment_count from approved comments. Uses two separate
     * statements because PDO with emulate_prepares=false rejects repeated
     * placeholders in a single query.
     */
    private static function recountForPost(int $postId): void
    {
        $count = (int) Database::instance()->fetchColumn(
            'SELECT COUNT(*) FROM comments WHERE post_id = :pid AND status = "approved"',
            [':pid' => $postId]
        );
        // Preserve updated_at so a comment count bump doesn't reset content
        // freshness (the maintenance scanner relies on updated_at).
        Database::instance()->run(
            'UPDATE posts SET comment_count = :n, updated_at = updated_at WHERE id = :pid',
            [':n' => $count, ':pid' => $postId]
        );
    }

    public static function rateLimitOk(string $ip, int $perMinute = 3): bool
    {
        $r = (int) Database::instance()->fetchColumn(
            'SELECT COUNT(*) FROM comments
             WHERE ip_address = :ip AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)',
            [':ip' => $ip]
        );
        return $r < $perMinute;
    }
}
