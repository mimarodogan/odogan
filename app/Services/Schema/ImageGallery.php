<?php
declare(strict_types=1);

namespace App\Services\Schema;

/**
 * ImageGallery JSON-LD — yazı body'sinde 3+ görsel varsa ImageGallery node üretir,
 * her görsel için ImageObject child döner. Schema.org/ImageGallery + ImageObject.
 *
 * Feature flag: image_gallery_enabled (default false).
 *
 * Tetikleme eşiği: body'de en az 3 <img> bulunmalı (1-2 görsel için anlamsız).
 *
 * @see https://schema.org/ImageGallery
 */
final class ImageGallery
{
    /**
     * Eşik: bu sayıdan az img varsa node üretilmez (1-2 görsel galeri sayılmaz).
     */
    private const MIN_IMAGES = 3;

    /**
     * Aynı görseli iki kez eklememek için URL bazlı dedup.
     */
    private const MAX_IMAGES = 30;

    /**
     * Yazı body'sinde img tag'lerini bul, ImageGallery node'u döndür.
     * 3+ img yoksa null döner — Renderer null'ı eklemez.
     *
     * @param string $bodyHtml Render edilmiş post body (markdown → HTML çıktısı)
     * @param string $postUrl  Yazının canonical URL'si
     * @param string $postTitle Galeri name alanı için
     */
    public static function build(string $bodyHtml, string $postUrl, string $postTitle): ?array
    {
        if (!function_exists('feature') || !feature('image_gallery_enabled')) {
            return null;
        }
        $images = self::extractImages($bodyHtml);
        if (count($images) < self::MIN_IMAGES) {
            return null;
        }

        $items = [];
        $position = 1;
        foreach ($images as $img) {
            $items[] = self::imageObject($img, $postUrl . '#gallery-img-' . $position, $position);
            $position++;
        }

        return [
            '@type'              => 'ImageGallery',
            '@id'                => $postUrl . '#gallery',
            'name'               => mb_substr($postTitle, 0, 110) . ' — Görseller',
            'mainEntityOfPage'   => ['@id' => $postUrl . '#webpage'],
            'numberOfItems'      => count($items),
            'associatedMedia'    => $items,
        ];
    }

    /**
     * Body HTML'inden img tag'lerini parse et.
     * DOMDocument güvenli alternatifi yok (Parsedown HTML üretir, fakat tag farkı olabilir).
     *
     * @return array<int,array{src:string,alt:string,width:?int,height:?int}>
     */
    private static function extractImages(string $html): array
    {
        $html = trim($html);
        if ($html === '' || stripos($html, '<img') === false) {
            return [];
        }

        // Görsel ve attribute'leri yakala — non-greedy, src zorunlu
        $found = [];
        $seen = [];
        if (preg_match_all('/<img\b([^>]+?)>/i', $html, $matches)) {
            foreach ($matches[1] as $attrs) {
                $src = self::attr($attrs, 'src');
                if ($src === '' || isset($seen[$src])) {
                    continue;
                }
                $seen[$src] = true;
                // data: URI atla (BlurHash placeholder vs)
                if (str_starts_with($src, 'data:')) {
                    continue;
                }
                $alt = self::attr($attrs, 'alt');
                $w = self::attr($attrs, 'width');
                $h = self::attr($attrs, 'height');
                $found[] = [
                    'src'    => self::absolutize($src),
                    'alt'    => $alt,
                    'width'  => ctype_digit($w) ? (int) $w : null,
                    'height' => ctype_digit($h) ? (int) $h : null,
                ];
                if (count($found) >= self::MAX_IMAGES) {
                    break;
                }
            }
        }
        return $found;
    }

    private static function attr(string $attrs, string $name): string
    {
        // Hem "..." hem '...' destekle
        if (preg_match('/\b' . preg_quote($name, '/') . '\s*=\s*"([^"]*)"/i', $attrs, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/\b' . preg_quote($name, '/') . '\s*=\s*\'([^\']*)\'/i', $attrs, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    private static function absolutize(string $src): string
    {
        if (preg_match('#^https?://#i', $src)) {
            return $src;
        }
        // Helper url() relative path'i tam URL'e çevirir
        return function_exists('absolute_url') ? absolute_url($src) : $src;
    }

    /**
     * Tek bir ImageObject node — @id ile referanslanabilir.
     *
     * @param array{src:string,alt:string,width:?int,height:?int} $img
     */
    private static function imageObject(array $img, string $id, int $position): array
    {
        $node = [
            '@type'    => 'ImageObject',
            '@id'      => $id,
            'url'      => $img['src'],
            'position' => $position,
        ];
        if ($img['width']) {
            $node['width'] = $img['width'];
        }
        if ($img['height']) {
            $node['height'] = $img['height'];
        }
        if ($img['alt'] !== '') {
            $node['name'] = mb_substr($img['alt'], 0, 220);
            $node['caption'] = mb_substr($img['alt'], 0, 500);
        }
        return $node;
    }
}
