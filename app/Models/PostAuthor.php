<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Post-yazar pivot. Bir yazının primary + co-author + editor ilişkilerini tutar.
 *
 * Kullanım:
 *   PostAuthor::listFor($postId)       — tüm yazarlar (role'lere göre sırayla)
 *   PostAuthor::coAuthorsFor($postId)  — sadece co_author rolündekiler
 *   PostAuthor::syncCoAuthors($postId, [userId1, userId2])
 *   PostAuthor::primaryFor($postId)    — asıl yazarın user kaydı
 */
final class PostAuthor
{
    public const ROLE_PRIMARY = 'primary';
    public const ROLE_CO = 'co_author';
    public const ROLE_EDITOR = 'editor';

    /**
     * Bir yazının tüm yazarları (user details ile).
     * Sıra: primary → co_authors (position) → editor.
     */
    public static function listFor(int $postId): array
    {
        return Database::instance()->fetchAll(
            'SELECT pa.role, pa.position, u.id, u.name, u.slug, u.email, u.avatar, u.bio
             FROM post_authors pa
             INNER JOIN users u ON u.id = pa.user_id
             WHERE pa.post_id = :pid
             ORDER BY FIELD(pa.role, "primary", "co_author", "editor"), pa.position ASC',
            [':pid' => $postId]
        );
    }

    /**
     * Bir yazının asıl yazarı.
     */
    public static function primaryFor(int $postId): ?array
    {
        return Database::instance()->fetch(
            'SELECT u.* FROM post_authors pa
             INNER JOIN users u ON u.id = pa.user_id
             WHERE pa.post_id = :pid AND pa.role = "primary"
             LIMIT 1',
            [':pid' => $postId]
        );
    }

    /**
     * Bir yazının sadece co-author'ları (primary hariç).
     */
    public static function coAuthorsFor(int $postId): array
    {
        return Database::instance()->fetchAll(
            'SELECT u.id, u.name, u.slug, u.avatar
             FROM post_authors pa
             INNER JOIN users u ON u.id = pa.user_id
             WHERE pa.post_id = :pid AND pa.role = "co_author"
             ORDER BY pa.position ASC',
            [':pid' => $postId]
        );
    }

    /**
     * Bir post'un primary yazarını set/değiştir. (Genelde Post::create sonrası).
     */
    public static function setPrimary(int $postId, int $userId): void
    {
        $db = Database::instance();
        // Eski primary'i co-author yap (silme yerine, history korunur)
        $db->run(
            'UPDATE post_authors SET role = "co_author"
             WHERE post_id = :pid AND role = "primary" AND user_id != :uid',
            [':pid' => $postId, ':uid' => $userId]
        );
        $db->run(
            'INSERT INTO post_authors (post_id, user_id, role, position)
             VALUES (:pid, :uid, "primary", 1)
             ON DUPLICATE KEY UPDATE role = "primary", position = 1',
            [':pid' => $postId, ':uid' => $userId]
        );
    }

    /**
     * Co-author listesini senkronize et — eksikleri ekle, fazlaları kaldır.
     * Primary yazar etkilenmez.
     *
     * @param int[] $userIds
     */
    public static function syncCoAuthors(int $postId, array $userIds): void
    {
        $db = Database::instance();
        $clean = array_values(array_unique(array_filter(array_map('intval', $userIds))));

        // Eski co-author kayıtlarını sil
        $db->run('DELETE FROM post_authors WHERE post_id = :pid AND role = "co_author"',
            [':pid' => $postId]);

        // Yenilerini ekle
        $position = 2;
        foreach ($clean as $uid) {
            $db->run(
                'INSERT IGNORE INTO post_authors (post_id, user_id, role, position)
                 VALUES (:pid, :uid, "co_author", :pos)',
                [':pid' => $postId, ':uid' => $uid, ':pos' => $position++]
            );
        }
    }

    /**
     * Author search — co-author seçici autocomplete için.
     */
    public static function searchUsers(string $query, int $excludeUserId = 0, int $limit = 10): array
    {
        $q = '%' . $query . '%';
        return Database::instance()->fetchAll(
            'SELECT id, name, slug, avatar, email, role
             FROM users
             WHERE status = "active"
               AND id != :ex
               AND (name LIKE :q OR email LIKE :q OR slug LIKE :q)
             ORDER BY name ASC
             LIMIT ' . max(1, min(50, $limit)),
            [':q' => $q, ':ex' => $excludeUserId]
        );
    }
}
