<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Periodically checks every external link in published posts.
 * Stores results in `link_checks` so the admin can see what's broken.
 *
 * Designed to be cron-driven (daily-ish). Each invocation processes a
 * bounded number of links so it never thrashes shared hosting.
 */
final class LinkChecker
{
    public const TIMEOUT = 6;
    public const HEAD_FAILS_FALLBACK_GET = true;

    /**
     * @return array{checked:int, broken:int}
     */
    public static function scanAll(int $postLimit = 25, int $linkLimit = 100): array
    {
        $posts = Database::instance()->fetchAll(
            'SELECT id, body, body_format FROM posts
             WHERE status = "published"
             ORDER BY (SELECT MAX(last_checked_at) FROM link_checks lc WHERE lc.post_id = posts.id) ASC,
                      published_at DESC
             LIMIT ' . max(1, $postLimit)
        );
        $checked = 0;
        $broken = 0;
        foreach ($posts as $p) {
            $urls = self::extractUrls((string) $p['body'], (string) $p['body_format']);
            foreach ($urls as $u) {
                if ($checked >= $linkLimit) break 2;
                $r = self::probe($u);
                self::record((int) $p['id'], $u, $r);
                $checked++;
                if ($r['ok'] === false) $broken++;
            }
        }
        return ['checked' => $checked, 'broken' => $broken];
    }

    /**
     * @return array<int,string>
     */
    public static function extractUrls(string $body, string $format = 'markdown'): array
    {
        // Convert to HTML once, then extract <a href>.
        $html = $format === 'html'
            ? MarkdownService::fromHtml($body)
            : MarkdownService::toHtml($body);
        $out = [];
        if (preg_match_all('#<a\s+[^>]*href=(["\'])(https?://[^"\']+)\1#i', $html, $m)) {
            $out = $m[2];
        }
        // Plain markdown leftovers (in case parsedown missed them)
        if ($format === 'markdown'
            && preg_match_all('#\]\((https?://[^)\s]+)\)#i', $body, $m2)) {
            $out = array_merge($out, $m2[1]);
        }
        return array_values(array_unique($out));
    }

    /**
     * @return array{ok:bool, status:int, error:?string}
     */
    public static function probe(string $url): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
            CURLOPT_USERAGENT => 'OtoriteLinkChecker/1.0',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch) ?: null;
        curl_close($ch);

        // Some servers reject HEAD — retry GET once.
        if (self::HEAD_FAILS_FALLBACK_GET && ($status === 405 || $status === 0 || $status >= 500)) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => self::TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
                CURLOPT_USERAGENT => 'OtoriteLinkChecker/1.0',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_RANGE => '0-1024',
            ]);
            curl_exec($ch);
            $s2 = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $e2 = curl_error($ch) ?: null;
            curl_close($ch);
            if ($s2 > 0) {
                $status = $s2;
                $err = $e2;
            }
        }
        $ok = $status >= 200 && $status < 400;
        return ['ok' => $ok, 'status' => $status, 'error' => $ok ? null : $err];
    }

    private static function record(int $postId, string $url, array $r): void
    {
        $existing = Database::instance()->fetch(
            'SELECT id FROM link_checks WHERE post_id = :pid AND url = :u LIMIT 1',
            [':pid' => $postId, ':u' => $url]
        );
        if ($existing) {
            Database::instance()->update('link_checks', [
                'status_code' => $r['status'],
                'error' => $r['error'] ? mb_substr($r['error'], 0, 255) : null,
                'last_checked_at' => date('Y-m-d H:i:s'),
                'resolved' => $r['ok'] ? 1 : 0,
            ], 'id = :wid', [':wid' => (int) $existing['id']]);
        } else {
            Database::instance()->insert('link_checks', [
                'post_id' => $postId,
                'url' => mb_substr($url, 0, 500),
                'status_code' => $r['status'],
                'error' => $r['error'] ? mb_substr($r['error'], 0, 255) : null,
                'resolved' => $r['ok'] ? 1 : 0,
            ]);
        }
    }

    public static function listBroken(int $limit = 200): array
    {
        return Database::instance()->fetchAll(
            'SELECT lc.*, p.title AS post_title, p.slug AS post_slug,
                    c.slug AS category_slug
             FROM link_checks lc
             INNER JOIN posts p ON p.id = lc.post_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE lc.resolved = 0
             ORDER BY lc.last_checked_at DESC
             LIMIT ' . max(1, $limit)
        );
    }

    public static function brokenCount(): int
    {
        try {
            return (int) Database::instance()->fetchColumn(
                'SELECT COUNT(*) FROM link_checks WHERE resolved = 0'
            );
        } catch (\Throwable) {
            // Table missing (migration not run yet) — degrade gracefully.
            return 0;
        }
    }
}
