<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Services\AuthService;
use App\Services\Logger;
use App\Services\MediaService;
use App\Services\ProfileService;
use App\Services\RateLimiter;
use App\Services\TotpService;

final class ProfileController
{
    public function dashboard(Request $req): Response
    {
        $user = AuthService::user();
        return view('panel.index', [
            'title' => 'Panel',
            'user' => $user,
        ]);
    }

    public function edit(Request $req): Response
    {
        $user = AuthService::user();
        return view('panel.profile', [
            'title' => 'Profilim',
            'user' => $user,
            'profile' => ProfileService::decode($user['profile_json'] ?? null),
        ]);
    }

    public function update(Request $req): Response
    {
        $user = AuthService::user();
        if ($user === null) {
            return Response::redirect(url('/giris'));
        }

        $name = trim((string) $req->input('name', ''));
        $bio = (string) $req->input('bio', '');

        $rawProfile = (array) ($req->body['profile'] ?? []);
        $rawProfile['bio'] = $bio;

        // CSV → array dönüşümü (JS olmasa da çalışsın diye).
        // profile[expertise_csv] = "mimari, tasarım, BIM" → profile[expertise] = ['mimari','tasarım','BIM']
        $csvKey = 'expertise_csv';
        if (!empty($rawProfile[$csvKey]) && is_string($rawProfile[$csvKey])) {
            $items = array_values(array_filter(array_map(
                'trim',
                explode(',', $rawProfile[$csvKey])
            ), static fn($v) => $v !== ''));
            if ($items) {
                $rawProfile['expertise'] = $items;
            }
            unset($rawProfile[$csvKey]);
        }

        // profiles_text (her satır bir URL) → profiles[] (Person.sameAs).
        // JS olmasa da çalışsın diye sunucu tarafında parse edilir.
        if (isset($rawProfile['profiles_text']) && is_string($rawProfile['profiles_text'])) {
            $rawProfile['profiles'] = array_values(array_filter(array_map(
                'trim',
                preg_split('/\r\n|\r|\n/', $rawProfile['profiles_text']) ?: []
            ), static fn($v) => $v !== ''));
            unset($rawProfile['profiles_text']);
        }

        [$profile, $profileErrors] = ProfileService::validate($rawProfile);

        $errors = $profileErrors;
        if (mb_strlen($name) < 2) {
            $errors['name'] = 'İsim en az 2 karakter olmalı.';
        }

        if ($errors) {
            foreach ($errors as $k => $v) {
                flash('error_' . str_replace('.', '_', $k), $v);
            }
            return Response::redirect(url('/panel/profil'));
        }

        $patch = [
            'name' => $name,
            'bio' => mb_substr($bio, 0, 4000),
            'profile_json' => ProfileService::encode($profile),
        ];

        // Optional avatar upload — same WebP/AVIF pipeline as cover images.
        $avatarFile = $req->files['avatar_file'] ?? null;
        if (is_array($avatarFile) && (int) ($avatarFile['size'] ?? 0) > 0
            && (int) ($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $r = MediaService::uploadFromForm($avatarFile, (int) $user['id'], $name);
            if ($r['ok']) {
                $patch['avatar'] = (string) $r['media']['path'];
            } else {
                flash('error_avatar', $r['error'] ?? 'Avatar yüklenemedi.');
            }
        }

        User::update((int) $user['id'], $patch);
        flash('success', 'Profiliniz güncellendi.');
        return Response::redirect(url('/panel/profil'));
    }

    /**
     * Şifre değiştir — mevcut şifre + yeni şifre × 2.
     * Başarılı olunca onay maili gönderilir.
     */
    public function changePassword(Request $req): Response
    {
        $user = AuthService::user();
        if ($user === null) {
            return Response::redirect(url('/giris'));
        }
        $userId = (int) $user['id'];

        // Rate limit — Y3: 5 şifre değişikliği denemesi / 10 dk per kullanıcı.
        $rl = RateLimiter::hit('pwchange:user:' . $userId, 5, 600);
        if (!$rl['ok']) {
            flash('error_current_password', 'Çok fazla şifre değiştirme denemesi. '
                . ceil($rl['retry_after'] / 60) . ' dakika sonra tekrar deneyin.');
            return Response::redirect(url('/panel/profil') . '#guvenlik');
        }

        $current = (string) $req->input('current_password', '');
        $newPwd  = (string) $req->input('new_password', '');
        $confirm = (string) $req->input('new_password_confirm', '');

        $errors = [];
        // Mevcut şifre doğru mu?
        if (!password_verify($current, (string) ($user['password_hash'] ?? ''))) {
            $errors['current_password'] = 'Mevcut şifre hatalı.';
        }
        if (mb_strlen($newPwd) < 8) {
            $errors['new_password'] = 'Yeni şifre en az 8 karakter olmalı.';
        }
        if ($newPwd !== $confirm) {
            $errors['new_password_confirm'] = 'Yeni şifreler eşleşmiyor.';
        }
        if ($newPwd === $current && empty($errors['new_password'])) {
            $errors['new_password'] = 'Yeni şifre eskiyle aynı olamaz.';
        }
        if ($errors) {
            foreach ($errors as $k => $v) flash('error_' . $k, $v);
            return Response::redirect(url('/panel/profil') . '#guvenlik');
        }

        User::update($userId, [
            // AuthService::hash() Argon2id kullanır — login flow ile tutarlılık
            'password_hash' => \App\Services\AuthService::hash($newPwd),
        ]);
        Logger::info('account.password_changed', ['user_id' => $userId], 'auth');

        // K5 — Şifre değişti: mevcut session HARİÇ tüm aktif oturumları kapat.
        // Saldırgan eski session'ı çalmışsa kullanıcı kendi şifresini
        // değiştirerek erişimi anında keser. session_regenerate_id mevcut
        // cookie ID'yi de yeniler — eski session_id artık kullanılamaz.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $currentSid = (string) session_id();
        try {
            $removed = \App\Models\UserSession::deleteAllForUserExceptCurrent($userId, $currentSid);
            if ($removed > 0) {
                Logger::info('account.sessions_revoked', ['user_id' => $userId, 'count' => $removed], 'auth');
            }
        } catch (\Throwable $e) {
            Logger::warning('account.sessions_revoke_failed', ['user_id' => $userId, 'err' => $e->getMessage()], 'auth');
        }
        // ID rotasyonu — eski session_id'yi kullanmaya çalışan saldırgan kapanır.
        session_regenerate_id(true);
        // Re-track current session under the new id (eski satır pruneStale ile temizlenecek).
        try {
            \App\Models\UserSession::track(
                $userId,
                (string) session_id(),
                (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
            );
        } catch (\Throwable) {}

        // Bilgilendirme maili — şablondan (admin'in düzenleyebileceği)
        try {
            \App\Services\MailService::sendTemplate('password_changed', (string) $user['email'], [
                'user_name'  => (string) $user['name'],
                'ip_address' => (string) $req->ip(),
            ]);
        } catch (\Throwable $e) {
            Logger::warning('account.password_mail_failed', ['user_id' => $userId, 'err' => $e->getMessage()], 'auth');
        }

        flash('success', 'Şifreniz güncellendi. Diğer cihazlardaki oturumlar kapatıldı. Onay e-postası gönderildi.');
        return Response::redirect(url('/panel/profil') . '#guvenlik');
    }

    /**
     * E-posta değiştir — K6 PENDING pattern.
     *
     * Mevcut email kolonuna ANINDA dokunmuyoruz. Yeni adres `email_pending`
     * sütununa yazılır + 48h geçerli token üretilir + yeni adrese onay linki
     * gönderilir + eski adrese bildirim gönderilir. Kullanıcı yeni adresteki
     * linke tıklayana dek mevcut e-postasıyla giriş yapmaya devam eder.
     * Bu davranış "saldırgan oturumu çalıp e-postayı kendine alır" senaryosunu
     * kapatır — eski adres her zaman erken uyarı alır.
     */
    public function changeEmail(Request $req): Response
    {
        $user = AuthService::user();
        if ($user === null) {
            return Response::redirect(url('/giris'));
        }
        $userId = (int) $user['id'];

        // Y3 — Rate limit: 3 değişiklik talebi / saat per kullanıcı.
        $rl = RateLimiter::hit('emailchange:user:' . $userId, 3, 3600);
        if (!$rl['ok']) {
            flash('error_new_email', 'Çok fazla e-posta değişikliği talebi. '
                . ceil($rl['retry_after'] / 60) . ' dk sonra tekrar deneyin.');
            return Response::redirect(url('/panel/profil') . '#guvenlik');
        }

        $newEmail = mb_strtolower(trim((string) $req->input('new_email', '')));
        $currentPwd = (string) $req->input('current_password', '');

        $errors = [];
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['new_email'] = 'Geçerli bir e-posta girin.';
        } elseif ($newEmail === mb_strtolower((string) $user['email'])) {
            $errors['new_email'] = 'Yeni e-posta mevcut adresle aynı.';
        } elseif (User::emailExists($newEmail)) {
            // Enum koruması açısından genel mesaj — yine de UX için açık tutuyoruz
            // çünkü kullanıcı kendi başka hesabını farkındadır.
            $errors['new_email'] = 'Bu e-posta başka bir hesapta kayıtlı.';
        }
        if (!password_verify($currentPwd, (string) ($user['password_hash'] ?? ''))) {
            $errors['current_password_email'] = 'Mevcut şifre hatalı.';
        }
        if ($errors) {
            foreach ($errors as $k => $v) flash('error_' . $k, $v);
            return Response::redirect(url('/panel/profil') . '#guvenlik');
        }

        // Pending kayıt — email kolonu ASLA bu adımda değişmez.
        $token = bin2hex(random_bytes(24));
        $expires = date('Y-m-d H:i:s', time() + 48 * 3600);
        try {
            User::update($userId, [
                'email_pending'            => $newEmail,
                'email_pending_token'      => $token,
                'email_pending_expires_at' => $expires,
            ]);
        } catch (\Throwable $e) {
            // Migration henüz çalıştırılmadıysa açıkça hata göster — eski email'i
            // YANLIŞLIKLA değiştirmemek için fallback yok.
            Logger::warning('account.email_pending_failed', ['user_id' => $userId, 'err' => $e->getMessage()], 'auth');
            flash('error_new_email', 'E-posta değişikliği şu an mümkün değil. /admin/bakim/migrasyonlar üzerinden 049_email_pending uygulanmamış olabilir.');
            return Response::redirect(url('/panel/profil') . '#guvenlik');
        }

        // Yeni adrese onay linki — `/eposta/onayla/{token}`
        $confirmLink = url('/eposta/onayla/' . $token);
        try {
            $sent = \App\Services\MailService::sendTemplate('email_change_confirm', $newEmail, [
                'user_name'    => (string) $user['name'],
                'new_email'    => $newEmail,
                'confirm_link' => $confirmLink,
            ]);
            if (!($sent['ok'] ?? false)) {
                // Şablon yoksa fallback hardcoded mail
                \App\Services\MailService::send(
                    $newEmail,
                    'Yeni e-posta adresinizi onaylayın',
                    '<p>Merhaba ' . esc((string) $user['name']) . ',</p>'
                    . '<p>Yeni e-posta adresinizi onaylamak için aşağıdaki bağlantıya 48 saat içinde tıklayın:</p>'
                    . '<p><a href="' . esc($confirmLink) . '">' . esc($confirmLink) . '</a></p>'
                );
            }
        } catch (\Throwable $e) {
            Logger::warning('account.email_confirm_send_failed', ['user_id' => $userId, 'err' => $e->getMessage()], 'auth');
        }

        // Eski adrese uyarı — saldırganın talebi kullanıcıdan saklamasını engeller.
        try {
            \App\Services\MailService::sendTemplate('email_change_request_old', (string) $user['email'], [
                'user_name'  => (string) $user['name'],
                'new_email'  => $newEmail,
                'ip_address' => (string) $req->ip(),
            ]);
        } catch (\Throwable $e) {
            Logger::warning('account.email_old_notify_failed', ['user_id' => $userId, 'err' => $e->getMessage()], 'auth');
        }

        Logger::info('account.email_change_requested', ['user_id' => $userId, 'pending' => $newEmail], 'auth');
        flash('success', 'Yeni e-posta adresinize onay bağlantısı gönderildi. Bağlantıya tıklayana kadar mevcut e-postanız aktif kalır.');
        return Response::redirect(url('/panel/profil') . '#guvenlik');
    }

    // ─── 2FA TOTP setup ───────────────────────────────────────────

    /**
     * 2FA durumu + setup başlatma sayfası.
     * Aktif ise: aç/kapat + recovery code'lar (gizli).
     * Aktif değil ise: "Etkinleştir" butonu.
     */
    public function show2fa(Request $req): Response
    {
        $user = AuthService::user();
        if ($user === null) {
            return Response::redirect(url('/giris'));
        }
        $pending = $_SESSION['_totp_setup'] ?? null;

        return view('panel.2fa', [
            'title' => 'İki Adımlı Doğrulama',
            'user' => $user,
            'enabled' => ((int) ($user['totp_enabled'] ?? 0)) === 1,
            'pending_secret' => is_array($pending) ? (string) ($pending['secret'] ?? '') : '',
            'pending_codes' => is_array($pending) ? (array) ($pending['recovery'] ?? []) : [],
            'pending_otpauth' => is_array($pending) ? (string) ($pending['otpauth'] ?? '') : '',
        ]);
    }

    /**
     * 2FA setup başlat — secret + recovery codes üret, session'da tut, kullanıcıya göster.
     * Kullanıcı kodla doğrulayana kadar DB'ye yazılmaz.
     */
    public function start2fa(Request $req): Response
    {
        $user = AuthService::user();
        if ($user === null) {
            return Response::redirect(url('/giris'));
        }
        if (((int) ($user['totp_enabled'] ?? 0)) === 1) {
            flash('warning', '2FA zaten aktif. Önce devre dışı bırakın.');
            return Response::redirect(url('/panel/iki-fa'));
        }

        $secret = TotpService::generateSecret();
        $recovery = TotpService::generateRecoveryCodes(10);
        $issuer = (string) \App\Models\Setting::get('site_name', \App\Core\Config::get('APP_NAME', 'Otorite Yayin'));
        $accountName = (string) $user['email'];
        $otpauth = TotpService::otpauthUrl($secret, $accountName, $issuer);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['_totp_setup'] = [
            'secret' => $secret,
            'recovery' => $recovery,
            'otpauth' => $otpauth,
            'at' => time(),
        ];

        return Response::redirect(url('/panel/iki-fa'));
    }

    /**
     * Kullanıcı authenticator app'inde gördüğü 6-haneli kodu gönderir.
     * Doğrulanırsa secret + recovery codes DB'ye kaydedilir, 2FA aktive olur.
     */
    public function activate2fa(Request $req): Response
    {
        $user = AuthService::user();
        if ($user === null) {
            return Response::redirect(url('/giris'));
        }
        if (((int) ($user['totp_enabled'] ?? 0)) === 1) {
            flash('warning', '2FA zaten aktif.');
            return Response::redirect(url('/panel/iki-fa'));
        }

        $pending = $_SESSION['_totp_setup'] ?? null;
        if (!is_array($pending) || empty($pending['secret'])) {
            flash('error', 'Setup oturumu bulunamadı. Yeniden başlatın.');
            return Response::redirect(url('/panel/iki-fa'));
        }

        $code = (string) $req->input('code', '');
        if (!TotpService::verify((string) $pending['secret'], $code)) {
            flash('error_code', 'Kod hatalı. Authenticator uygulamanızdaki güncel kodu girdiğinizden emin olun.');
            return Response::redirect(url('/panel/iki-fa'));
        }

        // F1.4 (KRİTİK): TOTP secret + recovery codes plaintext yerine
        // Crypto::encrypt() ile sodium secretbox sarılır. DB sızıntısı
        // durumunda 2FA fiili olarak ayakta kalır. Legacy plaintext kayıtlar
        // için okuma tarafında Crypto::decryptIfEncrypted() kullanılır.
        $recoveryJson = json_encode(array_values((array) $pending['recovery']), JSON_UNESCAPED_UNICODE);
        User::update((int) $user['id'], [
            'totp_secret'         => \App\Services\Crypto::encrypt((string) $pending['secret']),
            'totp_enabled'        => 1,
            'totp_enabled_at'     => date('Y-m-d H:i:s'),
            'totp_recovery_codes' => \App\Services\Crypto::encrypt((string) $recoveryJson),
        ]);
        unset($_SESSION['_totp_setup']);

        Logger::info('2fa.enabled', ['user_id' => $user['id']], 'auth');
        flash('success', 'İki adımlı doğrulama etkinleştirildi. Recovery kodlarınızı güvenli bir yere kaydedin.');
        return Response::redirect(url('/panel/iki-fa'));
    }

    /**
     * 2FA'yı kapat — kullanıcı mevcut TOTP kodunu girmek zorunda
     * (oturum çalıntıysa saldırgan 2FA'yı kapatamasın).
     */
    public function disable2fa(Request $req): Response
    {
        $user = AuthService::user();
        if ($user === null) {
            return Response::redirect(url('/giris'));
        }
        if (((int) ($user['totp_enabled'] ?? 0)) !== 1) {
            flash('warning', '2FA zaten aktif değil.');
            return Response::redirect(url('/panel/iki-fa'));
        }

        $code = (string) $req->input('code', '');
        // F1.4: secret encrypted veya legacy plaintext olabilir — decryptIfEncrypted güvenle açar.
        $secret = \App\Services\Crypto::decryptIfEncrypted((string) ($user['totp_secret'] ?? ''));
        if (!TotpService::verify($secret, $code)) {
            flash('error_code', 'Kod hatalı. 2FA kapatılamadı.');
            return Response::redirect(url('/panel/iki-fa'));
        }

        User::update((int) $user['id'], [
            'totp_secret' => null,
            'totp_enabled' => 0,
            'totp_enabled_at' => null,
            'totp_recovery_codes' => null,
        ]);

        Logger::warning('2fa.disabled', ['user_id' => $user['id']], 'auth');
        flash('success', 'İki adımlı doğrulama devre dışı bırakıldı.');
        return Response::redirect(url('/panel/iki-fa'));
    }

    /**
     * Recovery codes'u sıfırla — eski liste yerine yenisi üretilir.
     */
    public function regenerateRecoveryCodes(Request $req): Response
    {
        $user = AuthService::user();
        if ($user === null) {
            return Response::redirect(url('/giris'));
        }
        if (((int) ($user['totp_enabled'] ?? 0)) !== 1) {
            flash('warning', '2FA aktif değil.');
            return Response::redirect(url('/panel/iki-fa'));
        }

        $code = (string) $req->input('code', '');
        // F1.4: secret encrypted veya legacy plaintext olabilir.
        $secret = \App\Services\Crypto::decryptIfEncrypted((string) ($user['totp_secret'] ?? ''));
        if (!TotpService::verify($secret, $code)) {
            flash('error_code', 'Kod hatalı.');
            return Response::redirect(url('/panel/iki-fa'));
        }

        $new = TotpService::generateRecoveryCodes(10);
        $recoveryJson = json_encode(array_values($new), JSON_UNESCAPED_UNICODE);
        User::update((int) $user['id'], [
            'totp_recovery_codes' => \App\Services\Crypto::encrypt((string) $recoveryJson),
        ]);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['_totp_recovery_show'] = $new; // tek seferlik gösterim
        Logger::info('2fa.recovery_regenerated', ['user_id' => $user['id']], 'auth');
        flash('success', 'Yeni recovery kodları üretildi. Bir yere kaydedin — bir daha gösterilmeyecek.');
        return Response::redirect(url('/panel/iki-fa'));
    }
}
