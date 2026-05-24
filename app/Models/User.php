<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class User
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_EDITOR = 'editor';
    public const ROLE_AUTHOR = 'author';
    public const ROLE_MEMBER = 'member';
    public const ROLES = [self::ROLE_ADMIN, self::ROLE_EDITOR, self::ROLE_AUTHOR, self::ROLE_MEMBER];

    public static function findById(int $id): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM users WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM users WHERE email = :e LIMIT 1',
            [':e' => mb_strtolower($email)]
        );
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM users WHERE slug = :s LIMIT 1',
            [':s' => $slug]
        );
    }

    public static function emailExists(string $email): bool
    {
        $r = Database::instance()->fetchColumn(
            'SELECT COUNT(*) FROM users WHERE email = :e',
            [':e' => mb_strtolower($email)]
        );
        return (int) $r > 0;
    }

    public static function create(array $data): int
    {
        $data['email'] = mb_strtolower((string) $data['email']);
        if (empty($data['slug'])) {
            $data['slug'] = self::uniqueSlug((string) ($data['name'] ?? $data['email']));
        }
        return (int) Database::instance()->insert('users', $data);
    }

    public static function update(int $id, array $data): int
    {
        if (isset($data['email'])) {
            $data['email'] = mb_strtolower((string) $data['email']);
        }
        return Database::instance()->update('users', $data, 'id = :wid', [':wid' => $id]);
    }

    public static function touchLogin(int $id): void
    {
        Database::instance()->update(
            'users',
            ['last_login_at' => date('Y-m-d H:i:s')],
            'id = :wid',
            [':wid' => $id]
        );
    }

    public static function decodeProfile(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    public static function publicAuthors(int $limit = 30): array
    {
        return Database::instance()->fetchAll(
            'SELECT id, name, slug, avatar, bio, profile_json, role
             FROM users
             WHERE status = "active" AND role IN ("admin","editor","author")
             ORDER BY name ASC LIMIT ' . max(1, $limit)
        );
    }

    public static function findByVerifyToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        return Database::instance()->fetch(
            'SELECT * FROM users WHERE email_verification_token = :t LIMIT 1',
            [':t' => $token]
        );
    }

    /**
     * E-posta değişimi pending kaydı — token + 48h expiry kontrolü ile.
     * Kolonlar yoksa (migration 049 henüz çalıştırılmamış) null döner.
     */
    public static function findByEmailPendingToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        try {
            return Database::instance()->fetch(
                'SELECT * FROM users
                 WHERE email_pending_token = :t
                   AND email_pending IS NOT NULL
                   AND (email_pending_expires_at IS NULL OR email_pending_expires_at > NOW())
                 LIMIT 1',
                [':t' => $token]
            );
        } catch (\Throwable) {
            return null;
        }
    }

    public static function listAll(int $limit = 200): array
    {
        return Database::instance()->fetchAll(
            'SELECT id, name, slug, email, role, status, last_login_at,
                    email_verified_at IS NOT NULL AS email_verified, created_at
             FROM users
             ORDER BY id DESC
             LIMIT ' . max(1, $limit)
        );
    }

    public static function uniqueSlug(string $base): string
    {
        $slug = slugify($base);
        if ($slug === '') {
            $slug = 'user';
        }
        $candidate = $slug;
        $i = 1;
        while (Database::instance()->fetchColumn(
            'SELECT 1 FROM users WHERE slug = :s LIMIT 1',
            [':s' => $candidate]
        )) {
            $i++;
            $candidate = $slug . '-' . $i;
        }
        return $candidate;
    }
}
