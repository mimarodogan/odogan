<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Emoji Reactions (Tier 8).
 *
 * 6 emoji set: 👍 ❤ 🔥 💡 😮 🙏
 * Toggle pattern — bir kullanıcı bir emojiye sadece bir kez basabilir.
 */
final class PostReaction
{
    public const EMOJIS = [
        'thumbs'  => '👍',
        'heart'   => '❤️',
        'fire'    => '🔥',
        'idea'    => '💡',
        'wow'     => '😮',
        'thanks'  => '🙏',
    ];

    public static function toggle(int $postId, string $reaction, ?int $userId, ?string $ip): bool
    {
        if (!isset(self::EMOJIS[$reaction])) return false;
        $db = Database::instance();
        $ipHash = $ip ? hash('sha256', (string) $ip . '|odogan-react-salt') : null;
        try {
            if ($userId) {
                $existing = $db->fetch(
                    'SELECT id FROM post_reactions WHERE post_id = :pid AND user_id = :uid AND reaction = :r LIMIT 1',
                    [':pid' => $postId, ':uid' => $userId, ':r' => $reaction]
                );
            } else {
                $existing = $ipHash ? $db->fetch(
                    'SELECT id FROM post_reactions WHERE post_id = :pid AND ip_hash = :ih AND user_id IS NULL AND reaction = :r LIMIT 1',
                    [':pid' => $postId, ':ih' => $ipHash, ':r' => $reaction]
                ) : null;
            }
            if ($existing) {
                $db->delete('post_reactions', 'id = :id', [':id' => (int) $existing['id']]);
                self::recountForPost($postId);
                return false;
            }
            $db->insert('post_reactions', [
                'post_id' => $postId,
                'user_id' => $userId,
                'ip_hash' => $ipHash,
                'reaction' => $reaction,
            ]);
            self::recountForPost($postId);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Yazıdaki emoji sayıları + bu kullanıcının verdiği reaction'lar.
     * @return array{counts: array<string,int>, mine: string[]}
     */
    public static function summary(int $postId, ?int $userId, ?string $ip): array
    {
        $counts = array_fill_keys(array_keys(self::EMOJIS), 0);
        $mine = [];
        try {
            $rows = Database::instance()->fetchAll(
                'SELECT reaction, COUNT(*) AS c FROM post_reactions WHERE post_id = :pid GROUP BY reaction',
                [':pid' => $postId]
            );
            foreach ($rows as $r) {
                if (isset($counts[$r['reaction']])) {
                    $counts[$r['reaction']] = (int) $r['c'];
                }
            }
            if ($userId) {
                $myRows = Database::instance()->fetchAll(
                    'SELECT reaction FROM post_reactions WHERE post_id = :pid AND user_id = :uid',
                    [':pid' => $postId, ':uid' => $userId]
                );
                foreach ($myRows as $r) $mine[] = $r['reaction'];
            } elseif ($ip) {
                $ipHash = hash('sha256', (string) $ip . '|odogan-react-salt');
                $myRows = Database::instance()->fetchAll(
                    'SELECT reaction FROM post_reactions WHERE post_id = :pid AND ip_hash = :ih AND user_id IS NULL',
                    [':pid' => $postId, ':ih' => $ipHash]
                );
                foreach ($myRows as $r) $mine[] = $r['reaction'];
            }
        } catch (\Throwable) {}
        return ['counts' => $counts, 'mine' => $mine];
    }

    public static function recountForPost(int $postId): void
    {
        try {
            $n = (int) Database::instance()->fetchColumn(
                'SELECT COUNT(*) FROM post_reactions WHERE post_id = :pid',
                [':pid' => $postId]
            );
            Database::instance()->update('posts', ['reaction_count' => $n], 'id = :id', [':id' => $postId]);
        } catch (\Throwable) {}
    }
}
