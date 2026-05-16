<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Cache\CacheManager;
use App\Core\Database;

/**
 * Mimari Proje Portfolyosu (Tier 9).
 *
 * Bir proje: ad, slug, lokasyon (lat/lng), yıl aralığı, rol,
 * yüzölçümü, müşteri, partner JSON, galeri JSON, kapak görsel.
 *
 * Yazılar (`posts.project_id`) bir projeyle ilişkilendirilebilir;
 * bir proje sayfasında ilgili yazılar listelenir.
 */
final class Project
{
    /**
     * Yapı tipleri — projenin TÜRÜ (konut, otel, ofis vb.).
     * "role" ile karıştırılmamalı — role kullanıcının projedeki ROLÜ.
     */
    public const BUILDING_TYPES = [
        'konut'       => 'Konut',
        'otel'        => 'Otel',
        'ofis'        => 'Ofis',
        'ticari'      => 'Ticari',
        'karma'       => 'Karma Kullanım',
        'kamu'        => 'Kamu Binası',
        'egitim'      => 'Eğitim Yapısı',
        'saglik'      => 'Sağlık Yapısı',
        'kultur'      => 'Kültür Yapısı',
        'dini'        => 'Dini Yapı',
        'endustri'    => 'Endüstri',
        'restorasyon' => 'Restorasyon',
        'diger'       => 'Diğer',
    ];

    /**
     * Ekip disiplinleri — team_json içindeki grupların etiketleri.
     */
    public const TEAM_GROUPS = [
        'architects'  => 'Mimari Ekip',
        'engineers'   => 'Mühendislik',
        'consultants' => 'Danışmanlar',
    ];

    public static function buildingTypeLabel(string $key): string
    {
        return self::BUILDING_TYPES[$key] ?? self::BUILDING_TYPES['diger'];
    }

    public static function findById(int $id): ?array
    {
        $row = Database::instance()->fetch(
            'SELECT * FROM projects WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
        return $row ? self::decode($row) : null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $row = Database::instance()->fetch(
            'SELECT * FROM projects WHERE slug = :s LIMIT 1',
            [':s' => $slug]
        );
        return $row ? self::decode($row) : null;
    }

    /**
     * Tüm projeler — admin liste.
     */
    public static function all(int $limit = 200): array
    {
        $rows = Database::instance()->fetchAll(
            'SELECT * FROM projects ORDER BY updated_at DESC LIMIT ' . max(1, $limit)
        );
        return array_map([self::class, 'decode'], $rows);
    }

    /**
     * Bir kullanıcıya ait projeler (Author kendi listesini görür).
     * Migration 044 öncesi user_id kolonu yoksa boş döner (defansif).
     */
    public static function allForUser(int $userId, int $limit = 200): array
    {
        try {
            $rows = Database::instance()->fetchAll(
                'SELECT * FROM projects WHERE user_id = :uid ORDER BY updated_at DESC LIMIT ' . max(1, $limit),
                [':uid' => $userId]
            );
            return array_map([self::class, 'decode'], $rows);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Onay bekleyen projeler — admin badge için.
     * Migration 044 henüz uygulanmadıysa kolon yok → 0 döner (defansif).
     */
    public static function pendingApprovalCount(): int
    {
        try {
            return (int) Database::instance()->fetchColumn(
                "SELECT COUNT(*) FROM projects WHERE approval_stage = 'review'"
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Public listeleme — yalnız yayında olanlar.
     */
    public static function published(int $limit = 100, int $offset = 0): array
    {
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        $rows = Database::instance()->fetchAll(
            "SELECT * FROM projects WHERE status='published' ORDER BY year_completed DESC, published_at DESC LIMIT {$limit} OFFSET {$offset}"
        );
        return array_map([self::class, 'decode'], $rows);
    }

    public static function countPublished(): int
    {
        return (int) Database::instance()->fetchColumn(
            "SELECT COUNT(*) FROM projects WHERE status='published'"
        );
    }

    public static function featured(int $limit = 6): array
    {
        $rows = Database::instance()->fetchAll(
            "SELECT * FROM projects WHERE status='published' AND featured=1 ORDER BY year_completed DESC LIMIT " . max(1, $limit)
        );
        return array_map([self::class, 'decode'], $rows);
    }

    /**
     * Geo-tagged yayında projeler — harita için.
     */
    public static function geoTagged(): array
    {
        try {
            $rows = Database::instance()->fetchAll(
                "SELECT id, name, slug, location, lat, lng, year_completed, cover_image, role,
                        building_type, address_locality, address_region, postal_code
                 FROM projects
                 WHERE status='published' AND lat IS NOT NULL AND lng IS NOT NULL
                 ORDER BY year_completed DESC"
            );
        } catch (\Throwable) {
            try {
                // Migration 048 öncesi address kolonları yoksa — sadece 047 alanları
                $rows = Database::instance()->fetchAll(
                    "SELECT id, name, slug, location, lat, lng, year_completed, cover_image, role, building_type
                     FROM projects
                     WHERE status='published' AND lat IS NOT NULL AND lng IS NOT NULL
                     ORDER BY year_completed DESC"
                );
            } catch (\Throwable) {
                // Migration 047 öncesi building_type yok — minimum fallback
                $rows = Database::instance()->fetchAll(
                    "SELECT id, name, slug, location, lat, lng, year_completed, cover_image, role
                     FROM projects
                     WHERE status='published' AND lat IS NOT NULL AND lng IS NOT NULL
                     ORDER BY year_completed DESC"
                );
                foreach ($rows as &$r) { $r['building_type'] = 'diger'; }
            }
        }
        return $rows ?: [];
    }

    /**
     * Harita için tanı istatistikleri (admin tarafı).
     *
     * @return array{
     *   published_with_coords:int,
     *   published_without_coords:int,
     *   draft_with_coords:int,
     *   draft_total:int,
     *   missing_coords:array<int,array{id:int,name:string,slug:string,status:string}>
     * }
     */
    public static function mapStats(): array
    {
        $db = Database::instance();
        $base = [
            'published_with_coords' => 0,
            'published_without_coords' => 0,
            'draft_with_coords' => 0,
            'draft_total' => 0,
            'missing_coords' => [],
        ];
        try {
            $base['published_with_coords'] = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM projects WHERE status='published' AND lat IS NOT NULL AND lng IS NOT NULL"
            );
            $base['published_without_coords'] = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM projects WHERE status='published' AND (lat IS NULL OR lng IS NULL)"
            );
            $base['draft_with_coords'] = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM projects WHERE status='draft' AND lat IS NOT NULL AND lng IS NOT NULL"
            );
            $base['draft_total'] = (int) $db->fetchColumn(
                "SELECT COUNT(*) FROM projects WHERE status='draft'"
            );
            // Eksik koordinatlı veya draft kalan projelerin listesi (max 20)
            $base['missing_coords'] = $db->fetchAll(
                "SELECT id, name, slug, status, lat, lng
                 FROM projects
                 WHERE status IN ('draft','published')
                   AND (status='draft' OR lat IS NULL OR lng IS NULL)
                 ORDER BY updated_at DESC LIMIT 20"
            ) ?: [];
        } catch (\Throwable) { /* tablo yoksa default 0'lar */ }
        return $base;
    }

    public static function create(array $data): int
    {
        $data['slug'] = self::resolveSlug($data['slug'] ?? '', $data['name'] ?? '');
        $data = self::encode($data);
        $id = (int) Database::instance()->insert('projects', $data);
        CacheManager::driver()->invalidateTags(['projects', 'home', 'sitemap']);
        // IndexNow — yeni published proje bildir
        if (($data['status'] ?? null) === 'published' && !empty($data['slug'])) {
            \App\Services\IndexNow::ping(url('/proje/' . $data['slug']));
        }
        return $id;
    }

    public static function update(int $id, array $data): int
    {
        if (array_key_exists('slug', $data)) {
            $data['slug'] = self::resolveSlug($data['slug'] ?? '', $data['name'] ?? null, $id);
        }
        $data = self::encode($data);
        $n = Database::instance()->update('projects', $data, 'id = :wid', [':wid' => $id]);
        CacheManager::driver()->invalidateTags(['project:' . $id, 'projects', 'home', 'sitemap']);
        // IndexNow — published proje update bildir
        if (($data['status'] ?? null) === 'published' && !empty($data['slug'])) {
            \App\Services\IndexNow::ping(url('/proje/' . $data['slug']));
        }
        return $n;
    }

    /**
     * Slug çözücü: kullanıcı slug yazmadıysa veya çok kısaysa (slugify
     * sonrası 3 karakterden az), proje adından otomatik üretir.
     * Edit'te $ignoreId verilirse o satır slug çakışmasından muaf.
     */
    private static function resolveSlug(mixed $rawSlug, mixed $name = null, ?int $ignoreId = null): string
    {
        $raw = trim((string) $rawSlug);
        $candidate = slugify($raw);
        // 3 karakterden kısa veya boş → name'den üret
        if (mb_strlen($candidate) < 3 && $name !== null) {
            $candidate = slugify((string) $name);
        }
        if (mb_strlen($candidate) < 3) {
            $candidate = 'proje';
        }
        return self::uniqueSlug($candidate, $ignoreId);
    }

    public static function delete(int $id): int
    {
        $n = Database::instance()->delete('projects', 'id = :wid', [':wid' => $id]);
        CacheManager::driver()->invalidateTags(['project:' . $id, 'projects', 'home']);
        return $n;
    }

    /**
     * Bir projeye atfedilen yazılar (yayında).
     */
    public static function postsFor(int $projectId, int $limit = 20): array
    {
        $sql = 'SELECT p.id, p.title, p.slug, p.excerpt, p.cover_image, p.published_at, p.reading_minutes,
                       c.name AS category_name, c.slug AS category_slug,
                       u.name AS author_name, u.slug AS author_slug
                FROM posts p
                INNER JOIN categories c ON c.id = p.category_id
                INNER JOIN users u ON u.id = p.user_id
                WHERE p.project_id = :pid AND p.status = "published"
                ORDER BY p.published_at DESC LIMIT ' . max(1, $limit);
        return Database::instance()->fetchAll($sql, [':pid' => $projectId]);
    }

    public static function bumpViews(int $id): void
    {
        Database::instance()->run(
            'UPDATE projects SET view_count = view_count + 1 WHERE id = :id',
            [':id' => $id]
        );
    }

    /**
     * Listeleme için dropdown (post-form'da kullanılır).
     */
    public static function listActive(int $limit = 200): array
    {
        return Database::instance()->fetchAll(
            "SELECT id, name, year_completed FROM projects WHERE status IN ('draft','published') ORDER BY year_completed DESC, name ASC LIMIT " . max(1, $limit)
        );
    }

    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = slugify($base);
        if ($slug === '') $slug = 'proje';
        $candidate = $slug;
        $i = 1;
        while (true) {
            $sql = 'SELECT id FROM projects WHERE slug = :s';
            $params = [':s' => $candidate];
            if ($ignoreId !== null) {
                $sql .= ' AND id <> :id';
                $params[':id'] = $ignoreId;
            }
            $sql .= ' LIMIT 1';
            if (Database::instance()->fetch($sql, $params) === null) {
                return $candidate;
            }
            $i++;
            $candidate = $slug . '-' . $i;
        }
    }

    private static function encode(array $data): array
    {
        foreach (['partners_json', 'gallery_json', 'tags_json', 'links_json', 'team_json'] as $k) {
            if (isset($data[$k]) && is_array($data[$k])) {
                $data[$k] = json_encode($data[$k], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        return $data;
    }

    private static function decode(array $row): array
    {
        foreach (['partners_json', 'gallery_json', 'tags_json', 'links_json', 'team_json'] as $k) {
            if (isset($row[$k]) && is_string($row[$k]) && $row[$k] !== '') {
                $decoded = json_decode($row[$k], true);
                $row[$k] = is_array($decoded) ? $decoded : [];
            } else {
                $row[$k] = [];
            }
        }
        // team_json normalizasyonu — disiplin gruplarının her zaman array olduğundan emin ol
        if (!isset($row['team_json']) || !is_array($row['team_json'])) {
            $row['team_json'] = [];
        }
        foreach (array_keys(self::TEAM_GROUPS) as $g) {
            if (!isset($row['team_json'][$g]) || !is_array($row['team_json'][$g])) {
                $row['team_json'][$g] = [];
            }
        }
        return $row;
    }
}
