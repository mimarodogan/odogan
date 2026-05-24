<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Post Clap (Medium-vari beğeni — Tier 7).
 *
 * Bir kullanıcı bir yazıya 1-50 arası clap atabilir. Anonim ziyaretçi de
 * IP hash ile atabilir (rate limiting). Toplam clap posts.clap_count'a denormalize.
 */
final class PostClap
{
    private const MAX_PER_USER = 50;

    /**
     * Clap ekle/güncelle. Çağıran logged-in user veya anonim olabilir.
     * @return int Yazının yeni toplam clap_count'u
     */
    public static function clap(int $postId, ?int $userId, ?string $ip): int
    {
        $db = Database::instance();
        $ipHash = $ip ? hash('sha256', (string) $ip . '|odogan-clap-salt') : null;

        try {
            if ($userId) {
                $existing = $db->fetch(
                    'SELECT id, count FROM post_claps WHERE post_id = :pid AND user_id = :uid LIMIT 1',
                    [':pid' => $postId, ':uid' => $userId]
                );
            } else {
                $existing = $ipHash ? $db->fetch(
                    'SELECT id, count FROM post_claps WHERE post_id = :pid AND ip_hash = :ih AND user_id IS NULL LIMIT 1',
                    [':pid' => $postId, ':ih' => $ipHash]
                ) : null;
            }

            if ($existing) {
                $newCount = min(self::MAX_PER_USER, (int) $existing['count'] + 1);
                $db->update('post_claps', ['count' => $newCount], 'id = :id', [':id' => (int) $existing['id']]);
            } else {
                $db->insert('post_claps', [
                    'post_id' => $postId,
                    'user_id' => $userId,
                    'ip_hash' => $ipHash,
                    'count'   => 1,
                ]);
            }
            self::recountForPost($postId);
            return self::totalFor($postId);
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function totalFor(int $postId): int
    {
        try {
            return (int) Database::instance()->fetchColumn(
                'SELECT COALESCE(SUM(count), 0) FROM post_claps WHERE post_id = :pid',
                [':pid' => $postId]
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function userClapCount(int $postId, ?int $userId, ?string $ip): int
    {
        if (!$userId && !$ip) return 0;
        try {
            if ($userId) {
                return (int) Database::instance()->fetchColumn(
                    'SELECT count FROM post_claps WHERE post_id = :pid AND user_id = :uid LIMIT 1',
                    [':pid' => $postId, ':uid' => $userId]
                );
            }
            $ipHash = hash('sha256', (string) $ip . '|odogan-clap-salt');
            return (int) Database::instance()->fetchColumn(
                'SELECT count FROM post_claps WHERE post_id = :pid AND ip_hash = :ih AND user_id IS NULL LIMIT 1',
                [':pid' => $postId, ':ih' => $ipHash]
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * posts.clap_count denormalize alanını yenile.
     */
    public static function recountForPost(int $postId): void
    {
        try {
            $total = self::totalFor($postId);
            Database::instance()->update('posts', ['clap_count' => $total], 'id = :id', [':id' => $postId]);
        } catch (\Throwable) {}
    }
}
