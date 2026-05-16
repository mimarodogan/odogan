<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Key-value site settings. Backed by the `settings` table.
 *
 * Process-level memoization keeps repeat reads cheap — get('site_name')
 * across a request hits the DB at most once.
 */
final class Setting
{
    /** @var array<string,mixed>|null  group_name -> [key => decoded value] */
    private static ?array $cache = null;

    /**
     * Retrieve a setting value. Returns $default when missing or stored value
     * is null/empty.
     */
    public static function get(string $key, mixed $default = null, string $group = 'general'): mixed
    {
        $all = self::loadAll();
        $val = $all[$group][$key] ?? null;
        if ($val === null || $val === '') {
            return $default;
        }
        return $val;
    }

    /**
     * Bulk fetch a single group (e.g. 'general', 'seo', 'analytics').
     * @return array<string,mixed>
     */
    public static function group(string $group): array
    {
        return self::loadAll()[$group] ?? [];
    }

    /**
     * Insert or update a single setting.
     */
    public static function set(string $key, mixed $value, string $group = 'general', string $type = 'string', bool $isPublic = false): void
    {
        $stored = self::encode($value, $type);
        $db = Database::instance();
        $existing = $db->fetch(
            'SELECT id FROM settings WHERE group_name = :g AND key_name = :k LIMIT 1',
            [':g' => $group, ':k' => $key]
        );
        if ($existing) {
            $db->update('settings',
                ['value' => $stored, 'value_type' => $type, 'is_public' => $isPublic ? 1 : 0],
                'id = :id',
                [':id' => (int) $existing['id']]
            );
        } else {
            $db->insert('settings', [
                'group_name' => $group,
                'key_name'   => $key,
                'value'      => $stored,
                'value_type' => $type,
                'is_public'  => $isPublic ? 1 : 0,
            ]);
        }
        self::$cache = null;
    }

    /**
     * Apply a flat key=>value map under one group.
     *
     * @param array<string,mixed> $values
     * @param array<string,string> $types  optional key=>value_type override map
     */
    public static function saveGroup(string $group, array $values, array $types = []): void
    {
        foreach ($values as $key => $value) {
            $type = $types[$key] ?? self::inferType($value);
            self::set((string) $key, $value, $group, $type);
        }
    }

    public static function flushCache(): void
    {
        self::$cache = null;
    }

    /** @return array<string,array<string,mixed>> */
    private static function loadAll(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $rows = [];
        try {
            $rows = Database::instance()->fetchAll(
                'SELECT group_name, key_name, value, value_type FROM settings'
            );
        } catch (\Throwable) {
            // Migrations not yet run — behave as if every setting is absent.
        }
        $out = [];
        foreach ($rows as $r) {
            $out[$r['group_name']][$r['key_name']] = self::decode(
                $r['value'] ?? null,
                (string) ($r['value_type'] ?? 'string')
            );
        }
        return self::$cache = $out;
    }

    private static function encode(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }
        return match ($type) {
            'int'  => (string) (int) $value,
            'bool' => $value ? '1' : '0',
            'json' => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default => (string) $value,
        };
    }

    private static function decode(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }
        return match ($type) {
            'int'  => (int) $value,
            'bool' => $value === '1' || strtolower($value) === 'true',
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    private static function inferType(mixed $value): string
    {
        if (is_bool($value))   return 'bool';
        if (is_int($value))    return 'int';
        if (is_array($value))  return 'json';
        return 'string';
    }
}
