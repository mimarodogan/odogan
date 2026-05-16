<?php
declare(strict_types=1);

namespace App\Services\Schema;

use App\Core\Config;

/**
 * CollectionPage + ItemList JSON-LD üretimi.
 * Kategori sayfalarında o sayfada görünen yazıların özet bir
 * makine-okunur listesini sağlar.
 */
final class ItemList
{
    /**
     * @param array $category   ['name' => ..., 'slug' => ..., 'description' => ...]
     * @param array $posts      her satır title + slug + cover_image içerir
     * @param string $url       sayfanın tam canonical URL'i
     * @param int $startOffset  pagination'da bu sayfanın ilk öğesinin global sırası
     */
    public static function build(array $category, array $posts, string $url, int $startOffset = 1): array
    {
        $items = [];
        $pos = $startOffset;
        foreach ($posts as $p) {
            $postUrl = self::absUrl('/' . $category['slug'] . '/' . ($p['slug'] ?? ''));
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $pos++,
                'url'      => $postUrl,
                'name'     => (string) ($p['title'] ?? ''),
            ];
        }
        return [
            '@type'           => 'CollectionPage',
            '@id'             => $url . '#collection',
            'url'             => $url,
            'name'            => (string) ($category['name'] ?? ''),
            'description'     => mb_substr((string) ($category['description'] ?? ''), 0, 280),
            'inLanguage'      => (string) \App\Models\Setting::get('site_locale', Config::get('APP_LOCALE', 'tr'), 'general'),
            'mainEntity'      => [
                '@type'           => 'ItemList',
                'numberOfItems'   => count($items),
                'itemListElement' => $items,
            ],
        ];
    }

    private static function absUrl(string $path): string
    {
        $base = rtrim((string) \App\Models\Setting::get('canonical_base', Config::get('APP_URL', ''), 'seo'), '/');
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        return $base . '/' . ltrim($path, '/');
    }
}
