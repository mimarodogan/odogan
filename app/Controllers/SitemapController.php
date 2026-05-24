<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Cache\CacheManager;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

/**
 * Sitemap Index Pattern — 4 alt sitemap + 1 index dosyası.
 *
 * Yapı:
 *   /sitemap.xml             → Sitemap Index (alt sitemap'leri listeler)
 *   /sitemap-pages.xml       → Home, categories, authors, series, glossary (URLs only)
 *   /sitemap-posts.xml       → Posts + cover image inline (image:image)
 *   /sitemap-projects.xml    → Projects + cover + gallery images inline
 *   /sitemap-images.xml      → İsteğe bağlı: tüm görseller (posts cover + projects cover + gallery)
 *
 * Faydaları:
 *   - 50K URL limitine takılmama (her sitemap ayrı sayım)
 *   - Görseller ait olduğu URL ile birlikte (Google Images doğru indexlesin)
 *   - Proje galerisi (gallery_json) tek tek imgeleri sitemap'e dahil ediyor
 *   - Lighter çıktı: posts sayfa açtığında sadece posts XML yükleniyor
 */
final class SitemapController
{
    /**
     * /sitemap.xml — Sitemap Index dosyası.
     * robots.txt'deki Sitemap directive'i hâlâ bu URL'i gösterir.
     */
    public function index(Request $req): Response
    {
        return $this->cachedXmlResponse('sitemap:index', function () {
            $now = date('c');
            $sitemaps = [
                ['loc' => url('/sitemap-pages.xml'),    'lastmod' => $now],
                ['loc' => url('/sitemap-posts.xml'),    'lastmod' => self::latestUpdated('posts', 'status = "published"')],
                ['loc' => url('/sitemap-projects.xml'), 'lastmod' => self::latestUpdated('projects', 'status = "published"')],
                ['loc' => url('/sitemap-images.xml'),   'lastmod' => $now],
            ];

            $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
            foreach ($sitemaps as $sm) {
                $xml .= '  <sitemap>';
                $xml .= '<loc>' . htmlspecialchars($sm['loc'], ENT_XML1) . '</loc>';
                if ($sm['lastmod']) {
                    $xml .= '<lastmod>' . $sm['lastmod'] . '</lastmod>';
                }
                $xml .= '</sitemap>' . "\n";
            }
            $xml .= '</sitemapindex>';
            return $xml;
        });
    }

    /**
     * /sitemap-pages.xml — Statik + listeleme sayfaları (görsel yok).
     */
    public function pages(Request $req): Response
    {
        return $this->cachedXmlResponse('sitemap:pages', function () {
            return self::wrapUrlset($this->buildPageUrls());
        });
    }

    private function buildPageUrls(): array
    {
        $urls = [];

        // Home
        $urls[] = self::node(url('/'), null, '1.0', 'daily');

        // Categories
        $cats = Database::instance()->fetchAll(
            'SELECT slug, updated_at FROM categories WHERE is_active = 1 ORDER BY position ASC'
        );
        foreach ($cats as $c) {
            $urls[] = self::node(url('/' . $c['slug']), $c['updated_at'] ?? null, '0.8', 'daily');
        }

        // Authors
        $authors = Database::instance()->fetchAll(
            "SELECT slug, updated_at FROM users WHERE status = 'active' AND role IN ('admin','editor','author')"
        );
        foreach ($authors as $u) {
            $urls[] = self::node(url('/yazar/' . $u['slug']), $u['updated_at'] ?? null, '0.5', 'weekly');
        }
        // Authors index
        $urls[] = self::node(url('/yazarlar'), null, '0.5', 'monthly');

        // Series (Tier 5)
        if (function_exists('feature') && feature('series_enabled')) {
            if (self::tableExists('series')) {
                $series = Database::instance()->fetchAll(
                    'SELECT slug, updated_at FROM series WHERE post_count > 0 ORDER BY updated_at DESC LIMIT 500'
                );
                foreach ($series as $s) {
                    $urls[] = self::node(url('/dizi/' . $s['slug']), $s['updated_at'] ?? null, '0.6', 'weekly');
                }
                $urls[] = self::node(url('/diziler'), null, '0.5', 'weekly');
            }
        }

        // Glossary
        if (self::tableExists('glossary')) {
            $urls[] = self::node(url('/sozluk'), null, '0.5', 'weekly');
            $terms = Database::instance()->fetchAll(
                'SELECT slug, updated_at FROM glossary WHERE is_active = 1 ORDER BY updated_at DESC LIMIT 1000'
            );
            foreach ($terms as $g) {
                $urls[] = self::node(url('/sozluk/' . $g['slug']), $g['updated_at'] ?? null, '0.4', 'monthly');
            }
        }

        // Legal docs (sözleşmeler — sadece aktif)
        if (self::tableExists('legal_documents')) {
            $legals = Database::instance()->fetchAll(
                'SELECT slug, updated_at FROM legal_documents WHERE is_active = 1'
            );
            foreach ($legals as $l) {
                $urls[] = self::node(url('/sozlesmeler/' . $l['slug']), $l['updated_at'] ?? null, '0.3', 'monthly');
            }
        }

        return $urls;
    }

    /**
     * /sitemap-posts.xml — Yazılar + kapak görseli inline.
     */
    public function posts(Request $req): Response
    {
        return $this->cachedXmlResponse('sitemap:posts', function () {
            return self::wrapUrlset($this->buildPostUrls());
        });
    }

    private function buildPostUrls(): array
    {
        $urls = [];
        // published_at filtresi: scheduled veya future-dated postlar sitemap'e
        // sızmasın. Hem NULL hem gelecek tarih hariç bırakılır.
        $posts = Database::instance()->fetchAll(
            'SELECT p.slug, p.title, p.cover_image, p.updated_at, c.slug AS cslug
             FROM posts p
             INNER JOIN categories c ON c.id = p.category_id
             WHERE p.status = "published"
               AND p.published_at IS NOT NULL
               AND p.published_at <= NOW()
             ORDER BY p.published_at DESC
             LIMIT 5000'
        );
        foreach ($posts as $p) {
            $images = [];
            if (!empty($p['cover_image'])) {
                $images[] = [
                    'loc'     => self::absoluteImageUrl((string) $p['cover_image']),
                    'caption' => (string) $p['title'],
                    'title'   => (string) $p['title'],
                ];
            }
            $urls[] = self::node(
                url('/' . $p['cslug'] . '/' . $p['slug']),
                $p['updated_at'] ?? null,
                '0.7',
                'weekly',
                $images
            );
        }
        return $urls;
    }

    /**
     * /sitemap-projects.xml — Projeler + cover + gallery_json'daki tüm imageler.
     * Her projenin galerisindeki imgeler tek tek <image:image> olarak dahil.
     */
    public function projects(Request $req): Response
    {
        return $this->cachedXmlResponse('sitemap:projects', function () {
            return self::wrapUrlset($this->buildProjectUrls());
        });
    }

    private function buildProjectUrls(): array
    {
        if (!function_exists('feature') || !feature('project_portfolio_enabled') || !self::tableExists('projects')) {
            return [];
        }

        $urls = [];

        // /projeler ve /harita listeleme sayfaları
        $urls[] = self::node(url('/projeler'), null, '0.7', 'weekly');
        if (feature('building_map_enabled')) {
            $urls[] = self::node(url('/harita'), null, '0.6', 'monthly');
        }

        $projects = Database::instance()->fetchAll(
            "SELECT slug, name, cover_image, gallery_json, updated_at
             FROM projects
             WHERE status = 'published'
             ORDER BY updated_at DESC
             LIMIT 1000"
        );
        foreach ($projects as $pr) {
            $images = [];
            // Cover image
            if (!empty($pr['cover_image'])) {
                $images[] = [
                    'loc'     => self::absoluteImageUrl((string) $pr['cover_image']),
                    'caption' => (string) $pr['name'],
                    'title'   => (string) $pr['name'],
                ];
            }
            // Gallery — gallery_json içindeki tüm imgeler
            $gallery = self::parseGallery($pr['gallery_json'] ?? null);
            foreach ($gallery as $g) {
                $images[] = [
                    'loc'     => self::absoluteImageUrl($g['url']),
                    'caption' => $g['caption'] !== '' ? $g['caption'] : (string) $pr['name'],
                    'title'   => (string) $pr['name'],
                ];
            }
            $urls[] = self::node(
                url('/proje/' . $pr['slug']),
                $pr['updated_at'] ?? null,
                '0.8',
                'monthly',
                $images
            );
        }

        return $urls;
    }

    /**
     * /sitemap-images.xml — DEDICATED görsel sitemap.
     * Tüm post cover + project cover + project gallery + author avatar imgeleri
     * tek dosyada toplanır (Google Images crawling için ekstra sinyal).
     *
     * Not: Bu sitemap'teki URL'ler diğer sitemap'lerde de var; Google duplicate
     * URL'leri sorunsuz handle ediyor — image enrichment olarak değerlendiriyor.
     */
    public function images(Request $req): Response
    {
        return $this->cachedXmlResponse('sitemap:images', function () {
            return self::wrapUrlset($this->buildImageUrls());
        });
    }

    private function buildImageUrls(): array
    {
        $urls = [];

        // Post cover'ları — published_at filtresi (scheduled/future hariç)
        $posts = Database::instance()->fetchAll(
            'SELECT p.slug, p.title, p.cover_image, p.updated_at, c.slug AS cslug
             FROM posts p
             INNER JOIN categories c ON c.id = p.category_id
             WHERE p.status = "published"
               AND p.published_at IS NOT NULL
               AND p.published_at <= NOW()
               AND p.cover_image IS NOT NULL
               AND p.cover_image != ""
             ORDER BY p.published_at DESC
             LIMIT 5000'
        );
        foreach ($posts as $p) {
            $urls[] = self::node(
                url('/' . $p['cslug'] . '/' . $p['slug']),
                $p['updated_at'] ?? null,
                '0.7',
                'monthly',
                [[
                    'loc'     => self::absoluteImageUrl((string) $p['cover_image']),
                    'caption' => (string) $p['title'],
                    'title'   => (string) $p['title'],
                ]]
            );
        }

        // Project cover + gallery
        if (function_exists('feature') && feature('project_portfolio_enabled') && self::tableExists('projects')) {
            $projects = Database::instance()->fetchAll(
                "SELECT slug, name, cover_image, gallery_json, updated_at
                 FROM projects
                 WHERE status = 'published'
                 ORDER BY updated_at DESC
                 LIMIT 1000"
            );
            foreach ($projects as $pr) {
                $images = [];
                if (!empty($pr['cover_image'])) {
                    $images[] = [
                        'loc'     => self::absoluteImageUrl((string) $pr['cover_image']),
                        'caption' => (string) $pr['name'],
                        'title'   => (string) $pr['name'],
                    ];
                }
                foreach (self::parseGallery($pr['gallery_json'] ?? null) as $g) {
                    $images[] = [
                        'loc'     => self::absoluteImageUrl($g['url']),
                        'caption' => $g['caption'] !== '' ? $g['caption'] : (string) $pr['name'],
                        'title'   => (string) $pr['name'],
                    ];
                }
                if ($images) {
                    $urls[] = self::node(
                        url('/proje/' . $pr['slug']),
                        $pr['updated_at'] ?? null,
                        '0.8',
                        'monthly',
                        $images
                    );
                }
            }
        }

        // Author avatar'ları
        $authors = Database::instance()->fetchAll(
            "SELECT slug, name, avatar, updated_at
             FROM users
             WHERE status = 'active' AND role IN ('admin','editor','author')
             AND avatar IS NOT NULL AND avatar != ''"
        );
        foreach ($authors as $u) {
            $urls[] = self::node(
                url('/yazar/' . $u['slug']),
                $u['updated_at'] ?? null,
                '0.5',
                'monthly',
                [[
                    'loc'     => self::absoluteImageUrl((string) $u['avatar']),
                    'caption' => (string) $u['name'],
                    'title'   => (string) $u['name'],
                ]]
            );
        }

        return $urls;
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

    /** gallery_json içindeki imageleri normalize eder. */
    private static function parseGallery(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $arr = json_decode($json, true);
        if (!is_array($arr)) {
            return [];
        }
        $out = [];
        foreach ($arr as $item) {
            // gallery_json formatı: ['url' => '...', 'caption' => '...'] veya direkt string URL
            if (is_string($item) && $item !== '') {
                $out[] = ['url' => $item, 'caption' => ''];
            } elseif (is_array($item) && !empty($item['url'])) {
                $out[] = [
                    'url'     => (string) $item['url'],
                    'caption' => (string) ($item['caption'] ?? $item['alt'] ?? ''),
                ];
            }
        }
        return $out;
    }

    /** Tablo varlık kontrolü — feature flag açıkken bile tablo yoksa skip. */
    private static function tableExists(string $table): bool
    {
        try {
            return (int) Database::instance()->fetchColumn(
                'SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = ?',
                [$table]
            ) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /** En son güncellenen kaydı bulur (sitemap-index lastmod için). */
    private static function latestUpdated(string $table, string $where = '1=1'): ?string
    {
        try {
            $ts = Database::instance()->fetchColumn(
                "SELECT MAX(updated_at) FROM {$table} WHERE {$where}"
            );
            return $ts ? date('c', strtotime((string) $ts)) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Göreceli image path'i absolute URL'e çevirir. */
    private static function absoluteImageUrl(string $path): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        return url($path);
    }

    /**
     * @param array<int,array{loc:string,caption:string,title?:string}> $images
     */
    private static function node(
        string $loc,
        ?string $modified,
        string $priority,
        string $changefreq,
        array $images = []
    ): string {
        $iso = $modified ? date('c', strtotime($modified)) : null;
        $s  = '<url>';
        $s .= '<loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>';
        if ($iso) {
            $s .= '<lastmod>' . $iso . '</lastmod>';
        }
        $s .= '<changefreq>' . $changefreq . '</changefreq>';
        $s .= '<priority>' . $priority . '</priority>';
        foreach ($images as $img) {
            if (empty($img['loc'])) {
                continue;
            }
            $s .= '<image:image>';
            $s .= '<image:loc>' . htmlspecialchars((string) $img['loc'], ENT_XML1) . '</image:loc>';
            if (!empty($img['caption'])) {
                $s .= '<image:caption>' . htmlspecialchars((string) $img['caption'], ENT_XML1) . '</image:caption>';
            }
            if (!empty($img['title'])) {
                $s .= '<image:title>' . htmlspecialchars((string) $img['title'], ENT_XML1) . '</image:title>';
            }
            $s .= '</image:image>';
        }
        $s .= '</url>' . "\n";
        return $s;
    }

    private static function wrapUrlset(array $urlNodes): string
    {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        $xml .= '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
        foreach ($urlNodes as $u) {
            $xml .= $u;
        }
        $xml .= '</urlset>';
        return $xml;
    }

    /**
     * Sitemap XML cache + response.
     * Tag-based cache invalidation: Post/Project transition'larda `'sitemap'`
     * tag'i invalidate ediliyor → tag düşünce remember() yeniden generate eder.
     * HTTP Cache-Control 1 saat — yeni içerik en geç 1 saatte propagate.
     */
    private function cachedXmlResponse(string $cacheKey, callable $generator): Response
    {
        $xml = CacheManager::driver()->remember(
            $cacheKey,
            3600,                // 1 saat TTL (HTTP cache ile uyumlu)
            $generator,
            ['sitemap']          // Tag — post/project transition'da invalidate
        );

        return new Response($xml, 200, [
            'Content-Type'  => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',  // 24h → 1h
            'X-Robots-Tag'  => 'noindex',               // Sitemap dosyasının kendisi index edilmesin
        ]);
    }

    private function xmlResponse(string $xml): Response
    {
        return new Response($xml, 200, [
            'Content-Type'  => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
            'X-Robots-Tag'  => 'noindex',
        ]);
    }
}
