<?php
declare(strict_types=1);

namespace App\Services\Schema;

/**
 * /harita sayfası için Schema.org ItemList — her marker bir Place node'u.
 * Google'a "burada coğrafi içerik var" sinyali verir; aynı zamanda kategorik
 * sorgularda ("Bursa'daki oteller") eşleşme şansı oluşturur.
 *
 * SALAMA BİLGİ YOK — lat/lng veya address eksikse o nokta atlanır.
 */
final class MapPlaces
{
    /**
     * @param array<int,array> $points Project::geoTagged() çıktısı
     * @return array|null              ItemList node veya null (boşsa)
     */
    public static function build(array $points): ?array
    {
        if (empty($points)) return null;

        $items = [];
        $position = 1;
        foreach ($points as $p) {
            $lat = $p['lat'] ?? null;
            $lng = $p['lng'] ?? null;
            if ($lat === null || $lng === null || !is_numeric($lat) || !is_numeric($lng)) {
                continue;
            }

            $placeUrl = url('/proje/' . ($p['slug'] ?? ''));

            $place = [
                '@type' => 'Place',
                'name'  => (string) ($p['name'] ?? ''),
                'url'   => $placeUrl,
                'geo'   => [
                    '@type'     => 'GeoCoordinates',
                    'latitude'  => (float) $lat,
                    'longitude' => (float) $lng,
                ],
            ];

            // Structured address — sadece doluysa
            $locality = trim((string) ($p['address_locality'] ?? ''));
            $region   = trim((string) ($p['address_region']   ?? ''));
            $postal   = trim((string) ($p['postal_code']      ?? ''));
            if ($locality !== '' || $region !== '' || $postal !== '') {
                $addr = ['@type' => 'PostalAddress', 'addressCountry' => 'TR'];
                if ($locality !== '') $addr['addressLocality'] = $locality;
                if ($region   !== '') $addr['addressRegion']   = $region;
                if ($postal   !== '') $addr['postalCode']      = $postal;
                $place['address'] = $addr;
            }

            $items[] = [
                '@type'    => 'ListItem',
                'position' => $position++,
                'item'     => $place,
            ];
        }

        if (empty($items)) return null;

        return [
            '@type'           => 'ItemList',
            '@id'             => url('/harita') . '#yapi-listesi',
            'name'            => 'Yapı Haritası',
            'description'     => 'Türkiye genelinde mimari proje, restorasyon ve koruma çalışmaları.',
            'numberOfItems'   => count($items),
            'itemListElement' => $items,
        ];
    }
}
