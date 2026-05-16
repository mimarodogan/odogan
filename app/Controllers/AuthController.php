<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\AuthMiddleware;
use App\Models\User;
use App\Services\AuthService;
use App\Services\RateLimiter;
use App\Services\TotpService;

final class AuthController
{
    public function showLogin(Request $req): Response
    {
        return view('auth.login', ['title' => 'Giriş Yap', 'robots' => 'noindex, nofollow']);
    }

    public function login(Request $req): Response
    {
        $email = mb_strtolower(trim((string) $req->input('email', '')));
        $password = (string) $req->input('password', '');
        $ip = RateLimiter::clientIp();

        // Two-layer throttle: per-IP and per-email. The per-email bucket
        // stops account-targeted brute force even from rotating IPs.
        // Limit'ler config/security.php'den; fallback hard-coded.
        $rl = (array) Config::get('security.rate_limit', []);
        $ipCfg    = (array) ($rl['login_ip']    ?? ['max' => 10, 'window' => 300]);
        $emailCfg = (array) ($rl['login_email'] ?? ['max' => 5,  'window' => 900]);
        $ipLimit = RateLimiter::hit('login:ip:' . $ip, (int) ($ipCfg['max'] ?? 10), (int) ($ipCfg['window'] ?? 300));
        $idLimit = RateLimiter::hit('login:email:' . $email, (int) ($emailCfg['max'] ?? 5), (int) ($emailCfg['window'] ?? 900));
        if (!$ipLimit['ok'] || !$idLimit['ok']) {
            $retry = max($ipLimit['retry_after'], $idLimit['retry_after']);
            flash('error_email', 'Çok fazla başarısız giriş denemesi. ' . ceil($retry / 60) . ' dakika sonra tekrar deneyin.');
            $_SESSION['_old'] = ['email' => $email];
            return Response::redirect(url('/giris'));
        }

        $result = AuthService::attempt($email, $password);
        if (!$result['ok']) {
            $_SESSION['_old'] = ['email' => $email];
            foreach ($result['errors'] as $k => $v) {
                flash('error_' . $k, $v);
            }
            return Response::redirect(url('/giris'));
        }

        // Success — reset the per-email counter so a flaky-fingered user
        // doesn't get locked out tomorrow.
        RateLimiter::clear('login:email:' . $email);

        // 2FA aktifse: TOTP challenge'a yönlendir (henüz tam login değil)
        if (!empty($result['needs_totp'])) {
            return Response::redirect(url('/giris/dogrulama'));
        }

        $redirect = (string) ($_SESSION['_redirect_after_login'] ?? '/panel');
        unset($_SESSION['_redirect_after_login'], $_SESSION['_old']);
        if (!AuthMiddleware::isSafeInternalPath($redirect)) {
            $redirect = '/panel';
        }
        flash('success', 'Hoş geldiniz!');
        return Response::redirect(url($redirect));
    }

    // ─── 2FA TOTP login flow ─────────────────────────────────────

    public function showTotpChallenge(Request $req): Response
    {
        if (AuthService::pendingTotpUserId() === null) {
            return Response::redirect(url('/giris'));
        }
        return view('auth.totp-challenge', ['title' => 'İki Adımlı Doğrulama', 'robots' => 'noindex, nofollow']);
    }

    public function verifyTotp(Request $req): Response
    {
        $userId = AuthService::pendingTotpUserId();
        if ($userId === null) {
            flash('error_code', 'Oturum süresi doldu. Yeniden giriş yapın.');
            return Response::redirect(url('/giris'));
        }

        // Rate limit: 5 yanlış kod / 15 dk per IP
        $ip = RateLimiter::clientIp();
        $rl = RateLimiter::hit('totp:ip:' . $ip, 5, 900);
        if (!$rl['ok']) {
            AuthService::clearPendingTotp();
            flash('error_email', 'Çok fazla yanlış 2FA denemesi. ' . ceil($rl['retry_after'] / 60) . ' dk sonra tekrar deneyin.');
            return Response::redirect(url('/giris'));
        }

        $code = (string) $req->input('code', '');
        $user = User::findById($userId);
        if (!$user || empty($user['totp_secret'])) {
            AuthService::clearPendingTotp();
            return Response::redirect(url('/giris'));
        }

        // Önce TOTP kodunu dene
        if (TotpService::verify((string) $user['totp_secret'], $code)) {
            AuthService::completeTotpLogin($userId);
            RateLimiter::clear('totp:ip:' . $ip);
            $redirect = (string) ($_SESSION['_redirect_after_login'] ?? '/panel');
            unset($_SESSION['_redirect_after_login'], $_SESSION['_old']);
            if (!AuthMiddleware::isSafeInternalPath($redirect)) {
                $redirect = '/panel';
            }
            flash('success', 'Hoş geldiniz!');
            return Response::redirect(url($redirect));
        }

        // Recovery code denemesi (TOTP fail ettiyse)
        $stored = $user['totp_recovery_codes'] ?? null;
        $recovery = is_string($stored) ? json_decode($stored, true) : (is_array($stored) ? $stored : []);
        if (is_array($recovery) && $recovery) {
            $remaining = TotpService::consumeRecoveryCode($recovery, $code);
            if ($remaining !== null) {
                User::update($userId, [
                    'totp_recovery_codes' => json_encode(array_values($remaining), JSON_UNESCAPED_UNICODE),
                ]);
                AuthService::completeTotpLogin($userId);
                RateLimiter::clear('totp:ip:' . $ip);
                flash('warning', 'Recovery kodu kullanıldı. Kalan kod sayısı: ' . count($remaining) . '. Profilinizden yeni kodlar üretebilirsiniz.');
                return Response::redirect(url('/panel'));
            }
        }

        flash('error_code', 'Kod hatalı. Tekrar deneyin.');
        return Response::redirect(url('/giris/dogrulama'));
    }

    public function showRegister(Request $req): Response
    {
        return view('auth.register', ['title' => 'Kayıt Ol', 'robots' => 'noindex, nofollow']);
    }

    public function register(Request $req): Response
    {
        $ip = RateLimiter::clientIp();
        $rl = (array) Config::get('security.rate_limit', []);
        $regCfg = (array) ($rl['register_ip'] ?? ['max' => 5, 'window' => 3600]);
        $ipLimit = RateLimiter::hit('register:ip:' . $ip, (int) ($regCfg['max'] ?? 5), (int) ($regCfg['window'] ?? 3600));
        if (!$ipLimit['ok']) {
            flash('error_email', 'Çok fazla kayıt denemesi. Lütfen daha sonra tekrar deneyin.');
            return Response::redirect(url('/kayit'));
        }

        $input = [
            'name'             => (string) $req->input('name', ''),
            'email'            => (string) $req->input('email', ''),
            'password'         => (string) $req->input('password', ''),
            'password_confirm' => (string) $req->input('password_confirm', ''),
            // Sözleşme + gizlilik onayı — AuthService kontrol ediyor (LegalDocument
            // 'uyelik-sozlesmesi' kaydı varsa zorunlu). Checkbox value=1 gelir.
            'accept_terms'     => (string) $req->input('accept_terms', ''),
            'accept_privacy'   => (string) $req->input('accept_privacy', ''),
        ];

        $result = AuthService::register($input);
        if (!$result['ok']) {
            $_SESSION['_old'] = ['name' => $input['name'], 'email' => $input['email']];
            foreach ($result['errors'] as $k => $v) {
                flash('error_' . $k, $v);
            }
            return Response::redirect(url('/kayit'));
        }
        unset($_SESSION['_old']);
        // Y8 — Enumeration koruması: e-posta zaten kayıtlıysa AuthService
        // ['duplicate' => true] döner; yeni hesap oluşturulmadı, login etmiyoruz.
        // Mesaj iki durumda da aynı.
        if (!empty($result['duplicate'])) {
            flash('success', 'Hesabınız yoksa doğrulama bağlantısı gönderildi. Lütfen e-postanızı kontrol edin.');
            return Response::redirect(url('/giris'));
        }
        flash('success', 'Hesabınız oluşturuldu. Doğrulama bağlantısı için e-postanızı kontrol edin.');
        return Response::redirect(url('/panel'));
    }

    public function logout(Request $req): Response
    {
        AuthService::logout();
        return Response::redirect(url('/'));
    }
}
