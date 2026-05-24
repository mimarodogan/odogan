<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Architectural Glossary (Tier 7 — Architecture niche).
 *
 * Mimari/mühendislik terimleri sözlüğü. Public side `/sozluk` ve `/sozluk/{slug}`.
 */
final class Glossary
{
    public static function findBySlug(string $slug): ?array
    {
        try {
            return Database::instance()->fetch(
                'SELECT * FROM glossary WHERE slug = :s AND is_active = 1 LIMIT 1',
                [':s' => $slug]
            );
        } catch (\Throwable) {
            return null;
        }
    }

    public static function findById(int $id): ?array
    {
        try {
            return Database::instance()->fetch(
                'SELECT * FROM glossary WHERE id = :id LIMIT 1',
                [':id' => $id]
            );
        } catch (\Throwable) {
            return null;
        }
    }

    public static function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM glossary';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY term ASC';
        try {
            return Database::instance()->fetchAll($sql);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Alfabetik gruplandırılmış liste — public index sayfası için.
     * @return array<string, array<int,array>>
     */
    public static function groupedByLetter(): array
    {
        $items = self::all(true);
        $grouped = [];
        foreach ($items as $item) {
            $first = mb_strtoupper(mb_substr((string) $item['term'], 0, 1));
            // Türkçe Ç,Ğ,İ,Ö,Ş,Ü harfler için fallback latin'e çevirme yerine olduğu gibi grupla
            if (!preg_match('/^[A-ZÇĞİÖŞÜ]/u', $first)) {
                $first = '#';
            }
            $grouped[$first][] = $item;
        }
        ksort($grouped);
        return $grouped;
    }

    public static function search(string $q): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) return [];
        try {
            return Database::instance()->fetchAll(
                'SELECT * FROM glossary
                 WHERE is_active = 1
                   AND (term LIKE :q OR definition LIKE :q OR aliases LIKE :q)
                 ORDER BY term ASC
                 LIMIT 50',
                [':q' => '%' . $q . '%']
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public static function create(array $data): int
    {
        $data['slug'] = self::uniqueSlug((string) ($data['slug'] ?? $data['term'] ?? ''));
        return (int) Database::instance()->insert('glossary', $data);
    }

    public static function update(int $id, array $patch): int
    {
        if (isset($patch['slug']) && $patch['slug'] !== '') {
            $patch['slug'] = self::uniqueSlug($patch['slug'], $id);
        }
        return Database::instance()->update('glossary', $patch, 'id = :wid', [':wid' => $id]);
    }

    public static function delete(int $id): int
    {
        return Database::instance()->delete('glossary', 'id = :wid', [':wid' => $id]);
    }

    public static function bumpView(int $id): void
    {
        try {
            Database::instance()->run(
                'UPDATE glossary SET view_count = view_count + 1 WHERE id = :id',
                [':id' => $id]
            );
        } catch (\Throwable) {}
    }

    /**
     * Aynı kategorideki diğer terimler — "İlgili Terimler" widget'ı için.
     */
    public static function relatedByCategory(int $excludeId, string $category, int $limit = 6): array
    {
        $limit = max(1, $limit);
        if ($category === '') {
            return self::recentlyAdded($excludeId, $limit);
        }
        try {
            return Database::instance()->fetchAll(
                'SELECT id, term, slug, category, definition
                 FROM glossary
                 WHERE is_active = 1
                   AND id != :eid
                   AND category = :cat
                 ORDER BY view_count DESC, term ASC
                 LIMIT ' . $limit,
                [':eid' => $excludeId, ':cat' => $category]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Rastgele N aktif terim — anasayfa "Bugün öğren" widget'ı için.
     * Günde bir kez cache'lenir.
     *
     * @return array<int,array{id:int,term:string,slug:string,category:?string,definition:string}>
     */
    public static function randomPicks(int $limit = 3): array
    {
        $limit = max(1, min($limit, 12));
        try {
            return Database::instance()->fetchAll(
                'SELECT id, term, slug, category, definition
                 FROM glossary
                 WHERE is_active = 1
                 ORDER BY RAND()
                 LIMIT ' . $limit
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Belirli bir kategorideki tüm aktif terimler — public filtre için.
     *
     * @return array<int,array>
     */
    public static function byCategory(string $category): array
    {
        $category = trim($category);
        if ($category === '') {
            return self::all(true);
        }
        try {
            return Database::instance()->fetchAll(
                'SELECT * FROM glossary WHERE is_active = 1 AND category = :cat ORDER BY term ASC',
                [':cat' => $category]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Tüm aktif kategoriler (count'lu) — kategori filtre chip'leri için.
     *
     * @return array<int,array{category:string,n:int}>
     */
    public static function categoriesList(): array
    {
        try {
            return Database::instance()->fetchAll(
                'SELECT category, COUNT(*) AS n
                 FROM glossary
                 WHERE is_active = 1 AND category IS NOT NULL AND category <> ""
                 GROUP BY category
                 ORDER BY n DESC, category ASC'
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Bir terimin (term + alias) geçtiği son yayınlanmış yazıları döndürür.
     * "Sözlük → yazı geri linkleme" widget'ı için.
     *
     * @return array<int,array{id:int,title:string,slug:string,category_slug:string,excerpt:?string,published_at:string}>
     */
    public static function postsContainingTerm(string $term, string $aliasesCsv = '', int $limit = 5): array
    {
        $candidates = [trim($term)];
        if ($aliasesCsv !== '') {
            foreach (array_filter(array_map('trim', explode(',', $aliasesCsv))) as $a) {
                if (mb_strlen($a) >= 3) $candidates[] = $a;
            }
        }
        $candidates = array_unique(array_filter($candidates));
        if ($candidates === []) {
            return [];
        }

        // LIKE pattern'lar — body içinde substring eşleşmesi
        // KeyphraseService kadar derin değil ama hızlı (FULLTEXT olsa daha iyi).
        $clauses = [];
        $params  = [];
        foreach ($candidates as $i => $c) {
            $k = ':kw' . $i;
            $clauses[] = '(LOWER(p.body) LIKE ' . $k . ' OR LOWER(p.title) LIKE ' . $k . ')';
            $params[$k] = '%' . mb_strtolower($c) . '%';
        }
        $where = implode(' OR ', $clauses);

        $limit = max(1, min($limit, 20));
        try {
            return Database::instance()->fetchAll(
                "SELECT p.id, p.title, p.slug, p.excerpt, p.published_at, c.slug AS category_slug
                 FROM posts p
                 LEFT JOIN categories c ON c.id = p.category_id
                 WHERE p.status = 'published' AND p.published_at IS NOT NULL
                   AND ($where)
                 ORDER BY p.published_at DESC
                 LIMIT " . $limit,
                $params
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Son eklenen terimler — kategori yoksa fallback.
     */
    public static function recentlyAdded(int $excludeId, int $limit = 6): array
    {
        $limit = max(1, $limit);
        try {
            return Database::instance()->fetchAll(
                'SELECT id, term, slug, category, definition
                 FROM glossary
                 WHERE is_active = 1 AND id != :eid
                 ORDER BY id DESC
                 LIMIT ' . $limit,
                [':eid' => $excludeId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    private static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = function_exists('slugify') ? slugify($base) : preg_replace('/[^a-z0-9-]/i', '-', mb_strtolower($base));
        $slug = trim((string) $slug, '-');
        if ($slug === '') $slug = 'terim';
        $candidate = $slug;
        $i = 1;
        while (true) {
            $sql = 'SELECT id FROM glossary WHERE slug = :s';
            $params = [':s' => $candidate];
            if ($ignoreId !== null) {
                $sql .= ' AND id <> :id';
                $params[':id'] = $ignoreId;
            }
            $sql .= ' LIMIT 1';
            try {
                if (Database::instance()->fetch($sql, $params) === null) {
                    return $candidate;
                }
            } catch (\Throwable) {
                return $candidate;
            }
            $i++;
            $candidate = $slug . '-' . $i;
        }
    }
}
