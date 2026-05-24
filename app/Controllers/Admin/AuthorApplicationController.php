<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Services\AuthService;
use App\Services\Logger;
use App\Services\PostNotifier;

/**
 * Admin → Yazar Başvuruları (Tier 5 feature 5.1).
 *
 * Listele / detay / onayla / reddet. Onaylama → kullanıcının role'ünü AUTHOR'a çevirir
 * ve onay mailı atar. Reddetme → status=rejected + ret nedeniyle mail.
 *
 * Feature flag: author_application (default false) → off ise 404.
 */
final class AuthorApplicationController
{
    private static function gate(): ?Response
    {
        if (!function_exists('feature') || !feature('author_application')) {
            return Response::notFound();
        }
        return null;
    }

    public function index(Request $req): Response
    {
        if ($g = self::gate()) return $g;
        $status = (string) $req->input('status', 'pending');
        $allowedStatus = ['pending', 'approved', 'rejected', 'all'];
        if (!in_array($status, $allowedStatus, true)) {
            $status = 'pending';
        }

        $sql = 'SELECT id, name, slug, email, role, author_application_status AS app_status,
                       author_application_at AS app_at
                FROM users
                WHERE author_application_status != "none"';
        $params = [];
        if ($status !== 'all') {
            $sql .= ' AND author_application_status = :st';
            $params[':st'] = $status;
        }
        $sql .= ' ORDER BY author_application_at DESC LIMIT 200';

        $list = [];
        try {
            $list = Database::instance()->fetchAll($sql, $params);
        } catch (\Throwable $e) {
            // Tablo migration uygulanmadıysa boş döndür, error flash
            flash('error', 'Yazar başvuru tablosu hazır değil. Migration 025\'i uygulayın.');
        }

        $counts = self::statusCounts();

        return view('admin.author-applications.index', [
            'title'  => 'Yazar Başvuruları',
            'list'   => $list,
            'status' => $status,
            'counts' => $counts,
        ]);
    }

    public function show(Request $req, array $args): Response
    {
        if ($g = self::gate()) return $g;
        $id = (int) ($args['id'] ?? 0);
        $user = User::findById($id);
        if (!$user || (string) ($user['author_application_status'] ?? 'none') === 'none') {
            return Response::notFound();
        }
        $appData = $user['author_application_json']
            ? (array) json_decode((string) $user['author_application_json'], true)
            : [];
        return view('admin.author-applications.show', [
            'title'    => 'Başvuru — ' . ($user['name'] ?? ''),
            'applicant' => $user,
            'data'     => $appData,
        ]);
    }

    public function approve(Request $req, array $args): Response
    {
        if ($g = self::gate()) return $g;
        $id = (int) ($args['id'] ?? 0);
        $user = User::findById($id);
        if (!$user) return Response::notFound();
        if ((string) ($user['author_application_status'] ?? 'none') !== 'pending') {
            flash('error', 'Bu başvuru zaten işlenmiş.');
            return Response::redirect(url('/admin/yazar-basvurulari'));
        }

        Database::instance()->update('users', [
            'role'                      => User::ROLE_AUTHOR,
            'author_application_status' => 'approved',
        ], 'id = :wid', [':wid' => $id]);

        try {
            PostNotifier::notifyAuthorOfApprovalAsWriter(
                (string) $user['email'],
                (string) $user['name']
            );
        } catch (\Throwable $e) {
            Logger::warning('author_app.notify_approve_failed', [
                'user_id' => $id, 'error' => $e->getMessage(),
            ], 'editorial');
        }

        $actor = AuthService::user();
        Logger::info('author_app.approved', [
            'user_id' => $id,
            'by'      => (int) ($actor['id'] ?? 0),
        ], 'editorial');

        flash('success', $user['name'] . ' artık yazar yetkisine sahip.');
        return Response::redirect(url('/admin/yazar-basvurulari'));
    }

    public function reject(Request $req, array $args): Response
    {
        if ($g = self::gate()) return $g;
        $id = (int) ($args['id'] ?? 0);
        $user = User::findById($id);
        if (!$user) return Response::notFound();
        if ((string) ($user['author_application_status'] ?? 'none') !== 'pending') {
            flash('error', 'Bu başvuru zaten işlenmiş.');
            return Response::redirect(url('/admin/yazar-basvurulari'));
        }
        $reason = trim((string) $req->input('reason', ''));

        Database::instance()->update('users', [
            'author_application_status' => 'rejected',
        ], 'id = :wid', [':wid' => $id]);

        try {
            PostNotifier::notifyAuthorOfApplicationRejection(
                (string) $user['email'],
                (string) $user['name'],
                $reason
            );
        } catch (\Throwable $e) {
            Logger::warning('author_app.notify_reject_failed', [
                'user_id' => $id, 'error' => $e->getMessage(),
            ], 'editorial');
        }

        $actor = AuthService::user();
        Logger::info('author_app.rejected', [
            'user_id' => $id, 'by' => (int) ($actor['id'] ?? 0), 'reason_len' => mb_strlen($reason),
        ], 'editorial');

        flash('success', 'Başvuru reddedildi ve bildirim gönderildi.');
        return Response::redirect(url('/admin/yazar-basvurulari'));
    }

    /**
     * Status bazlı sayım — admin sidebar badge için.
     *
     * @return array{pending:int,approved:int,rejected:int,total:int}
     */
    public static function statusCounts(): array
    {
        try {
            $rows = Database::instance()->fetchAll(
                'SELECT author_application_status AS s, COUNT(*) AS c
                 FROM users
                 WHERE author_application_status != "none"
                 GROUP BY author_application_status'
            );
            $out = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'total' => 0];
            foreach ($rows as $r) {
                $key = (string) $r['s'];
                if (isset($out[$key])) {
                    $out[$key] = (int) $r['c'];
                    $out['total'] += (int) $r['c'];
                }
            }
            return $out;
        } catch (\Throwable) {
            return ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'total' => 0];
        }
    }
}
