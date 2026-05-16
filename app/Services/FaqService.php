<?php
declare(strict_types=1);

namespace App\Services;

/**
 * FAQ list normalization for `posts.faq_json`.
 *
 * Stored shape:
 *   [{"q":"...","a":"...markdown..."}]
 *
 * This same array is later consumed by SchemaService (Phase 4)
 * to emit FAQPage JSON-LD.
 */
final class FaqService
{
    public const MAX_ITEMS = 30;
    public const MAX_Q = 220;
    public const MAX_A = 4000;

    /**
     * @param mixed $input  Either an already-decoded array (from JSON) or the
     *                      raw POST shape `[{"q":"","a":""}, ...]`.
     * @return array<int,array{q:string,a:string}>
     */
    public static function normalize(mixed $input): array
    {
        if (is_string($input)) {
            $decoded = json_decode($input, true);
            $input = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($input)) {
            return [];
        }
        $out = [];
        foreach ($input as $row) {
            if (!is_array($row)) {
                continue;
            }
            $q = trim((string) ($row['q'] ?? $row['question'] ?? ''));
            $a = trim((string) ($row['a'] ?? $row['answer'] ?? ''));
            if ($q === '' || $a === '') {
                continue;
            }
            $out[] = [
                'q' => mb_substr($q, 0, self::MAX_Q),
                'a' => mb_substr($a, 0, self::MAX_A),
            ];
            if (count($out) >= self::MAX_ITEMS) {
                break;
            }
        }
        return $out;
    }

    public static function encode(array $items): string
    {
        return (string) json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function decode(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $data = json_decode($json, true);
        return self::normalize($data);
    }
}
