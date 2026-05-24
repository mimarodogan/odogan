<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\User;

final class AuthService
{
    private const HASH_ALGO = PASSWORD_ARGON2ID;
    private const HASH_OPTS = [
        'memory_cost' => 65536, // 64 MB
        'time_cost'   => 4,
        'threads'     => 2,
    ];

    public static function hash(string $password): string
    {
        return password_hash($password, self::HASH_ALGO, self::HASH_OPTS);
    }

    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, self::HASH_ALGO, self::HASH_OPTS);
    }

    /**
     * @return array{ok:bool, errors?:array<string,string>, user_id?:int, duplicate?:bool}
     */
    public static function register(array $input): array
    {
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $confirm = (string) ($input['password_confirm'] ?? '');

        if ($name === '' || mb_strlen($name) < 2) {
            $errors['name'] = 'İsim en az 2 karakter olmalı.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Geçerli bir e-posta girin.';
        }
        if (mb_strlen($password) < 8) {
            $errors['password'] = 'Parola en az 8 karakter olmalı.';
        } elseif ($password !== $confirm) {
            $errors['password_confirm'] = 'Parolalar eşleşmiyor.';
        }
        // Sözleşme onayı — üyelik sözleşmesi VEYA gizlilik politikası varsa
        // checkbox zorunlu (view tek checkbox altında ikisini birden onaylatıyor).
        // LegalDocument tablosu yoksa hiç kontrol etme.
        $hasAnyLegalDoc = false;
        try {
            $hasAnyLegalDoc = \App\Models\LegalDocument::findBySlug('uyelik-sozlesmesi') !== null
                           || \App\Models\LegalDocument::findBySlug('gizlilik-politikasi') !== null;
        } catch (\Throwable) {}
        if ($hasAnyLegalDoc && empty($input['accept_terms'])) {
            $errors['terms'] = 'Üyelik sözleşmesini ve gizlilik politikasını kabul etmelisiniz.';
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        // Y8 — E-mail enumeration koruması: kullanıcı kayıtlıysa
        // "zaten kayıtlı" demiyoruz. Yeni kullanıcı oluşturmuyoruz, login
        // de etmiyoruz; mevcut hesabın sahibine "biri kayıt denemesi yaptı"
        // bildirimi atıyoruz. Akış başarılıymış gibi 200 OK döner.
        if (User::emailExists($email)) {
            try {
                $existing = User::findByEmail($email);
                if ($existing !== null) {
                    \App\Services\MailService::send(
                        (string) $existing['email'],
                        'Hesabınızla kayıt denemesi',
                        '<p>Merhaba ' . esc((string) $existing['name']) . ',</p>'
                        . '<p>Bu e-posta ile yeni bir kayıt denemesi yapıldı. Eğer bu sizdiniz '
                        . '<a href="' . esc(url('/giris')) . '">giriş yapabilir</a> veya '
                        . '<a href="' . esc(url('/sifremi-unuttum')) . '">şifrenizi sıfırlayabilirsiniz</a>.</p>'
                        . '<p>Siz değilseniz hiçbir işlem yapmanıza gerek yok; hesabınız güvende.</p>'
                    );
                }
            } catch (\Throwable) {
                // Mail başarısız da olsa enumerasyon sızdırma — sessiz kal.
            }
            return ['ok' => true, 'duplicate' => true];
        }

        // Default role — feature: author_application aktifse MEMBER (kullanıcı
        // sonradan /yazar-ol ile yazar olmak için başvurur). Off ise eski davranış:
        // AUTHOR (geriye uyumluluk).
        $defaultRole = (function_exists('feature') && feature('author_application'))
            ? User::ROLE_MEMBER
            : User::ROLE_AUTHOR;

        $token = bin2hex(random_bytes(24));
        // Y2 — Doğrulama linki 72 saat geçerli. expires_at kolonu yoksa
        // (migration 051 henüz uygulanmadıysa) sessizce atla.
        $expires = date('Y-m-d H:i:s', time() + 72 * 3600);

        $payload = [
            'name' => $name,
            'email' => $email,
            'password_hash' => self::hash($password),
            'role' => $defaultRole,
            // Email doğrulama zorunlu — hesap 'pending' başlar; /dogrula/{token}
            // tıklanınca 'active' olur. Doğrulanmadan login/panel erişimi yok.
            // Bu, gerçek gmail/hotmail kullanan botları da durdurur (link tıklanamaz).
            'status' => 'pending',
            'email_verification_token' => $token,
            'profile_json' => json_encode((object) [], JSON_UNESCAPED_UNICODE),
        ];

        try {
            $id = User::create($payload + ['email_verification_expires_at' => $expires]);
        } catch (\Throwable) {
            // Migration 051 henüz çalıştırılmamış — kolonsuz dene.
            $id = User::create($payload);
        }

        self::sendVerificationEmail($email, $name, $token);
        // ÖNEMLİ: login() YAPILMAZ — hesap doğrulanana kadar pending kalır.
        return ['ok' => true, 'user_id' => $id, 'pending_verification' => true];
    }

    public static function sendVerificationEmail(string $email, string $name, string $token): void
    {
        $result = \App\Services\MailService::sendTemplate('verify_email', $email, [
            'user_name'         => $name,
            'verification_link' => url('/dogrula/' . $token),
        ]);
        // Şablon yoksa fallback (eski hardcoded gönderim)
        if (!($result['ok'] ?? false)) {
            $link = url('/dogrula/' . $token);
            $html = sprintf(
                '<p>Merhaba %s,</p><p>Hesabınızı doğrulamak için aşağıdaki bağlantıya tıklayın:</p>'
                . '<p><a href="%s">%s</a></p>'
                . '<p>Eğer kayıt olmadıysanız bu e-postayı yok sayın.</p>',
                esc($name), esc($link), esc($link)
            );
            \App\Services\MailService::send($email, 'E-posta adresinizi doğrulayın', $html);
        }
    }

    public static function regenerateVerifyToken(int $userId): string
    {
        $token = bin2hex(random_bytes(24));
        $expires = date('Y-m-d H:i:s', time() + 72 * 3600);
        try {
            User::update($userId, [
                'email_verification_token' => $token,
                'email_verification_expires_at' => $expires,
            ]);
        } catch (\Throwable) {
            // Migration 051 yoksa expiry kolonsuz geri düş.
            User::update($userId, ['email_verification_token' => $token]);
        }
        return $token;
    }

    /**
     * @return array{ok:bool, errors?:array<string,string>, user_id?:int, needs_totp?:bool}
     */
    public static function attempt(string $email, string $password): array
    {
        $email = mb_strtolower(trim($email));
        $user = User::findByEmail($email);
        if (!$user) {
            return ['ok' => false, 'errors' => ['email' => 'E-posta veya parola hatalı.']];
        }

        // Soft delete kontrolü
        if (!empty($user['deleted_at'])) {
            return ['ok' => false, 'errors' => ['email' => 'Bu hesap silinmiş.']];
        }

        // Login lockout — Tier 7 (feature_flag korumalı)
        $lockoutEnabled = function_exists('feature') && feature('login_lockout_enabled');
        if ($lockoutEnabled && !empty($user['locked_until'])) {
            $lockedUntil = strtotime((string) $user['locked_until']);
            if ($lockedUntil && $lockedUntil > time()) {
                $minutes = max(1, (int) ceil(($lockedUntil - time()) / 60));
                return ['ok' => false, 'errors' => [
                    'email' => 'Çok fazla yanlış deneme. Hesap ' . $minutes . ' dakika kilitli.',
                ]];
            }
            // Süresi dolmuş — sıfırla
            User::update((int) $user['id'], ['locked_until' => null, 'failed_login_count' => 0]);
        }

        if (!self::verify($password, (string) $user['password_hash'])) {
            // Failed attempt + lockout
            if ($lockoutEnabled) {
                $newCount = (int) ($user['failed_login_count'] ?? 0) + 1;
                $patch = ['failed_login_count' => $newCount];
                if ($newCount >= 5) {
                    $patch['locked_until'] = date('Y-m-d H:i:s', time() + 15 * 60);
                    \App\Models\AuditLog::record(
                        'auth.lockout',
                        'user', (int) $user['id'],
                        'Hesap 5 yanlış denemeyle 15dk kilitlendi'
                    );
                }
                User::update((int) $user['id'], $patch);
            }
            return ['ok' => false, 'errors' => ['email' => 'E-posta veya parola hatalı.']];
        }

        $uStatus = $user['status'] ?? 'active';
        if ($uStatus !== 'active') {
            // Pending + e-posta doğrulanmamış → kayıt yapmış ama doğrulamamış.
            // Doğru parolayı bildiği için (buraya ancak parola eşleşince gelinir)
            // ona yardımcı ol: doğrulama bağlantısını yeniden gönder (saatte 3'e
            // kadar) ve net yönlendirme yap. Enumeration riski yok — parola gerekli.
            if ($uStatus === 'pending' && empty($user['email_verified_at'])) {
                $rl = RateLimiter::hit('email_resend:user:' . (int) $user['id'], 3, 3600);
                if ($rl['ok']) {
                    $token = self::regenerateVerifyToken((int) $user['id']);
                    self::sendVerificationEmail((string) $user['email'], (string) $user['name'], $token);
                }
                return ['ok' => false, 'errors' => [
                    'email' => 'Giriş yapabilmek için önce e-posta adresinizi doğrulayın. '
                        . 'Doğrulama bağlantısını e-postanıza (yeniden) gönderdik — gelen kutunuzu ve spam klasörünü kontrol edin.',
                ]];
            }
            return ['ok' => false, 'errors' => ['email' => 'Hesabınız etkin değil.']];
        }
        if (self::needsRehash((string) $user['password_hash'])) {
            User::update((int) $user['id'], ['password_hash' => self::hash($password)]);
        }

        // Başarılı login → counter reset
        User::update((int) $user['id'], [
            'failed_login_count' => 0,
            'locked_until'       => null,
        ]);

        // 2FA (TOTP) aktifse: henüz login etme — pending state'e koy.
        if (((int) ($user['totp_enabled'] ?? 0)) === 1 && !empty($user['totp_secret'])) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['_pending_totp_user_id'] = (int) $user['id'];
            $_SESSION['_pending_totp_at'] = time();
            return ['ok' => true, 'needs_totp' => true, 'user_id' => (int) $user['id']];
        }

        self::login((int) $user['id']);
        User::touchLogin((int) $user['id']);
        User::update((int) $user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);
        return ['ok' => true, 'user_id' => (int) $user['id']];
    }

    /**
     * Login akışı ortasında TOTP challenge bekleyen user var mı?
     * 10 dakikadan eski bekleyen state expire eder.
     */
    public static function pendingTotpUserId(): ?int
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $id = $_SESSION['_pending_totp_user_id'] ?? null;
        $at = (int) ($_SESSION['_pending_totp_at'] ?? 0);
        if (!is_int($id) || $id <= 0 || (time() - $at) > 600) {
            self::clearPendingTotp();
            return null;
        }
        return $id;
    }

    public static function clearPendingTotp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['_pending_totp_user_id'], $_SESSION['_pending_totp_at']);
    }

    /**
     * TOTP doğrulaması başarılı oldu — pending state'i temizleyip login et.
     */
    public static function completeTotpLogin(int $userId): void
    {
        self::clearPendingTotp();
        self::login($userId);
        User::touchLogin($userId);
    }

    public static function login(int $userId): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['_login_at'] = time();

        // Active session tracking (Tier 8)
        if (function_exists('feature') && feature('active_sessions_enabled')) {
            try {
                \App\Models\UserSession::track(
                    $userId,
                    (string) session_id(),
                    (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                    (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
                );
            } catch (\Throwable) {}
        }
    }

    public static function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $p['path'],
                'domain' => $p['domain'],
                'secure' => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }

    public static function id(): ?int
    {
        $id = $_SESSION['user_id'] ?? null;
        return is_int($id) ? $id : null;
    }

    public static function user(): ?array
    {
        $id = self::id();
        return $id === null ? null : User::findById($id);
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    public static function hasRole(string ...$roles): bool
    {
        $u = self::user();
        if ($u === null) {
            return false;
        }
        return in_array($u['role'] ?? '', $roles, true);
    }
}
