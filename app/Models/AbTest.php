<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * A/B Headline Test (Tier 9).
 *
 * Bir yazıya 2 alternatif başlık tanımlanır; mag-card / liste sayfalarında
 * trafik rastgele bölünür, görüntülenme ve tıklama sayaçları tutulur.
 * 7 gün sonra istatistiksel kazanan otomatik seçilir veya admin manuel
 * karar verir.
 */
final class AbTest
{
    public static function findByPost(int $postId): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM ab_tests WHERE post_id = :pid LIMIT 1',
            [':pid' => $postId]
        );
    }

    public static function create(int $postId, string $variantA, string $variantB): int
    {
        return (int) Database::instance()->insert('ab_tests', [
            'post_id' => $postId,
            'variant_a' => $variantA,
            'variant_b' => $variantB,
        ]);
    }

    public static function delete(int $postId): int
    {
        return Database::instance()->delete('ab_tests', 'post_id = :pid', [':pid' => $postId]);
    }

    /**
     * Bir yazı için bu istek için hangi varyant gösterilmeli?
     * Stable cookie + post id ile tutarlı bölme.
     */
    public static function pick(int $postId, string $variantA, string $variantB): array
    {
        $cookie = $_COOKIE['ab_bucket'] ?? null;
        if (!$cookie) {
            $cookie = bin2hex(random_bytes(8));
            setcookie('ab_bucket', $cookie, [
                'expires' => time() + 86400 * 90,
                'path' => '/',
                'samesite' => 'Lax',
                'httponly' => false,
            ]);
        }
        $hash = (int) hexdec(substr(md5($cookie . ':' . $postId), 0, 8));
        $variant = ($hash % 2 === 0) ? 'a' : 'b';
        return [
            'variant' => $variant,
            'title' => $variant === 'a' ? $variantA : $variantB,
        ];
    }

    public static function bumpView(int $postId, string $variant): void
    {
        $col = $variant === 'b' ? 'views_b' : 'views_a';
        Database::instance()->run(
            "UPDATE ab_tests SET {$col} = {$col} + 1 WHERE post_id = :pid AND active = 1",
            [':pid' => $postId]
        );
    }

    public static function bumpClick(int $postId, string $variant): void
    {
        $col = $variant === 'b' ? 'clicks_b' : 'clicks_a';
        Database::instance()->run(
            "UPDATE ab_tests SET {$col} = {$col} + 1 WHERE post_id = :pid AND active = 1",
            [':pid' => $postId]
        );
    }

    public static function declareWinner(int $postId, string $winner): int
    {
        $winner = in_array($winner, ['a', 'b', 'tie'], true) ? $winner : 'tie';
        return Database::instance()->update('ab_tests', [
            'winner' => $winner,
            'active' => 0,
            'ended_at' => date('Y-m-d H:i:s'),
        ], 'post_id = :pid', [':pid' => $postId]);
    }

    public static function active(int $limit = 50): array
    {
        return Database::instance()->fetchAll(
            "SELECT ab.*, p.title AS original_title, p.slug, p.status
             FROM ab_tests ab
             INNER JOIN posts p ON p.id = ab.post_id
             WHERE ab.active = 1
             ORDER BY ab.started_at DESC LIMIT " . max(1, $limit)
        );
    }

    public static function all(int $limit = 200): array
    {
        return Database::instance()->fetchAll(
            "SELECT ab.*, p.title AS original_title, p.slug
             FROM ab_tests ab
             INNER JOIN posts p ON p.id = ab.post_id
             ORDER BY ab.started_at DESC LIMIT " . max(1, $limit)
        );
    }

    /**
     * CTR yüzdesi (clicks/views).
     */
    public static function ctr(int $views, int $clicks): float
    {
        if ($views <= 0) return 0.0;
        return round(($clicks / $views) * 100, 2);
    }
}
