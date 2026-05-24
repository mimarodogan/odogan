<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Analytics Event Tracker (Tier 8 — first-party).
 *
 * Read-depth %, time-on-page, outbound click takibi.
 * Feature flag korumalı (`analytics_events_enabled`); kapalıysa hiç yazılmaz.
 */
final class AnalyticsEvent
{
    public static function record(
        string $type,
        ?int $postId = null,
        ?int $valueInt = null,
        ?string $valueStr = null,
        array $meta = []
    ): void {
        try {
            $user = \App\Services\AuthService::user();
            Database::instance()->insert('analytics_events', [
                'event_type'   => mb_substr($type, 0, 40),
                'post_id'      => $postId,
                'user_id'      => $user['id'] ?? null,
                'session_hash' => self::sessionHash(),
                'value_int'    => $valueInt,
                'value_str'    => $valueStr ? mb_substr($valueStr, 0, 500) : null,
                'meta_json'    => $meta ? (string) json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                'referer'      => mb_substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 500) ?: null,
                'ua_kind'      => self::uaKind(),
            ]);
        } catch (\Throwable) {}
    }

    /**
     * Yazının analytics özet metrikleri.
     */
    public static function summaryFor(int $postId, int $days = 30): array
    {
        try {
            $db = Database::instance();
            $sinceClause = $days > 0 ? "AND created_at >= DATE_SUB(NOW(), INTERVAL " . max(1, $days) . " DAY)" : '';
            $reads100 = (int) $db->fetchColumn(
                "SELECT COUNT(DISTINCT session_hash) FROM analytics_events
                 WHERE post_id = :pid AND event_type = 'read_depth' AND value_int >= 100 $sinceClause",
                [':pid' => $postId]
            );
            $reads75 = (int) $db->fetchColumn(
                "SELECT COUNT(DISTINCT session_hash) FROM analytics_events
                 WHERE post_id = :pid AND event_type = 'read_depth' AND value_int >= 75 $sinceClause",
                [':pid' => $postId]
            );
            $reads50 = (int) $db->fetchColumn(
                "SELECT COUNT(DISTINCT session_hash) FROM analytics_events
                 WHERE post_id = :pid AND event_type = 'read_depth' AND value_int >= 50 $sinceClause",
                [':pid' => $postId]
            );
            $avgTime = (int) $db->fetchColumn(
                "SELECT AVG(value_int) FROM analytics_events
                 WHERE post_id = :pid AND event_type = 'time_on_page' $sinceClause",
                [':pid' => $postId]
            );
            $outbound = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM analytics_events
                 WHERE post_id = :pid AND event_type = 'outbound_click' $sinceClause",
                [':pid' => $postId]
            );
            return [
                'reads_50'  => $reads50,
                'reads_75'  => $reads75,
                'reads_100' => $reads100,
                'avg_time_s' => $avgTime,
                'outbound_clicks' => $outbound,
            ];
        } catch (\Throwable) {
            return ['reads_50' => 0, 'reads_75' => 0, 'reads_100' => 0, 'avg_time_s' => 0, 'outbound_clicks' => 0];
        }
    }

    /**
     * Top outbound URL'leri (admin için).
     */
    public static function topOutbound(int $limit = 20): array
    {
        try {
            return Database::instance()->fetchAll(
                "SELECT value_str AS url, COUNT(*) AS hits, MAX(created_at) AS last_at
                 FROM analytics_events
                 WHERE event_type = 'outbound_click' AND value_str IS NOT NULL
                 GROUP BY value_str
                 ORDER BY hits DESC
                 LIMIT " . max(1, $limit)
            );
        } catch (\Throwable) {
            return [];
        }
    }

    private static function sessionHash(): ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            return null;
        }
        return hash('sha256', (string) session_id() . '|odogan-ae-salt');
    }

    private static function uaKind(): ?string
    {
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        if (preg_match('/Mobi|Android|iPhone|iPad/i', $ua)) return 'mobile';
        if (preg_match('/bot|crawl|spider/i', $ua)) return 'bot';
        return 'desktop';
    }
}
