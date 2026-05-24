<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Yazı Şablonları (Tier 7 — Editorial Pro).
 *
 * "Yeni İçerik" oluşturulurken yazar bir şablon seçer (haber, rehber, söyleşi, ...).
 * Body editörü şablon HTML'iyle önceden doldurulur.
 */
final class PostTemplate
{
    public static function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM post_templates';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY position ASC, id ASC';
        try {
            return Database::instance()->fetchAll($sql);
        } catch (\Throwable) {
            return [];
        }
    }

    public static function findById(int $id): ?array
    {
        try {
            return Database::instance()->fetch(
                'SELECT * FROM post_templates WHERE id = :id LIMIT 1',
                [':id' => $id]
            );
        } catch (\Throwable) {
            return null;
        }
    }

    public static function findByKey(string $key): ?array
    {
        try {
            return Database::instance()->fetch(
                'SELECT * FROM post_templates WHERE key_name = :k AND is_active = 1 LIMIT 1',
                [':k' => $key]
            );
        } catch (\Throwable) {
            return null;
        }
    }

    public static function update(int $id, array $patch): int
    {
        return Database::instance()->update('post_templates', $patch, 'id = :wid', [':wid' => $id]);
    }

    public static function create(array $data): int
    {
        return (int) Database::instance()->insert('post_templates', $data);
    }

    public static function delete(int $id): int
    {
        return Database::instance()->delete('post_templates', 'id = :wid', [':wid' => $id]);
    }
}
