<?php
declare(strict_types=1);

namespace App\Services\Schema;

use App\Core\Config;
use App\Models\Setting;

/**
 * WebPage (ve alt-tipleri) için JSON-LD builder.
 *
 * @graph içinde her sayfa için bir WebPage node bulunur — bu node Article,
 * BreadcrumbList, ImageObject, Organization gibi diğer node'lara @id ile
 * bağlanır. Google Page Hierarchy parser'ı için merkezi düğüm.
 *
 * Alt-tipler:
 *   WebPage          (default)
 *   CollectionPage   (kategori sayfası — ItemList builder ayrıca üretir)
 *   ProfilePage      (yazar profili — ProfilePage builder ayrıca üretir)
 *   AboutPage / ContactPage / SearchResultsPage / FAQPage (gerekirse)
 */
final class WebPage
{
    public static function build(string $url, string $name, array $opts = []): array
    {
        $type = (string) ($opts['type'] ?? 'WebPage');
        $siteUrl = rtrim((string) Setting::get('canonical_base', Config::get('APP_URL', ''), 'seo'), '/');

        $node = [
            '@type'      => $type,
            '@id'        => $url . '#webpage',
            'url'        => $url,
            'name'       => mb_substr($name, 0, 110),
            'inLanguage' => (string) Setting::get('site_locale', Config::get('APP_LOCALE', 'tr'), 'general'),
        ];

        if ($siteUrl !== '') {
            $node['isPartOf'] = ['@id' => $siteUrl . '#website'];
        }

        if (!empty($opts['description'])) {
            $node['description'] = mb_substr((string) $opts['description'], 0, 280);
        }

        // Tarihler — Article veya kategori update timestamps
        if (!empty($opts['datePublished'])) {
            $ts = is_numeric($opts['datePublished']) ? (int) $opts['datePublished'] : strtotime((string) $opts['datePublished']);
            if ($ts) {
                $node['datePublished'] = date('c', $ts);
            }
        }
        if (!empty($opts['dateModified'])) {
            $ts = is_numeric($opts['dateModified']) ? (int) $opts['dateModified'] : strtotime((string) $opts['dateModified']);
            if ($ts) {
                $node['dateModified'] = date('c', $ts);
            }
        }

        // Primary image — Article cover ile aynı @id'yi kullanır
        if (!empty($opts['primary_image_id'])) {
            $node['primaryImageOfPage'] = ['@id' => $opts['primary_image_id']];
        }

        // Breadcrumb referansı — eğer BreadcrumbList graph'taysa
        if (!empty($opts['breadcrumb_id'])) {
            $node['breadcrumb'] = ['@id' => $opts['breadcrumb_id']];
        }

        // Sayfanın ana entity'si (örn. Article)
        if (!empty($opts['main_entity_id'])) {
            $node['mainEntity'] = ['@id' => $opts['main_entity_id']];
        }

        // Potential ReadAction — sayfaya erişim modeli
        $node['potentialAction'] = [
            '@type'  => 'ReadAction',
            'target' => [$url],
        ];

        return array_filter($node, static fn($v) => $v !== null && $v !== '' && $v !== []);
    }
}
