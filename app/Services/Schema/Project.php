<?php
declare(strict_types=1);

namespace App\Services\Schema;

use App\Core\Config;
use App\Models\Setting;
use App\Models\Project as ProjectModel;

/**
 * Schema.org JSON-LD — Proje detay sayfası (Tier 9 schema iyileştirmesi).
 *
 * Hibrit yapı: `Article` + `Place` (varsa coğrafi koordinat ile).
 *
 *   • Article — proje hakkındaki içerik (headline + body + ekip + tarihler)
 *   • Place   — projenin lokasyonu (lat/lng + structured address varsa)
 *
 * SALAMA BİLGİ YOK — sadece DB'de dolu olan alanlar JSON'a çıkar.
 * Eksik alan → o property hiç render edilmez.
 */
final class Project
{
    /**
     * @param array       $project Project::findBySlug() çıktısı (team_json decode'lu)
     * @param ?array      $owner   user satırı (id, name, slug) — null ise author gizli
     * @return array<int,array>    JSON-LD node listesi (her biri Renderer'a add edilir)
     */
    public static function build(array $project, ?array $owner = null): array
    {
        $slug = (string) ($project['slug'] ?? '');
        $url  = url('/proje/' . $slug);
        $siteUrl = rtrim((string) Setting::get('canonical_base', Config::get('APP_URL', ''), 'seo'), '/');

        $nodes = [];

        // ── Article node ──
        $article = self::buildArticle($project, $owner, $url, $siteUrl);
        if (!empty($article)) {
            $nodes[] = $article;
        }

        // ── Place node (sadece lat/lng VEYA structured address varsa) ──
        $place = self::buildPlace($project, $url);
        if ($place !== null) {
            $nodes[] = $place;
        }

        return $nodes;
    }

    private static function buildArticle(array $project, ?array $owner, string $url, string $siteUrl): array
    {
        $name        = (string) ($project['name'] ?? '');
        $subtitle    = trim((string) ($project['subtitle'] ?? ''));
        $description = trim(strip_tags((string) ($project['description'] ?? '')));
        $publishedAt = (string) ($project['published_at'] ?? $project['created_at'] ?? '');
        $updatedAt   = (string) ($project['updated_at'] ?? $publishedAt);
        $cover       = (string) ($project['cover_image'] ?? '');
        $tags        = is_array($project['tags_json'] ?? null) ? $project['tags_json'] : [];

        $node = [
            '@type'       => 'Article',
            '@id'         => $url . '#article',
            'headline'    => mb_substr($name, 0, 110),
            'mainEntityOfPage' => ['@id' => $url . '#webpage'],
            'url'         => $url,
            'inLanguage'  => (string) Setting::get('site_locale', Config::get('APP_LOCALE', 'tr'), 'general'),
        ];

        if ($subtitle !== '') {
            $node['description'] = mb_substr($subtitle, 0, 300);
        } elseif ($description !== '') {
            $node['description'] = mb_substr($description, 0, 300);
        }

        // Tarih alanları — sadece geçerli ISO formatına çevrilebilenler
        $iso = static function ($d): ?string {
            if (empty($d)) return null;
            $ts = strtotime((string) $d);
            return $ts ? date('c', $ts) : null;
        };
        if ($p = $iso($publishedAt)) { $node['datePublished'] = $p; }
        if ($m = $iso($updatedAt))   { $node['dateModified']  = $m; }

        // Image — cover + gallery (sadece doluysa)
        $images = [];
        if ($cover !== '') {
            $images[] = preg_match('#^https?://#i', $cover) ? $cover : rtrim($siteUrl, '/') . '/' . ltrim($cover, '/');
        }
        $gallery = is_array($project['gallery_json'] ?? null) ? $project['gallery_json'] : [];
        foreach ($gallery as $g) {
            $u = (string) ($g['url'] ?? '');
            if ($u !== '') {
                $images[] = preg_match('#^https?://#i', $u) ? $u : rtrim($siteUrl, '/') . '/' . ltrim($u, '/');
            }
        }
        $images = array_values(array_unique($images));
        if (!empty($images)) {
            $node['image'] = count($images) === 1 ? $images[0] : $images;
        }

        // Author — proje sahibi + team_json içindeki mimarlar
        $authors = [];
        if ($owner && !empty($owner['name'])) {
            $a = ['@type' => 'Person', 'name' => (string) $owner['name']];
            if (!empty($owner['slug'])) {
                $a['url'] = url('/yazar/' . $owner['slug']);
            }
            $authors[] = $a;
        }
        $team = is_array($project['team_json'] ?? null) ? $project['team_json'] : [];
        $architects = $team['architects'] ?? [];
        foreach ($architects as $arch) {
            $aName = trim((string) ($arch['name'] ?? ''));
            if ($aName === '') continue;
            $a = ['@type' => 'Person', 'name' => $aName];
            $aUrl = trim((string) ($arch['url'] ?? ''));
            if ($aUrl !== '' && preg_match('#^https?://#i', $aUrl)) {
                $a['url'] = $aUrl;
            }
            $authors[] = $a;
        }
        if (!empty($authors)) {
            $node['author'] = count($authors) === 1 ? $authors[0] : $authors;
        }

        // Contributor — mühendisler + danışmanlar
        $contributors = [];
        foreach (['engineers', 'consultants'] as $group) {
            foreach (($team[$group] ?? []) as $m) {
                $mName = trim((string) ($m['name'] ?? ''));
                if ($mName === '') continue;
                $c = ['@type' => 'Person', 'name' => $mName];
                $mUrl = trim((string) ($m['url'] ?? ''));
                if ($mUrl !== '' && preg_match('#^https?://#i', $mUrl)) {
                    $c['url'] = $mUrl;
                }
                $contributors[] = $c;
            }
        }
        if (!empty($contributors)) {
            $node['contributor'] = count($contributors) === 1 ? $contributors[0] : $contributors;
        }

        // Publisher — Organization @id referansı (Renderer::siteOrganization ile aynı sayfada)
        if ($siteUrl !== '') {
            $node['publisher'] = ['@id' => $siteUrl . '#org'];
        }

        // Keywords — tag'ler + yapı tipi
        $keywords = $tags;
        $bt = (string) ($project['building_type'] ?? '');
        if ($bt !== '' && isset(ProjectModel::BUILDING_TYPES[$bt])) {
            $keywords[] = mb_strtolower(ProjectModel::BUILDING_TYPES[$bt]);
        }
        $keywords = array_values(array_unique(array_filter($keywords)));
        if (!empty($keywords)) {
            $node['keywords'] = implode(', ', $keywords);
        }

        // about: Place (eğer lat/lng veya structured address varsa)
        if (self::buildPlace($project, '') !== null) {
            $node['about'] = ['@id' => $url . '#place'];
        }

        return $node;
    }

    private static function buildPlace(array $project, string $url): ?array
    {
        $lat = $project['lat'] ?? null;
        $lng = $project['lng'] ?? null;
        $locality = trim((string) ($project['address_locality'] ?? ''));
        $region   = trim((string) ($project['address_region']   ?? ''));
        $postal   = trim((string) ($project['postal_code']      ?? ''));
        $location = trim((string) ($project['location']         ?? ''));

        $hasGeo  = $lat !== null && $lng !== null && is_numeric($lat) && is_numeric($lng);
        $hasAddr = $locality !== '' || $region !== '' || $postal !== '';

        // Hiçbiri yoksa Place node oluşturma — SALAMA BİLGİ YOK
        if (!$hasGeo && !$hasAddr) {
            return null;
        }

        $node = [
            '@type' => 'Place',
            'name'  => (string) ($project['name'] ?? ''),
        ];
        if ($url !== '') {
            $node['@id'] = $url . '#place';
        }

        if ($hasGeo) {
            $node['geo'] = [
                '@type'     => 'GeoCoordinates',
                'latitude'  => (float) $lat,
                'longitude' => (float) $lng,
            ];
        }

        if ($hasAddr || $location !== '') {
            $addr = ['@type' => 'PostalAddress'];
            if ($locality !== '') $addr['addressLocality'] = $locality;
            if ($region   !== '') $addr['addressRegion']   = $region;
            if ($postal   !== '') $addr['postalCode']      = $postal;
            // Sadece structured alanlar boş ama serbest location string varsa,
            // onu fallback olarak addressLocality'e koyma — yanlış bilgi olur.
            // Bunun yerine structured varsa kullan, yoksa addressCountry sabit TR.
            if (count($addr) > 1) {
                $addr['addressCountry'] = 'TR';
                $node['address'] = $addr;
            }
        }

        return $node;
    }
}
