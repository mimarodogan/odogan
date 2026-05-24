<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class PostRevision
{
    public const KEEP_LIMIT = 20;

    /**
     * Snapshot the current state of a post BEFORE applying changes.
     */
    public static function snapshot(array $post, ?int $actorId, ?string $note = null, bool $isAutosave = false): int
    {
        $id = (int) Database::instance()->insert('post_revisions', [
            'post_id' => (int) $post['id'],
            'user_id' => $actorId,
            'title' => mb_substr((string) ($post['title'] ?? ''), 0, 220),
            'excerpt' => mb_substr((string) ($post['excerpt'] ?? ''), 0, 500) ?: null,
            'body' => (string) ($post['body'] ?? ''),
            'body_format' => in_array($post['body_format'] ?? 'markdown', ['markdown', 'html'], true)
                ? $post['body_format'] : 'markdown',
            'faq_json' => $post['faq_json'] ?? null,
            'note' => $note ? mb_substr($note, 0, 255) : null,
            'is_autosave' => $isAutosave ? 1 : 0,
        ]);
        self::trimOld((int) $post['id']);
        return $id;
    }

    public static function listForPost(int $postId, int $limit = 20): array
    {
        return Database::instance()->fetchAll(
            'SELECT r.id, r.user_id, r.title, r.note, r.body_format, r.is_autosave, r.created_at,
                    u.name AS user_name
             FROM post_revisions r
             LEFT JOIN users u ON u.id = r.user_id
             WHERE r.post_id = :pid
             ORDER BY r.id DESC
             LIMIT ' . max(1, $limit),
            [':pid' => $postId]
        );
    }

    /**
     * Bir post'un en son auto-save kaydını döner (eğer 30 saniyeden eskiyse).
     * Aksi durumda null — throttle için.
     */
    public static function lastAutosave(int $postId): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM post_revisions
             WHERE post_id = :pid AND is_autosave = 1
             ORDER BY id DESC LIMIT 1',
            [':pid' => $postId]
        );
    }

    /**
     * 7 günden eski auto-save kayıtlarını temizler (housekeeping).
     */
    public static function purgeOldAutosaves(int $olderThanDays = 7): int
    {
        return (int) Database::instance()->run(
            'DELETE FROM post_revisions
             WHERE is_autosave = 1 AND created_at < DATE_SUB(NOW(), INTERVAL :d DAY)',
            [':d' => $olderThanDays]
        )->rowCount();
    }

    public static function findById(int $id): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM post_revisions WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
    }

    /**
     * Cap stored revisions per post.
     */
    private static function trimOld(int $postId): void
    {
        $ids = Database::instance()->fetchAll(
            'SELECT id FROM post_revisions WHERE post_id = :pid ORDER BY id DESC LIMIT 1000',
            [':pid' => $postId]
        );
        if (count($ids) <= self::KEEP_LIMIT) {
            return;
        }
        $toKeep = array_slice(array_column($ids, 'id'), 0, self::KEEP_LIMIT);
        $place = implode(',', array_fill(0, count($toKeep), '?'));
        $stmt = Database::instance()->pdo()->prepare(
            "DELETE FROM post_revisions WHERE post_id = ? AND id NOT IN ($place)"
        );
        $stmt->execute(array_merge([$postId], $toKeep));
    }
}
