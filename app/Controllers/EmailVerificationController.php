<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Services\AuthService;
use App\Services\Logger;
use App\Services\RateLimiter;

final class EmailVerificationController
{
    public function verify(Request $req, array $args): Response
    {
        $token = (string) ($args['token'] ?? '');
        $user = User::findByVerifyToken($token);
        if ($user === null) {
            flash('error', 'Doğrulama bağlantısı geçersiz veya süresi dolmuş.');
            return Response::redirect(url('/giris'));
        }

        // Y2 — Expiry kontrolü. Kolon yoksa (migration 051 uygulanmamış)
        // veya NULL ise eski davranış: süresiz token.
        $expiresAt = $user['email_verification_expires_at'] ?? null;
        if ($expiresAt !== null && $expiresAt !== '') {
            $ts = strtotime((string) $expiresAt);
            if ($ts !== false && $ts < time()) {
                // Süresi dolmuş — temizleme YAPMA (kullanıcı resend ile yenisini istesin).
                if (AuthService::check() && AuthService::id() === (int) $user['id']) {
                    flash('error', 'Doğrulama bağlantısının süresi doldu. Aşağıdaki butondan yeni link talep edebilirsiniz.');
                    return Response::redirect(url('/panel'));
                }
                flash('error', 'Doğrulama bağlantısının süresi doldu. Lütfen giriş yapıp yeni bir link talep edin.');
                return Response::redirect(url('/giris'));
            }
        }

        // Hem token hem de expiry kolonunu temizle.
        $patch = [
            'email_verified_at'        => date('Y-m-d H:i:s'),
            'email_verification_token' => null,
        ];
        try {
            Database::instance()->update('users', $patch + ['email_verification_expires_at' => null],
                'id = :wid', [':wid' => (int) $user['id']]);
        } catch (\Throwable) {
            // expires_at kolonu yoksa
            Database::instance()->update('users', $patch, 'id = :wid', [':wid' => (int) $user['id']]);
        }
        Logger::info('user.email-verified', ['user_id' => (int) $user['id']], 'auth');
        flash('success', 'E-posta adresiniz başarıyla doğrulandı.');
        return Response::redirect(url(AuthService::check() ? '/panel' : '/giris'));
    }

    public function resend(Request $req): Response
    {
        $user = AuthService::user();
        if ($user === null) {
            return Response::redirect(url('/giris'));
        }
        if (!empty($user['email_verified_at'])) {
            flash('success', 'E-postanız zaten doğrulanmış.');
            return Response::redirect(url('/panel'));
        }
        // Y3 — Resend: 3 talep / saat per kullanıcı.
        $rl = RateLimiter::hit('email_resend:user:' . (int) $user['id'], 3, 3600);
        if (!$rl['ok']) {
            flash('error', 'Çok fazla doğrulama maili talebi. '
                . ceil($rl['retry_after'] / 60) . ' dakika sonra tekrar deneyin.');
            return Response::redirect(url('/panel'));
        }

        $token = AuthService::regenerateVerifyToken((int) $user['id']);
        AuthService::sendVerificationEmail((string) $user['email'], (string) $user['name'], $token);
        flash('success', 'Yeni doğrulama e-postası gönderildi (72 saat geçerli).');
        return Response::redirect(url('/panel'));
    }
}
