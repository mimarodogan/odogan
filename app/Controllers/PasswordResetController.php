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
use App\Services\RateLimiter;

/**
 * Şifremi Unuttum akışı (Y1).
 *
 *  • GET  /sifremi-unuttum            → form
 *  • POST /sifremi-unuttum            → token üret, mail at, rate-limit (3/saat per IP+email)
 *  • GET  /sifre-sifirla/{token}      → yeni şifre formu
 *  • POST /sifre-sifirla/{token}      → şifre update, oturumları kapat
 *
 * Davranış: bilinen/bilinmeyen e-posta cevabı AYNI — enum koruması.
 * Token: 32-byte hex (64 char), 60 dakika geçerli, tek seferlik (used_at).
 */
final class PasswordResetController
{
    private const TOKEN_TTL_MIN = 60;

    public function showRequest(Request $req): Response
    {
        return view('auth.forgot-password', [
            'title'  => 'Şifremi Unuttum',
            'robots' => 'noindex, nofollow',
        ]);
    }

    public function submitRequest(Request $req): Response
    {
        $email = mb_strtolower(trim((string) $req->input('email', '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error_email', 'Geçerli bir e-posta girin.');
            return Response::redirect(url('/sifremi-unuttum'));
        }

        $ip = RateLimiter::clientIp();
        // 3 talep / saat per IP + per email — credential spray engeli.
        $rlIp = RateLimiter::hit('pwreset:ip:' . $ip, 3, 3600);
        $rlEm = RateLimiter::hit('pwreset:email:' . $email, 3, 3600);
        if (!$rlIp['ok'] || !$rlEm['ok']) {
            // Yine de generic mesaj — enum sızdırmamak için aynı flash.
            flash('success', 'Eğer bu e-posta kayıtlıysa şifre sıfırlama bağlantısı gönderildi.');
            return Response::redirect(url('/sifremi-unuttum/gonderildi'));
        }

        $user = User::findByEmail($email);
        if ($user !== null && empty($user['deleted_at'])) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + self::TOKEN_TTL_MIN * 60);

            try {
                // Eski kullanılmamış tokenları invalidasyon — bir kullanıcı için
                // her seferinde tek geçerli link.
                Database::instance()->run(
                    'UPDATE password_resets SET used_at = NOW()
                     WHERE user_id = :uid AND used_at IS NULL',
                    [':uid' => (int) $user['id']]
                );
                Database::instance()->insert('password_resets', [
                    'user_id'    => (int) $user['id'],
                    'token'      => $token,
                    'expires_at' => $expires,
                    'ip'         => mb_substr($ip, 0, 45),
                    'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                ]);
                $resetLink = url('/sifre-sifirla/' . $token);

                $sent = MailService::sendTemplate('password_reset', (string) $user['email'], [
                    'user_name'  => (string) $user['name'],
                    'reset_link' => $resetLink,
                    'ip_address' => $ip,
                ]);
                if (!($sent['ok'] ?? false)) {
                    // Şablon yoksa fallback hardcoded mail
                    MailService::send(
                        (string) $user['email'],
                        'Şifre sıfırlama bağlantınız',
                        '<p>Merhaba ' . esc((string) $user['name']) . ',</p>'
                        . '<p>Aşağıdaki bağlantıya ' . self::TOKEN_TTL_MIN
                        . ' dakika içinde tıklayarak yeni bir şifre belirleyebilirsiniz:</p>'
                        . '<p><a href="' . esc($resetLink) . '">' . esc($resetLink) . '</a></p>'
                        . '<p>Bu talebi siz yapmadıysanız bu e-postayı yok sayabilirsiniz.</p>'
                    );
                }
                Logger::info('password_reset.sent', ['user_id' => (int) $user['id']], 'auth');
            } catch (\Throwable $e) {
                Logger::warning('password_reset.send_failed', ['err' => $e->getMessage()], 'auth');
                // 050_password_reset migration uygulanmadıysa burada düşeriz.
                // Yine de enum sızdırmamak için generic mesajla dön.
            }
        } else {
            // E-posta yok ya da soft-delete. Generic loglama (sayım için).
            Logger::info('password_reset.unknown_email', ['ip' => $ip], 'auth');
        }

        return Response::redirect(url('/sifremi-unuttum/gonderildi'));
    }

    public function sentNotice(Request $req): Response
    {
        return view('auth.password-reset-sent', [
            'title'  => 'Bağlantı Gönderildi',
            'robots' => 'noindex, nofollow',
        ]);
    }

    public function showReset(Request $req, array $args): Response
    {
        $token = (string) ($args['token'] ?? '');
        $row = $this->findValidToken($token);
        if ($row === null) {
            flash('error', 'Şifre sıfırlama bağlantısı geçersiz veya süresi dolmuş.');
            return Response::redirect(url('/sifremi-unuttum'));
        }
        return view('auth.reset-password', [
            'title'  => 'Yeni Şifre Belirle',
            'token'  => $token,
            'robots' => 'noindex, nofollow',
        ]);
    }

    public function submitReset(Request $req, array $args): Response
    {
        $token = (string) ($args['token'] ?? '');
        $row = $this->findValidToken($token);
        if ($row === null) {
            flash('error', 'Şifre sıfırlama bağlantısı geçersiz veya süresi dolmuş.');
            return Response::redirect(url('/sifremi-unuttum'));
        }

        $userId = (int) $row['user_id'];
        $newPwd  = (string) $req->input('new_password', '');
        $confirm = (string) $req->input('new_password_confirm', '');
        if (mb_strlen($newPwd) < 8) {
            flash('error_new_password', 'Şifre en az 8 karakter olmalı.');
            return Response::redirect(url('/sifre-sifirla/' . $token));
        }
        if ($newPwd !== $confirm) {
            flash('error_new_password_confirm', 'Şifreler eşleşmiyor.');
            return Response::redirect(url('/sifre-sifirla/' . $token));
        }

        $user = User::findById($userId);
        if ($user === null) {
            flash('error', 'Hesap bulunamadı.');
            return Response::redirect(url('/sifremi-unuttum'));
        }

        try {
            Database::instance()->transaction(function (Database $db) use ($newPwd, $userId, $row) {
                User::update($userId, [
                    'password_hash'      => AuthService::hash($newPwd),
                    'failed_login_count' => 0,
                    'locked_until'       => null,
                ]);
                $db->update('password_resets', ['used_at' => date('Y-m-d H:i:s')],
                    'id = :wid', [':wid' => (int) $row['id']]);
            });
        } catch (\Throwable $e) {
            Logger::warning('password_reset.apply_failed', ['err' => $e->getMessage()], 'auth');
            flash('error', 'Şifre güncellenemedi. Lütfen tekrar deneyin.');
            return Response::redirect(url('/sifre-sifirla/' . $token));
        }

        // Tüm aktif oturumları kapat — saldırgan oturumu varsa tahliye.
        try {
            UserSession::deleteAllForUser($userId);
        } catch (\Throwable) {}

        // Bilgilendirme maili — `password_changed` şablonu reset için de mantıklı
        try {
            MailService::sendTemplate('password_changed', (string) $user['email'], [
                'user_name'  => (string) $user['name'],
                'ip_address' => RateLimiter::clientIp(),
            ]);
        } catch (\Throwable) {}

        Logger::info('password_reset.applied', ['user_id' => $userId], 'auth');
        flash('success', 'Şifreniz güncellendi. Lütfen yeni şifrenizle giriş yapın.');
        return Response::redirect(url('/giris'));
    }

    private function findValidToken(string $token): ?array
    {
        if ($token === '' || strlen($token) !== 64) {
            return null;
        }
        try {
            return Database::instance()->fetch(
                'SELECT * FROM password_resets
                 WHERE token = :t
                   AND used_at IS NULL
                   AND expires_at > NOW()
                 LIMIT 1',
                [':t' => $token]
            );
        } catch (\Throwable) {
            // password_resets tablosu yoksa migration 050 henüz uygulanmamış.
            return null;
        }
    }
}
