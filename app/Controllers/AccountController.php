<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuthService;

/**
 * Account Controller (Tier 7 — Privacy & Güvenlik).
 *
 * /panel/hesap/verilerim — JSON export (KVKK)
 * /panel/hesap/sil      — soft delete + 30 gün geri al
 */
final class AccountController
{
    /**
     * GET /panel/hesap/verilerim → JSON download
     * Tüm user verisini içerir: profil, yazılar, yorumlar, bookmark, follow, vb.
     */
    public function exportData(Request $req): Response
    {
        if (!function_exists('feature') || !feature('data_export_enabled')) {
            return Response::notFound();
        }
        $user = AuthService::user();
        if (!$user) {
            return Response::redirect(url('/giris'));
        }
        $uid = (int) $user['id'];
        $db = Database::instance();

        $payload = [
            'export_at' => date('c'),
            'user' => [
                'id'         => $uid,
                'name'       => $user['name'],
                'email'      => $user['email'],
                'slug'       => $user['slug'] ?? '',
                'role'       => $user['role'],
                'created_at' => $user['created_at'] ?? null,
                'profile'    => json_decode((string) ($user['profile_json'] ?? '{}'), true) ?: (object) [],
            ],
            'posts'    => self::safeFetch($db,
                'SELECT id, title, slug, status, published_at, created_at FROM posts WHERE user_id = :uid ORDER BY id DESC',
                [':uid' => $uid]
            ),
            'comments' => self::safeFetch($db,
                'SELECT id, post_id, body, status, created_at FROM comments WHERE user_id = :uid ORDER BY id DESC',
                [':uid' => $uid]
            ),
            'bookmarks' => self::safeFetch($db,
                'SELECT post_id, created_at FROM post_bookmarks WHERE user_id = :uid',
                [':uid' => $uid]
            ),
            'following' => self::safeFetch($db,
                'SELECT author_id, created_at FROM author_follows WHERE follower_id = :uid',
                [':uid' => $uid]
            ),
        ];

        $json = (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $filename = 'odogan-verilerim-' . date('Y-m-d-His') . '.json';

        AuditLog::record('account.data_export', 'user', $uid, 'Veri export');

        return (new Response($json, 200, [
            'Content-Type'        => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'private, no-store',
        ]));
    }

    /**
     * GET /panel/hesap/sil → step 1 confirm sayfası
     */
    public function showDelete(Request $req): Response
    {
        if (!function_exists('feature') || !feature('account_delete_enabled')) {
            return Response::notFound();
        }
        $user = AuthService::user();
        if (!$user) {
            return Response::redirect(url('/giris'));
        }
        // Eğer bekleyen geçerli kod varsa step 2'yi göster
        $pending = $this->findPendingCode((int) $user['id']);
        if ($pending) {
            return view('panel.account-delete-verify', [
                'title' => 'Hesap Silme — Kodu Doğrula',
                'user'  => $user,
                'expires_at' => $pending['expires_at'],
            ]);
        }
        return view('panel.account-delete', [
            'title' => 'Hesabımı Sil',
            'user'  => $user,
        ]);
    }

    /**
     * POST /panel/hesap/sil → step 1: şifre doğrula + 6-haneli kod üret + mail gönder
     */
    public function requestDelete(Request $req): Response
    {
        if (!function_exists('feature') || !feature('account_delete_enabled')) {
            return Response::notFound();
        }
        $user = AuthService::user();
        if (!$user) {
            return Response::redirect(url('/giris'));
        }
        $reason = mb_substr(trim((string) $req->input('reason', '')), 0, 255);
        $password = (string) $req->input('password', '');

        if (!password_verify($password, (string) $user['password_hash'])) {
            flash('error', 'Şifre hatalı.');
            return Response::redirect(url('/panel/hesap/sil'));
        }

        // Daha önce gönderilmiş, hala kullanılmamış kodları geçersiz yap (yeniden istek)
        Database::instance()->run(
            'UPDATE account_deletion_codes SET used_at = NOW() WHERE user_id = :uid AND used_at IS NULL',
            [':uid' => (int) $user['id']]
        );

        // 6-haneli numeric kod üret
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 dakika

        Database::instance()->insert('account_deletion_codes', [
            'user_id'    => (int) $user['id'],
            'code_hash'  => password_hash($code, PASSWORD_DEFAULT),
            'reason'     => $reason ?: null,
            'expires_at' => $expiresAt,
        ]);

        AuditLog::record('account.delete_requested', 'user', (int) $user['id'], 'Doğrulama kodu mail edildi');

        // Mail gönder — varsa template, yoksa fallback
        $this->sendDeletionCodeMail($user, $code, $expiresAt);

        flash('success', 'Doğrulama kodu e-posta adresinize gönderildi. 10 dakika içinde kodu girip son onayı verebilirsin.');
        return Response::redirect(url('/panel/hesap/sil'));
    }

    /**
     * POST /panel/hesap/sil/iptal → bekleyen istekleri iptal et
     */
    public function cancelDelete(Request $req): Response
    {
        $user = AuthService::user();
        if (!$user) return Response::redirect(url('/giris'));
        Database::instance()->run(
            'UPDATE account_deletion_codes SET used_at = NOW() WHERE user_id = :uid AND used_at IS NULL',
            [':uid' => (int) $user['id']]
        );
        AuditLog::record('account.delete_cancelled', 'user', (int) $user['id']);
        flash('success', 'Hesap silme isteği iptal edildi.');
        return Response::redirect(url('/panel/hesap/sil'));
    }

    /**
     * POST /panel/hesap/sil/dogrula → step 2: kod doğrula + soft delete
     */
    public function destroy(Request $req): Response
    {
        if (!function_exists('feature') || !feature('account_delete_enabled')) {
            return Response::notFound();
        }
        $user = AuthService::user();
        if (!$user) {
            return Response::redirect(url('/giris'));
        }
        $confirm = (string) $req->input('confirm_text', '');
        $code = trim((string) $req->input('code', ''));

        if (mb_strtolower(trim($confirm)) !== 'sil') {
            flash('error', 'Onay için "SİL" yazmalısınız.');
            return Response::redirect(url('/panel/hesap/sil'));
        }
        if ($code === '' || !preg_match('/^\d{6}$/', $code)) {
            flash('error', '6 haneli doğrulama kodunu girmelisiniz.');
            return Response::redirect(url('/panel/hesap/sil'));
        }

        $pending = $this->findPendingCode((int) $user['id']);
        if (!$pending) {
            flash('error', 'Aktif doğrulama kodu yok. Lütfen yeniden istek başlatın.');
            return Response::redirect(url('/panel/hesap/sil'));
        }
        if (!password_verify($code, (string) $pending['code_hash'])) {
            flash('error', 'Doğrulama kodu hatalı.');
            return Response::redirect(url('/panel/hesap/sil'));
        }

        // Kodu kullanılmış işaretle
        Database::instance()->run(
            'UPDATE account_deletion_codes SET used_at = NOW() WHERE id = :id',
            [':id' => (int) $pending['id']]
        );

        // Soft delete — deleted_at set, status=disabled
        $reason = $pending['reason'] ?: 'Reason yok';
        User::update((int) $user['id'], [
            'deleted_at'     => date('Y-m-d H:i:s'),
            'deleted_reason' => $reason,
            'status'         => 'disabled',
        ]);
        AuditLog::record('account.deleted', 'user', (int) $user['id'], $reason);

        // Logout
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION = [];
        session_destroy();

        return view('pages.account-deleted', [
            'title'     => 'Hesabınız Silindi',
            'canonical' => absolute_url('/hesap-silindi'),
            'robots'    => 'noindex, nofollow',
        ]);
    }

    // ─────────────── Yardımcılar ───────────────

    private function findPendingCode(int $userId): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM account_deletion_codes
             WHERE user_id = :uid AND used_at IS NULL AND expires_at > NOW()
             ORDER BY id DESC LIMIT 1',
            [':uid' => $userId]
        );
    }

    private function sendDeletionCodeMail(array $user, string $code, string $expiresAt): void
    {
        try {
            $vars = [
                'user_name' => $user['name'] ?? '',
                'email'     => $user['email'] ?? '',
                'code'      => $code,
                'expires_at'=> $expiresAt,
            ];

            // Önce template'den dene
            $sent = false;
            if (class_exists('\App\Services\MailTemplateService')) {
                try {
                    $sent = (bool) \App\Services\MailTemplateService::sendByKey('account_delete_code', (string) $user['email'], $vars);
                } catch (\Throwable) { /* fallback'e geç */ }
            }

            if (!$sent && class_exists('\App\Services\MailService')) {
                $subject = 'Hesap silme doğrulama kodunuz';
                $body = '<p>Merhaba ' . htmlspecialchars((string) ($user['name'] ?? ''), ENT_QUOTES, 'UTF-8') . ',</p>'
                      . '<p>Hesabınızı silmek için aşağıdaki <strong>6 haneli doğrulama kodunu</strong> kullanın:</p>'
                      . '<p style="font-family:monospace;font-size:1.8rem;letter-spacing:.3em;padding:1rem;background:#f5f1ea;border-radius:4px;text-align:center;color:#1F3A8A">'
                      . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</p>'
                      . '<p>Bu kod <strong>10 dakika</strong> içinde geçerlidir (' . htmlspecialchars($expiresAt, ENT_QUOTES, 'UTF-8') . ').</p>'
                      . '<p>Eğer hesap silme talebini siz oluşturmadıysanız, bu e-postayı yok sayın ve şifrenizi değiştirin.</p>';
                \App\Services\MailService::send((string) $user['email'], $subject, $body);
            }
        } catch (\Throwable $e) {
            // Mail göndermek başarısız olursa kullanıcıya görünmesin — ama log'a düşür
            \App\Services\Logger::warning('account.delete_mail_failed', [
                'user' => (int) $user['id'],
                'err'  => $e->getMessage(),
            ], 'account');
        }
    }

    private static function safeFetch(Database $db, string $sql, array $params): array
    {
        try {
            return $db->fetchAll($sql, $params);
        } catch (\Throwable) {
            return [];
        }
    }
}
