<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Active Sessions (Tier 8 — Privacy).
 *
 * Kullanıcı login olunca session_id kaydı oluşturulur. Kullanıcı tüm cihazlarını
 * görür ve uzakta çıkış yapabilir.
 */
final class UserSession
{
    public static function track(int $userId, string $sessionId, ?string $ip, ?string $ua): void
    {
        try {
            $db = Database::instance();
            $existing = $db->fetch(
                'SELECT id FROM user_sessions WHERE session_id = :sid LIMIT 1',
                [':sid' => $sessionId]
            );
            if ($existing) {
                $db->run(
                    'UPDATE user_sessions SET last_seen_at = NOW(), ip_address = :ip WHERE id = :id',
                    [':id' => (int) $existing['id'], ':ip' => $ip]
                );
                return;
            }
            $db->insert('user_sessions', [
                'user_id'     => $userId,
                'session_id'  => $sessionId,
                'ip_address'  => $ip ? mb_substr($ip, 0, 45) : null,
                'user_agent'  => $ua ? mb_substr($ua, 0, 500) : null,
                'device_kind' => self::detectDevice($ua),
            ]);
        } catch (\Throwable) {}
    }

    public static function forUser(int $userId): array
    {
        try {
            return Database::instance()->fetchAll(
                'SELECT * FROM user_sessions WHERE user_id = :uid ORDER BY last_seen_at DESC LIMIT 50',
                [':uid' => $userId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public static function delete(int $userId, int $id): bool
    {
        try {
            $row = Database::instance()->fetch(
                'SELECT * FROM user_sessions WHERE id = :id AND user_id = :uid LIMIT 1',
                [':id' => $id, ':uid' => $userId]
            );
            if (!$row) return false;
            Database::instance()->delete('user_sessions', 'id = :id', [':id' => $id]);
            // PHP file-based session ise session dosyasını silemiyoruz; ama session_id geçersizdir,
            // gc süresi sonunda otomatik temizlenir.
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Bir kullanıcının current dışındaki TÜM oturumlarını terminate eder.
     * Şifre değişimi, email değişim onayı, "tüm cihazlardan çıkış" akışlarında
     * çağrılır. user_sessions tablosundaki sıraları siler; PHP file-based
     * session dosyaları gc süresi sonunda otomatik temizlenir.
     *
     * @return int silinen oturum sayısı
     */
    public static function deleteAllForUserExceptCurrent(int $userId, string $currentSessionId): int
    {
        try {
            return Database::instance()->delete(
                'user_sessions',
                'user_id = :uid AND session_id <> :sid',
                [':uid' => $userId, ':sid' => $currentSessionId]
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Tüm oturumları (current dahil) siler — örn. "hesabı sil" sonrası.
     */
    public static function deleteAllForUser(int $userId): int
    {
        try {
            return Database::instance()->delete(
                'user_sessions',
                'user_id = :uid',
                [':uid' => $userId]
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function pruneStale(int $days = 30): int
    {
        try {
            return Database::instance()->run(
                'DELETE FROM user_sessions WHERE last_seen_at < DATE_SUB(NOW(), INTERVAL :d DAY)',
                [':d' => max(1, $days)]
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function detectDevice(?string $ua): ?string
    {
        if (!$ua) return null;
        if (preg_match('/iPhone|iPod/i', $ua)) return 'iPhone';
        if (preg_match('/iPad/i', $ua)) return 'iPad';
        if (preg_match('/Android/i', $ua)) return 'Android';
        if (preg_match('/Macintosh/i', $ua)) return 'Mac';
        if (preg_match('/Windows/i', $ua)) return 'Windows';
        if (preg_match('/Linux/i', $ua)) return 'Linux';
        return 'Bilinmeyen';
    }
}
