<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Admin Audit Log (Tier 7) — hassas admin işlemleri kayıt edilir.
 *
 * Kullanım: AuditLog::record('user.role_change', ['target' => 'user:42'], 'Admin Ali → editor')
 */
final class AuditLog
{
    public static function record(
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        ?string $summary = null,
        array $meta = []
    ): void {
        try {
            $user = \App\Services\AuthService::user();
            Database::instance()->insert('audit_log', [
                'actor_id'    => $user['id'] ?? null,
                'actor_name'  => $user['name'] ?? null,
                'action'      => mb_substr($action, 0, 80),
                'target_type' => $targetType ? mb_substr($targetType, 0, 60) : null,
                'target_id'   => $targetId,
                'summary'     => $summary ? mb_substr($summary, 0, 500) : null,
                'meta_json'   => $meta ? (string) json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                'ip_address'  => self::clientIp(),
            ]);
        } catch (\Throwable) {
            // Audit log her zaman fail-safe — ana akışı bloklamaz
        }
    }

    public static function list(int $limit = 100, int $offset = 0, array $filters = []): array
    {
        $sql = 'SELECT * FROM audit_log WHERE 1=1';
        $params = [];
        if (!empty($filters['action'])) {
            $sql .= ' AND action = :a';
            $params[':a'] = $filters['action'];
        }
        if (!empty($filters['actor_id'])) {
            $sql .= ' AND actor_id = :uid';
            $params[':uid'] = (int) $filters['actor_id'];
        }
        if (!empty($filters['since'])) {
            $sql .= ' AND created_at >= :since';
            $params[':since'] = $filters['since'];
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);
        try {
            return Database::instance()->fetchAll($sql, $params);
        } catch (\Throwable) {
            return [];
        }
    }

    public static function count(array $filters = []): int
    {
        $sql = 'SELECT COUNT(*) FROM audit_log WHERE 1=1';
        $params = [];
        if (!empty($filters['action'])) {
            $sql .= ' AND action = :a';
            $params[':a'] = $filters['action'];
        }
        try {
            return (int) Database::instance()->fetchColumn($sql, $params);
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function clientIp(): ?string
    {
        // Trusted-proxy aware: Cloudflare/Reverse-proxy arkasında REMOTE_ADDR
        // edge IP'sidir; audit log'da saldırgan IP'sini doğru tutmak için
        // RealIpService XFF/CF-Connecting-IP zincirinden gerçek client'ı çözer.
        try {
            $ip = \App\Services\RealIpService::ip();
            return $ip !== '' ? $ip : null;
        } catch (\Throwable) {
            return (string) ($_SERVER['REMOTE_ADDR'] ?? '') ?: null;
        }
    }
}
