<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\Logger;

/**
 * KVKK Açık Rıza Kayıt Endpoint'i (F2.1).
 *
 *   POST /api/consent
 *
 * Frontend cookie-consent.js kullanıcı butona bastığında bu endpoint'i
 * çağırır. Sunucu tarafında consent_logs tablosuna kayıt düşer.
 *
 * KVKK m.5 ve GDPR Art. 7(1) gereği rızanın denetlenebilir biçimde
 * saklanması zorunlu — localStorage tek başına yeterli değil.
 *
 * Güvenlik: CSRF middleware global olarak POST'ları doğrular.
 * Rate limit: aynı IP saatte 30 değişiklik (banner spam koruması).
 */
final class ConsentController
{
    /** İzin verilen action değerleri */
    private const ACTIONS = ['accept_all', 'reject_optional', 'prefs_save', 'withdraw'];

    /** Çerez kategorileri */
    private const CATEGORIES = ['essential', 'analytics', 'marketing'];

    public function record(Request $req): Response
    {
        $action = (string) $req->input('action', '');
        if (!in_array($action, self::ACTIONS, true)) {
            return Response::json(['ok' => false, 'error' => 'invalid_action'], 400);
        }

        // Rate limit — IP saatte 30 (banner reklam üzerinden saldırı engelleme)
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        if (!self::checkRate($ip)) {
            return Response::json(['ok' => false, 'error' => 'rate_limited'], 429);
        }

        // Kategori onayları — sadece bilinen anahtarlar, boolean cast
        $raw = (array) ($req->body['categories'] ?? []);
        $categories = ['essential' => true]; // her zaman zorunlu
        foreach (self::CATEGORIES as $cat) {
            if ($cat === 'essential') continue;
            $categories[$cat] = !empty($raw[$cat]);
        }

        // Versiyon — Çerez Politikası / Aydınlatma Metni hangi sürüm onaylandı
        $version = mb_substr(trim((string) $req->input('version', '1.0')), 0, 20);

        // Visitor token (misafir takip) — cookie'den okunur, yoksa üret
        $visitorToken = (string) ($_COOKIE['odogan_vt'] ?? '');
        if ($visitorToken === '' || !preg_match('/^[a-f0-9]{32}$/', $visitorToken)) {
            $visitorToken = bin2hex(random_bytes(16));
            // 2 yıl saklanan anonim takip çerezi (sadece consent denetim için)
            setcookie(
                'odogan_vt',
                $visitorToken,
                [
                    'expires'  => time() + (2 * 365 * 86400),
                    'path'     => '/',
                    'secure'   => true,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]
            );
        }

        $user = AuthService::user();
        $userId = $user ? (int) $user['id'] : null;

        try {
            Database::instance()->insert('consent_logs', [
                'user_id'         => $userId,
                'visitor_token'   => $visitorToken,
                'ip_address'      => self::ipToBinary($ip),
                'user_agent'      => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'action'          => $action,
                'categories_json' => json_encode($categories, JSON_UNESCAPED_UNICODE),
                'policy_version'  => $version,
            ]);
            return Response::json(['ok' => true, 'visitor_token' => $visitorToken]);
        } catch (\Throwable $e) {
            Logger::warning('consent.record.fail', [
                'msg' => $e->getMessage(),
                'action' => $action,
            ], 'kvkk');
            return Response::json(['ok' => false, 'error' => 'server_error'], 500);
        }
    }

    /** IPv4/IPv6 → binary (16 byte) — VARBINARY kolonuna uygun. */
    private static function ipToBinary(string $ip): ?string
    {
        $packed = @inet_pton($ip);
        return $packed === false ? null : $packed;
    }

    /** Settings tablosunu basit sayaç olarak kullanan rate limit. */
    private static function checkRate(string $ip): bool
    {
        $key = 'consent_rate_' . md5($ip) . '_' . date('YmdH');
        try {
            $current = (int) \App\Models\Setting::get($key, 0, '__consent_rate');
            if ($current >= 30) {
                return false;
            }
            $db = Database::instance();
            $db->run(
                'INSERT INTO settings (group_name, key_name, value, value_type) '
                . 'VALUES (:g, :k, :v, "string") '
                . 'ON DUPLICATE KEY UPDATE value = :v2',
                [':g' => '__consent_rate', ':k' => $key, ':v' => (string) ($current + 1), ':v2' => (string) ($current + 1)]
            );
            return true;
        } catch (\Throwable) {
            return true; // hata olursa engelleme
        }
    }
}
