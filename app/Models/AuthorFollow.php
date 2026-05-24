<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Yazara Abone (Tier 7 — Engagement).
 *
 * Bir üye bir yazarın yeni içeriği yayınlandığında bildirim alır.
 * Toggle pattern (takip et / takibi bırak).
 */
final class AuthorFollow
{
    public static function toggle(int $followerId, int $authorId): bool
    {
        if ($followerId === $authorId) {
            return false; // kendini takip edemez
        }
        $db = Database::instance();
        try {
            $existing = $db->fetch(
                'SELECT id FROM author_follows WHERE follower_id = :f AND author_id = :a LIMIT 1',
                [':f' => $followerId, ':a' => $authorId]
            );
            if ($existing) {
                $db->delete('author_follows', 'id = :id', [':id' => (int) $existing['id']]);
                return false;
            }
            $db->insert('author_follows', [
                'follower_id'  => $followerId,
                'author_id'    => $authorId,
                'notify_email' => 1,
            ]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function isFollowing(int $followerId, int $authorId): bool
    {
        try {
            return (bool) Database::instance()->fetchColumn(
                'SELECT id FROM author_follows WHERE follower_id = :f AND author_id = :a LIMIT 1',
                [':f' => $followerId, ':a' => $authorId]
            );
        } catch (\Throwable) {
            return false;
        }
    }

    public static function followerCountFor(int $authorId): int
    {
        try {
            return (int) Database::instance()->fetchColumn(
                'SELECT COUNT(*) FROM author_follows WHERE author_id = :a',
                [':a' => $authorId]
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Yazarın takipçilerinin mail+id'lerini getir (yayın bildirimi için).
     */
    public static function notifiableFollowers(int $authorId): array
    {
        try {
            return Database::instance()->fetchAll(
                'SELECT u.id, u.name, u.email FROM author_follows af
                 INNER JOIN users u ON u.id = af.follower_id
                 WHERE af.author_id = :a AND af.notify_email = 1
                   AND u.status = "active"
                   AND u.deleted_at IS NULL',
                [':a' => $authorId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Kullanıcının takip ettiği yazarlar — profile sayfasında listelemek için.
     */
    public static function followingFor(int $followerId): array
    {
        try {
            return Database::instance()->fetchAll(
                'SELECT u.id, u.name, u.slug, u.avatar FROM author_follows af
                 INNER JOIN users u ON u.id = af.author_id
                 WHERE af.follower_id = :f
                 ORDER BY af.created_at DESC',
                [':f' => $followerId]
            );
        } catch (\Throwable) {
            return [];
        }
    }
}
