<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Cache\CacheManager;
use App\Core\Database;

/**
 * Sözleşmeler (Tier 6) — üyelik, yazar, gizlilik, kullanım koşulları.
 *
 * Admin panelden HTML olarak düzenlenir, public side'da `/sozlesmeler/{slug}`.
 * Yazar başvuru wizard + register form bu modelden okur.
 */
final class LegalDocument
{
    /**
     * Slug ile getir — cache'lenir (Settings benzeri yaklaşım, 1 saat TTL).
     */
    public static function findBySlug(string $slug): ?array
    {
        try {
            $cache = CacheManager::driver();
            return $cache->remember(
                'legal:' . $slug,
                3600,
                static function () use ($slug) {
                    $row = Database::instance()->fetch(
                        'SELECT * FROM legal_documents WHERE slug = :s AND is_active = 1 LIMIT 1',
                        [':s' => $slug]
                    );
                    return $row;
                },
                ['legal']
            );
        } catch (\Throwable) {
            try {
                return Database::instance()->fetch(
                    'SELECT * FROM legal_documents WHERE slug = :s AND is_active = 1 LIMIT 1',
                    [':s' => $slug]
                );
            } catch (\Throwable) {
                return null;
            }
        }
    }

    public static function findById(int $id): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM legal_documents WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
    }

    /**
     * Admin listesi — tüm sözleşmeler.
     */
    public static function all(): array
    {
        try {
            return Database::instance()->fetchAll(
                'SELECT id, slug, title, version, is_active, updated_at
                 FROM legal_documents
                 ORDER BY slug ASC'
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public static function update(int $id, array $patch): int
    {
        // version artırma: body değişti mi?
        $existing = self::findById($id);
        if ($existing && isset($patch['body_html']) && $patch['body_html'] !== $existing['body_html']) {
            $patch['version'] = (int) $existing['version'] + 1;
        }
        $n = Database::instance()->update('legal_documents', $patch, 'id = :wid', [':wid' => $id]);
        try {
            CacheManager::driver()->invalidateTags(['legal']);
        } catch (\Throwable) {}
        return $n;
    }

    /**
     * Yardımcı: slug'a göre body_html döner (sözleşme metni — yazar başvuru wizard'ı vb. için).
     */
    public static function bodyOf(string $slug, string $fallback = ''): string
    {
        $row = self::findBySlug($slug);
        if (!$row || empty($row['body_html'])) {
            return $fallback;
        }
        return (string) $row['body_html'];
    }
}
