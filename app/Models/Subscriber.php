<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Subscriber
{
    public static function findByEmail(string $email): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM subscribers WHERE email = :e LIMIT 1',
            [':e' => mb_strtolower($email)]
        );
    }

    public static function findByConfirmToken(string $token): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM subscribers WHERE confirm_token = :t LIMIT 1',
            [':t' => $token]
        );
    }

    public static function findByUnsubToken(string $token): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM subscribers WHERE unsub_token = :t LIMIT 1',
            [':t' => $token]
        );
    }

    public static function create(string $email, ?string $name, ?string $ip): array
    {
        $confirmToken = bin2hex(random_bytes(24));
        $unsubToken   = bin2hex(random_bytes(24));
        $id = Database::instance()->insert('subscribers', [
            'email' => mb_strtolower($email),
            'name' => $name ? mb_substr($name, 0, 100) : null,
            'confirm_token' => $confirmToken,
            'unsub_token' => $unsubToken,
            'ip_address' => $ip,
        ]);
        return self::findById((int) $id) ?? [
            'id' => (int) $id, 'email' => $email, 'name' => $name,
            'confirm_token' => $confirmToken, 'unsub_token' => $unsubToken,
        ];
    }

    public static function findById(int $id): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM subscribers WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
    }

    public static function confirm(int $id, ?string $brevoContactId = null): void
    {
        Database::instance()->update('subscribers', [
            'confirmed_at' => date('Y-m-d H:i:s'),
            'confirm_token' => null,
            'brevo_contact_id' => $brevoContactId,
        ], 'id = :id', [':id' => $id]);
    }

    public static function deleteById(int $id): void
    {
        Database::instance()->delete('subscribers', 'id = :id', [':id' => $id]);
    }

    /**
     * Onaylı abonelerin listesi (admin görüntülemesi için).
     */
    public static function listAll(int $limit = 1000, int $offset = 0): array
    {
        return Database::instance()->fetchAll(
            'SELECT id, email, name, confirmed_at, brevo_contact_id, created_at
             FROM subscribers
             ORDER BY id DESC
             LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset)
        );
    }

    public static function stats(): array
    {
        $db = Database::instance();
        return [
            'total' => (int) $db->fetchColumn('SELECT COUNT(*) FROM subscribers'),
            'confirmed' => (int) $db->fetchColumn('SELECT COUNT(*) FROM subscribers WHERE confirmed_at IS NOT NULL'),
            'pending' => (int) $db->fetchColumn('SELECT COUNT(*) FROM subscribers WHERE confirmed_at IS NULL'),
        ];
    }
}
