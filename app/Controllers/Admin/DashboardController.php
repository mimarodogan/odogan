<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

final class DashboardController
{
    public function index(Request $req): Response
    {
        $db = Database::instance();
        $count = static function (string $sql, array $params = []) use ($db): int {
            try { return (int) $db->fetchColumn($sql, $params); }
            catch (\Throwable) { return 0; }
        };
        $fetch = static function (string $sql, array $params = []) use ($db): array {
            try { return $db->fetchAll($sql, $params); }
            catch (\Throwable) { return []; }
        };

        // Period filter — son N gün (7 / 30 / 90 / all)
        $allowedPeriods = [7, 30, 90, 0];
        $period = (int) $req->input('period', 7);
        if (!in_array($period, $allowedPeriods, true)) {
            $period = 7;
        }

        $stats = [
            'posts_total'      => $count("SELECT COUNT(*) FROM posts"),
            'posts_published'  => $count("SELECT COUNT(*) FROM posts WHERE status='published'"),
            'posts_pending'    => $count("SELECT COUNT(*) FROM posts WHERE status='pending'"),
            'posts_draft'      => $count("SELECT COUNT(*) FROM posts WHERE status='draft'"),
            'posts_scheduled'  => $count("SELECT COUNT(*) FROM posts WHERE status='scheduled'"),
            'users_total'      => $count("SELECT COUNT(*) FROM users"),
            'users_admin'      => $count("SELECT COUNT(*) FROM users WHERE role='admin'"),
            'users_editor'     => $count("SELECT COUNT(*) FROM users WHERE role='editor'"),
            'users_author'     => $count("SELECT COUNT(*) FROM users WHERE role='author'"),
            'users_member'     => $count("SELECT COUNT(*) FROM users WHERE role='member'"),
            'comments_total'   => $count("SELECT COUNT(*) FROM comments"),
            'comments_pending' => $count("SELECT COUNT(*) FROM comments WHERE status='pending'"),
            'categories_total' => $count("SELECT COUNT(*) FROM categories"),
        ];

        // Genişletilmiş dashboard widget'ları — feature flag korumalı.
        $widgets = null;
        if (function_exists('feature') && feature('dashboard_widgets')) {
            $widgets = self::buildWidgets($db, $period, $count, $fetch);
        }

        $recentPosts = $fetch(
            "SELECT p.id, p.title, p.slug, p.status, p.published_at, p.created_at,
                    u.name AS author_name, u.slug AS author_slug,
                    c.name AS category_name, c.slug AS category_slug
             FROM posts p
             LEFT JOIN users u ON u.id = p.user_id
             LEFT JOIN categories c ON c.id = p.category_id
             ORDER BY p.created_at DESC
             LIMIT 10"
        );

        $recentComments = $fetch(
            "SELECT cm.id, cm.body, cm.status, cm.author_name, cm.created_at,
                    u.name AS user_name,
                    p.title AS post_title, p.slug AS post_slug,
                    cat.slug AS category_slug
             FROM comments cm
             LEFT JOIN users u ON u.id = cm.user_id
             LEFT JOIN posts p ON p.id = cm.post_id
             LEFT JOIN categories cat ON cat.id = p.category_id
             ORDER BY cm.created_at DESC
             LIMIT 10"
        );

        $recentUsers = $fetch(
            "SELECT id, name, slug, email, role, created_at
             FROM users
             ORDER BY created_at DESC
             LIMIT 10"
        );

        return view('admin.dashboard', [
            'title'          => 'Yönetim Paneli',
            'user'           => AuthService::user(),
            'stats'          => $stats,
            'recentPosts'    => $recentPosts,
            'recentComments' => $recentComments,
            'recentUsers'    => $recentUsers,
            'period'         => $period,
            'widgets'        => $widgets,
        ]);
    }

    /**
     * Genişletilmiş widget'lar (feature: dashboard_widgets).
     *  - period bazlı delta metrikler (posts/comments/subscribers)
     *  - sparkline (son 7-30 gün, günlük serisi)
     *  - en aktif yazarlar (post sayısı + view toplamı)
     *  - en yorum alan yazılar (period kapsamında)
     */
    private static function buildWidgets(\App\Core\Database $db, int $period, \Closure $count, \Closure $fetch): array
    {
        // SQL parameter — period=0 ise "all time" → tüm tarihlerdeki kayıtlar.
        $where = $period > 0 ? "DATE_SUB(NOW(), INTERVAL :p DAY)" : "DATE_SUB(NOW(), INTERVAL 9999 DAY)";
        $param = $period > 0 ? [':p' => $period] : [':p' => 9999];

        $widgets = [
            'period_label'      => self::periodLabel($period),
            'posts_recent'      => $count("SELECT COUNT(*) FROM posts WHERE created_at >= $where", $param),
            'posts_published_recent' => $count(
                "SELECT COUNT(*) FROM posts WHERE status='published' AND published_at >= $where", $param
            ),
            'comments_recent'   => $count("SELECT COUNT(*) FROM comments WHERE created_at >= $where", $param),
            'comments_pending'  => $count("SELECT COUNT(*) FROM comments WHERE status='pending'"),
            'users_recent'      => $count("SELECT COUNT(*) FROM users WHERE created_at >= $where", $param),
        ];

        // Subscribers — tablo varsa
        $hasSubs = $count(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'subscribers'"
        );
        if ($hasSubs > 0) {
            $widgets['subs_total'] = $count("SELECT COUNT(*) FROM subscribers WHERE confirmed_at IS NOT NULL");
            $widgets['subs_recent'] = $count(
                "SELECT COUNT(*) FROM subscribers WHERE confirmed_at >= $where", $param
            );
        }

        // Pending author applications (Sprint E hazır olunca)
        $hasAuthorApp = $count(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'author_application_status'"
        );
        if ($hasAuthorApp > 0) {
            $widgets['pending_authors'] = $count(
                "SELECT COUNT(*) FROM users WHERE author_application_status = 'pending'"
            );
        }

        // En aktif yazarlar — period içinde post yayınlayanlar (view toplamı ile)
        $widgets['top_authors'] = $fetch(
            "SELECT u.id, u.name, u.slug,
                    COUNT(p.id) AS post_count,
                    COALESCE(SUM(p.view_count), 0) AS view_total
             FROM users u
             INNER JOIN posts p ON p.user_id = u.id AND p.status='published' AND p.published_at >= $where
             GROUP BY u.id
             ORDER BY post_count DESC, view_total DESC
             LIMIT 5",
            $param
        );

        // En yorum alan yazılar — period içinde
        $widgets['top_commented'] = $fetch(
            "SELECT p.id, p.title, p.slug, p.comment_count,
                    c.slug AS category_slug
             FROM posts p
             INNER JOIN categories c ON c.id = p.category_id
             WHERE p.status='published' AND p.published_at >= $where
             ORDER BY p.comment_count DESC, p.view_count DESC
             LIMIT 5",
            $param
        );

        // Daily sparkline serileri — son `min(period,30)` gün
        $sparkDays = $period > 0 ? min($period, 30) : 14;
        $widgets['spark_posts'] = self::dailySeries($db, 'posts', 'created_at', $sparkDays);
        $widgets['spark_comments'] = self::dailySeries($db, 'comments', 'created_at', $sparkDays);

        return $widgets;
    }

    /**
     * Belirli tablo + tarih kolonu için son N günün günlük sayısını döndür.
     * Eksik günler 0 ile doldurulur (frontend sparkline için sürekli seri).
     *
     * @return array<int,array{date:string,count:int}>
     */
    private static function dailySeries(\App\Core\Database $db, string $table, string $dateCol, int $days): array
    {
        $days = max(1, min(90, $days));
        try {
            $rows = $db->fetchAll(
                "SELECT DATE($dateCol) AS d, COUNT(*) AS c
                 FROM $table
                 WHERE $dateCol >= DATE_SUB(CURDATE(), INTERVAL " . ($days - 1) . " DAY)
                 GROUP BY DATE($dateCol)
                 ORDER BY d ASC"
            );
        } catch (\Throwable) {
            return [];
        }
        $map = [];
        foreach ($rows as $r) $map[$r['d']] = (int) $r['c'];

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i day"));
            $series[] = ['date' => $d, 'count' => $map[$d] ?? 0];
        }
        return $series;
    }

    private static function periodLabel(int $period): string
    {
        switch ($period) {
            case 7: return 'Son 7 gün';
            case 30: return 'Son 30 gün';
            case 90: return 'Son 90 gün';
            default: return 'Tüm zamanlar';
        }
    }
}
