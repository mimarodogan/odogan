<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Resolves a `posts.cover_image` value (which may be a numeric media id,
 * an `/uploads/...` path written by MediaController, or an external URL)
 * back to the row + variants needed by the picture() helper.
 */
final class MediaResolver
{
    public static function fromPath(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = (string) $value;

        if (ctype_digit($value)) {
            return self::byId((int) $value);
        }

        // External URL — return a minimal object the picture() helper can use.
        if (preg_match('#^https?://#i', $value)) {
            return ['path' => $value, 'variants' => [], 'width' => null, 'height' => null];
        }

        $row = Database::instance()->fetch(
            'SELECT * FROM media WHERE path = :p LIMIT 1',
            [':p' => ltrim($value, '/')]
        );
        if ($row !== null) {
            $row['variants'] = $row['variants_json']
                ? (array) json_decode((string) $row['variants_json'], true)
                : [];
            return $row;
        }
        return ['path' => ltrim($value, '/'), 'variants' => [], 'width' => null, 'height' => null];
    }

    public static function byId(int $id): ?array
    {
        $row = Database::instance()->fetch('SELECT * FROM media WHERE id = :id LIMIT 1', [':id' => $id]);
        if ($row === null) {
            return null;
        }
        $row['variants'] = $row['variants_json']
            ? (array) json_decode((string) $row['variants_json'], true)
            : [];
        return $row;
    }
}
