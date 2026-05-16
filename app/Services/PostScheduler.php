<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Post;

/**
 * Pulls due-by-now scheduled posts and transitions them to "published".
 * Called from the bootstrap opportunistic hook on every request (rate-limited
 * to once per minute). Idempotent.
 */
final class PostScheduler
{
    public static function dueScheduled(int $limit = 50): array
    {
        return Database::instance()->fetchAll(
            'SELECT id, title, slug, category_id FROM posts
             WHERE status = "scheduled" AND published_at IS NOT NULL AND published_at <= NOW()
             ORDER BY published_at ASC
             LIMIT ' . max(1, $limit)
        );
    }

    public static function scheduledCount(): int
    {
        return (int) Database::instance()->fetchColumn(
            'SELECT COUNT(*) FROM posts WHERE status = "scheduled"'
        );
    }

    public static function publishDue(): int
    {
        $rows = self::dueScheduled(100);
        $n = 0;
        foreach ($rows as $r) {
            Post::transition(
                (int) $r['id'],
                Post::STATUS_SCHEDULED,
                Post::STATUS_PUBLISHED,
                null,
                'auto-published'
            );
            Logger::info('post.auto-published', ['post_id' => (int) $r['id'], 'title' => $r['title']], 'editorial');
            $n++;
        }
        return $n;
    }

    /**
     * Accepts <input type="datetime-local"> values like "2026-06-15T09:30"
     * and emits a MySQL DATETIME. Returns null if blank or in the past.
     */
    public static function parseInput(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $ts = strtotime($raw);
        if ($ts === false || $ts <= time()) {
            return null;
        }
        return date('Y-m-d H:i:s', $ts);
    }
}
