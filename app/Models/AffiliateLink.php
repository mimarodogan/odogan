<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Affiliate / Sponsor Link Tracking (Tier 8 — Monetization).
 *
 * Yazılarda `/git/{code}` formatlı linkler — tıklanınca counter artar ve to_url'e
 * 301 yönlendirme yapılır.
 */
final class AffiliateLink
{
    public static function findByCode(string $code): ?array
    {
        try {
            return Database::instance()->fetch(
                'SELECT * FROM affiliate_links WHERE code = :c AND is_active = 1 LIMIT 1',
                [':c' => $code]
            );
        } catch (\Throwable) {
            return null;
        }
    }

    public static function findById(int $id): ?array
    {
        try {
            return Database::instance()->fetch(
                'SELECT * FROM affiliate_links WHERE id = :id LIMIT 1',
                [':id' => $id]
            );
        } catch (\Throwable) {
            return null;
        }
    }

    public static function bumpClick(int $id): void
    {
        try {
            Database::instance()->run(
                'UPDATE affiliate_links SET click_count = click_count + 1 WHERE id = :id',
                [':id' => $id]
            );
        } catch (\Throwable) {}
    }

    public static function all(int $limit = 200): array
    {
        try {
            return Database::instance()->fetchAll(
                'SELECT * FROM affiliate_links ORDER BY click_count DESC, id DESC LIMIT ' . max(1, $limit)
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public static function create(array $data): int
    {
        try {
            return (int) Database::instance()->insert('affiliate_links', $data);
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function update(int $id, array $patch): int
    {
        try {
            return Database::instance()->update('affiliate_links', $patch, 'id = :wid', [':wid' => $id]);
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function delete(int $id): int
    {
        try {
            return Database::instance()->delete('affiliate_links', 'id = :wid', [':wid' => $id]);
        } catch (\Throwable) {
            return 0;
        }
    }
}
