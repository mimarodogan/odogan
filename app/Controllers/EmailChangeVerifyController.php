<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Models\UserSession;
use App\Services\AuthService;
use App\Services\Logger;
use App\Services\MailService;

/**
 * E-posta değişikliği onayı — K6 pending pattern.
 *
 * Akış:
 *   1. /panel/profil/eposta → email_pending + token + 48h expiry
 *   2. Yeni adres → /eposta/onayla/{token} bağlantısına tıklar
 *   3. Burada: email kolonunu swap'le, email_pending temizle,
 *      email_verified_at = NOW(), diğer oturumları kapat.
 *
 * Login durumundan bağımsız çalışır — kullanıcı çıkış yapmış olsa bile
 * (örneğin başka cihazda) onay tamamlanır.
 */
final class EmailChangeVerifyController
{
    public function confirm(Request $req, array $args): Response
    {
        $token = (string) ($args['token'] ?? '');
        $row = User::findByEmailPendingToken($token);
        if ($row === null) {
            flash('error', 'E-posta onay bağlantısı geçersiz veya süresi dolmuş.');
            return Response::redirect(url(AuthService::check() ? '/panel/profil' : '/giris'));
        }

        $userId = (int) $row['id'];
        $oldEmail = (string) $row['email'];
        $newEmail = (string) ($row['email_pending'] ?? '');
        if ($newEmail === '') {
            flash('error', 'E-posta onay bağlantısı geçersiz.');
            return Response::redirect(url('/giris'));
        }

        // Yeni email başka hesaba kayıt edilmiş olabilir (talep verildikten sonra).
        // Yarış koşulu — burada da kontrol et.
        if (User::emailExists($newEmail) && mb_strtolower($newEmail) !== mb_strtolower($oldEmail)) {
            // Sahiplik çakışması; pending'i temizle, kullanıcı yeniden talep etsin.
            try {
                Database::instance()->update('users', [
                    'email_pending'            => null,
                    'email_pending_token'      => null,
                    'email_pending_expires_at' => null,
                ], 'id = :wid', [':wid' => $userId]);
            } catch (\Throwable) {}
            flash('error', 'Bu e-posta artık başka bir hesapta kayıtlı. Lütfen farklı bir adresle yeniden talep gönderin.');
            return Response::redirect(url(AuthService::check() ? '/panel/profil' : '/giris'));
        }

        // Atomik swap: email ← pending, pending sütunlarını temizle, verify'i işaretle.
        try {
            Database::instance()->update('users', [
                'email'                        => $newEmail,
                'email_pending'                => null,
                'email_pending_token'          => null,
                'email_pending_expires_at'     => null,
                'email_verified_at'            => date('Y-m-d H:i:s'),
                'email_verification_token'     => null,
            ], 'id = :wid', [':wid' => $userId]);
        } catch (\Throwable $e) {
            Logger::warning('account.email_change_apply_failed', ['user_id' => $userId, 'err' => $e->getMessage()], 'auth');
            flash('error', 'E-posta güncellenirken hata oluştu. Lütfen tekrar deneyin.');
            return Response::redirect(url('/giris'));
        }

        Logger::info('account.email_changed', [
            'user_id' => $userId,
            'old'     => $oldEmail,
            'new'     => $newEmail,
        ], 'auth');

        // Mevcut oturum dışındaki tüm oturumları kapat — saldırgan eski cihazdan
        // oturum sürdürmesin. Onay genellikle yeni cihazda yapılır, kullanıcı
        // login değilse hiçbir oturum etkilenmez; login ise current SID korunur.
        if (session_status() === \PHP_SESSION_NONE) {
            session_start();
        }
        $currentSid = (string) session_id();
        try {
            if (AuthService::id() === $userId && $currentSid !== '') {
                UserSession::deleteAllForUserExceptCurrent($userId, $currentSid);
            } else {
                UserSession::deleteAllForUser($userId);
            }
        } catch (\Throwable) {}

        // Eski adrese bilgilendirme — değişim TAMAMLANDI; saldırı erken
        // tespiti için kanıt niteliğinde.
        try {
            $sent = MailService::sendTemplate('email_changed_old', $oldEmail, [
                'user_name'  => (string) $row['name'],
                'new_email'  => $newEmail,
                'ip_address' => (string) $req->ip(),
            ]);
            if (!($sent['ok'] ?? false)) {
                MailService::send(
                    $oldEmail,
                    'E-posta adresiniz değiştirildi',
                    '<p>Merhaba ' . esc((string) $row['name']) . ',</p>'
                    . '<p>Hesabınızın e-posta adresi <strong>' . esc($newEmail) . '</strong> olarak güncellendi.</p>'
                    . '<p>Bu işlemi siz yapmadıysanız derhal destek ekibine başvurun.</p>'
                );
            }
        } catch (\Throwable $e) {
            Logger::warning('account.email_changed_notify_failed', ['user_id' => $userId, 'err' => $e->getMessage()], 'auth');
        }

        flash('success', 'E-posta adresiniz başarıyla güncellendi: ' . $newEmail);
        return Response::redirect(url(AuthService::check() ? '/panel/profil' : '/giris'));
    }
}
