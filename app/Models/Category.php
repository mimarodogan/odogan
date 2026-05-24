<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Category
{
    public static function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM categories';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY position ASC, name ASC';
        return Database::instance()->fetchAll($sql);
    }

    /**
     * Tüm kategoriler + her birinde yayımlanmış yazı sayısı (post_count).
     * Kategoriler listesi sayfası ve menü rozeti için.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function allWithCounts(bool $activeOnly = true): array
    {
        $sql = 'SELECT c.*, COUNT(p.id) AS post_count
                FROM categories c
                LEFT JOIN posts p
                       ON p.category_id = c.id AND p.status = "published"';
        if ($activeOnly) {
            $sql .= ' WHERE c.is_active = 1';
        }
        $sql .= ' GROUP BY c.id ORDER BY c.position ASC, c.name ASC';
        return Database::instance()->fetchAll($sql);
    }

    public static function findById(int $id): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM categories WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM categories WHERE slug = :s LIMIT 1',
            [':s' => $slug]
        );
    }

    public static function create(array $data): int
    {
        $data['slug'] = self::uniqueSlug((string) ($data['slug'] ?? $data['name'] ?? ''));
        return (int) Database::instance()->insert('categories', $data);
    }

    public static function update(int $id, array $data): int
    {
        if (isset($data['slug'])) {
            $data['slug'] = self::uniqueSlug((string) $data['slug'], $id);
        }
        return Database::instance()->update('categories', $data, 'id = :wid', [':wid' => $id]);
    }

    public static function delete(int $id): int
    {
        return Database::instance()->delete('categories', 'id = :wid', [':wid' => $id]);
    }

    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = slugify($base);
        if ($slug === '') {
            $slug = 'kategori';
        }
        $candidate = $slug;
        $i = 1;
        while (true) {
            $sql = 'SELECT id FROM categories WHERE slug = :s';
            $params = [':s' => $candidate];
            if ($ignoreId !== null) {
                $sql .= ' AND id <> :id';
                $params[':id'] = $ignoreId;
            }
            $sql .= ' LIMIT 1';
            $row = Database::instance()->fetch($sql, $params);
            if ($row === null) {
                return $candidate;
            }
            $i++;
            $candidate = $slug . '-' . $i;
        }
    }

    public static function options(): array
    {
        $rows = self::all(true);
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = $r['name'];
        }
        return $out;
    }
}
