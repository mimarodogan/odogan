<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use App\Services\Schema\Breadcrumb;
use App\Services\Schema\MapPlaces as SchemaMapPlaces;
use App\Services\Schema\Project as SchemaProject;
use App\Services\Schema\Renderer as SchemaRenderer;
use App\Services\Schema\WebPage as SchemaWebPage;

/**
 * Public Proje Portfolyosu — /projeler ve /proje/{slug}.
 *
 * Feature flag: project_portfolio_enabled
 */
final class ProjectController
{
    public function index(Request $req): Response
    {
        if (!Setting::get('project_portfolio_enabled', false, 'features')) {
            return Response::notFound();
        }
        $page = max(1, (int) $req->input('sayfa', 1));
        $perPage = 18;
        $offset = ($page - 1) * $perPage;
        $roleFilter = trim((string) $req->input('rol', ''));
        $typeFilter = trim((string) $req->input('tip', ''));

        $items = Project::published($perPage, $offset);
        $total = Project::countPublished();
        $totalPages = (int) max(1, ceil($total / $perPage));
        $featured = Project::featured(3);

        // Filter chips için building_type + rol özetleri + yıl aralığı (TÜM yayında projelerden)
        $allPublished = Project::published(500, 0);
        $roleCounts = [];
        $typeCounts = [];
        $years = [];
        foreach ($allPublished as $p) {
            $role = (string) ($p['role'] ?? 'arsitekt');
            $type = (string) ($p['building_type'] ?? 'diger');
            $roleCounts[$role] = ($roleCounts[$role] ?? 0) + 1;
            $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
            if (!empty($p['year_completed'])) {
                $years[] = (int) $p['year_completed'];
            }
        }
        // BUILDING_TYPES sırasına göre sırala (UI tutarlılığı)
        $orderedTypeCounts = [];
        foreach (array_keys(Project::BUILDING_TYPES) as $key) {
            if (!empty($typeCounts[$key])) {
                $orderedTypeCounts[$key] = $typeCounts[$key];
            }
        }
        $yearRange = !empty($years)
            ? ['min' => min($years), 'max' => max($years)]
            : ['min' => null, 'max' => null];

        // Server-side filtre (yapı tipi öncelikli, rol ikincil)
        if ($typeFilter !== '' && array_key_exists($typeFilter, Project::BUILDING_TYPES)) {
            $items = array_values(array_filter($items, static fn($p) => ($p['building_type'] ?? '') === $typeFilter));
        }
        if ($roleFilter !== '' && in_array($roleFilter, ['arsitekt','musavir','kontrol','danisman','arastirma','diger'], true)) {
            $items = array_values(array_filter($items, static fn($p) => ($p['role'] ?? '') === $roleFilter));
        }

        // Filtreli görünüm: SEO açısından duplicate content yaratmasın diye
        // canonical filtresiz ana URL'e işaret eder ve robots noindex,follow olur.
        $hasFilter = $typeFilter !== '' || $roleFilter !== '';
        $baseUrl   = '/projeler';
        $canonical = $hasFilter
            ? absolute_url($baseUrl)
            : ($page > 1
                ? absolute_url($baseUrl . '?sayfa=' . $page)
                : absolute_url($baseUrl));

        // Pagination prev/next — head <link rel=prev/next> için
        $prevUrl = (!$hasFilter && $page > 1)
            ? ($page === 2 ? url($baseUrl) : url($baseUrl . '?sayfa=' . ($page - 1)))
            : null;
        $nextUrl = (!$hasFilter && $page < $totalPages)
            ? url($baseUrl . '?sayfa=' . ($page + 1))
            : null;
        $prevAbs = $prevUrl ? (preg_match('#^https?://#i', $prevUrl) ? $prevUrl : absolute_url($prevUrl)) : null;
        $nextAbs = $nextUrl ? (preg_match('#^https?://#i', $nextUrl) ? $nextUrl : absolute_url($nextUrl)) : null;

        // Dinamik H1 — filtre aktifse "Konut Projeleri" gibi, değilse "Projeler"
        $h1Title = 'Projeler';
        if ($typeFilter !== '' && isset(Project::BUILDING_TYPES[$typeFilter])) {
            $h1Title = Project::BUILDING_TYPES[$typeFilter] . ' Projeleri';
        } elseif ($roleFilter !== '') {
            $roleLabels = [
                'arsitekt'  => 'Müellif Mimar',
                'musavir'   => 'Mimari Müşavir',
                'kontrol'   => 'Kontrol',
                'danisman'  => 'Danışman',
                'arastirma' => 'Araştırmacı',
                'diger'     => 'Diğer',
            ];
            $h1Title = ($roleLabels[$roleFilter] ?? ucfirst($roleFilter)) . ' Projeleri';
        }
        $titleSuffix = (!$hasFilter && $page > 1) ? ' · Sayfa ' . $page : '';

        // JSON-LD: Organization + WebSite + CollectionPage + ItemList + Breadcrumb
        $schemaJsonLd = (new SchemaRenderer())
            ->add(SchemaRenderer::siteOrganization())
            ->add(SchemaRenderer::siteWebsite())
            ->add(self::buildCollectionPage($canonical, 'Projeler',
                'Osman Doğan mimari proje portfolyosu — restorasyon, koruma ve mimari müşavirlik çalışmaları.'))
            ->add(self::buildItemListFromProjects($items, $canonical))
            ->add(Breadcrumb::build([
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Projeler',  'url' => absolute_url($baseUrl)],
            ]))
            ->emitCached('schema:projects:' . $page . ':' . $typeFilter . ':' . $roleFilter, 600);

        return view('pages.projects', [
            'title' => $h1Title . $titleSuffix,
            'description' => 'Osman Doğan mimari proje portfolyosu — restorasyon, koruma ve mimari müşavirlik çalışmaları.',
            'css_extra' => 'projects',
            'canonical' => $canonical,
            'robots' => $hasFilter ? 'noindex, follow' : null,
            'schema_jsonld' => $schemaJsonLd,
            'items' => $items,
            'featured' => $featured,
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
            'role_counts' => $roleCounts,
            'type_counts' => $orderedTypeCounts,
            'year_range' => $yearRange,
            'active_role' => $roleFilter,
            'active_type' => $typeFilter,
            'h1_title' => $h1Title,
            'pagination' => [
                'page'         => $page,
                'total_pages'  => $totalPages,
                'prev_url'     => $prevUrl,
                'next_url'     => $nextUrl,
                'prev_abs_url' => $prevAbs,
                'next_abs_url' => $nextAbs,
            ],
            'breadcrumbs' => [
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Projeler', 'url' => url('/projeler')],
            ],
        ]);
    }

    public function show(Request $req, array $args): Response
    {
        $slug = (string) ($args['slug'] ?? '');
        if (!Setting::get('project_portfolio_enabled', false, 'features')) {
            return Response::notFound();
        }
        $project = Project::findBySlug($slug);
        if (!$project || $project['status'] !== 'published') {
            return Response::notFound();
        }
        Project::bumpViews((int) $project['id']);
        $posts = Project::postsFor((int) $project['id']);
        $url = absolute_url('/proje/' . $project['slug']);

        // Proje sahibi (user_id varsa) — schema'da author olarak görünür
        $owner = null;
        if (!empty($project['user_id'])) {
            try {
                $owner = User::findById((int) $project['user_id']);
            } catch (\Throwable) { /* user tablosunda yoksa schema'ya author eklenmez */ }
        }

        // JSON-LD: Organization + WebSite + WebPage + Article + Place + Breadcrumb
        $renderer = (new SchemaRenderer())
            ->add(SchemaRenderer::siteOrganization())
            ->add(SchemaRenderer::siteWebsite())
            ->add(SchemaWebPage::build($url, (string) $project['name'], [
                'description' => (string) ($project['meta_description'] ?? $project['subtitle'] ?? ''),
            ]))
            ->add(Breadcrumb::build([
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Projeler',  'url' => url('/projeler')],
                ['name' => (string) $project['name'], 'url' => $url],
            ]));

        // Article + Place (varsa) — sallama yok, SchemaProject::build() boş alanları atlar
        foreach (SchemaProject::build($project, $owner) as $node) {
            $renderer->add($node);
        }
        $schemaJsonLd = $renderer->emitCached('schema:project:' . (int) $project['id'] . ':' . (string) ($project['updated_at'] ?? ''), 1800);

        return view('pages.project', [
            'title' => $project['name'],
            'description' => $project['meta_description'] ?? $project['subtitle'] ?? mb_substr(strip_tags((string) $project['description']), 0, 160),
            'css_extra' => 'projects',
            'canonical' => $url,
            'schema_jsonld' => $schemaJsonLd,
            'project' => $project,
            'posts' => $posts,
            'body_extra_js' => [
                asset('js/gallery-lightbox.js'),
            ],
            'breadcrumbs' => [
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Projeler', 'url' => url('/projeler')],
                ['name' => $project['name'], 'url' => url('/proje/' . $project['slug'])],
            ],
        ]);
    }

    /**
     * /harita — Leaflet map of geo-tagged projects.
     */
    public function map(Request $req): Response
    {
        if (!Setting::get('building_map_enabled', false, 'features')) {
            return Response::notFound();
        }
        // Cache bypass — admin yeni proje ekleyince anında görsün
        // (browser HTTP cache + proxy cache devre dışı)
        header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        $points = Project::geoTagged();

        // Yapı tipi ve rol bazlı özet — filter chip'leri için
        $roleCounts = [];
        $typeCounts = [];
        $years = [];
        foreach ($points as $p) {
            $role = (string) ($p['role'] ?? 'arsitekt');
            $type = (string) ($p['building_type'] ?? 'diger');
            $roleCounts[$role] = ($roleCounts[$role] ?? 0) + 1;
            $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
            if (!empty($p['year_completed'])) {
                $years[] = (int) $p['year_completed'];
            }
        }
        // BUILDING_TYPES sırasına göre normalize et
        $orderedTypeCounts = [];
        foreach (array_keys(Project::BUILDING_TYPES) as $key) {
            if (!empty($typeCounts[$key])) {
                $orderedTypeCounts[$key] = $typeCounts[$key];
            }
        }
        $yearRange = !empty($years)
            ? ['min' => min($years), 'max' => max($years)]
            : ['min' => null, 'max' => null];

        // Admin için tanı bilgisi — neden harita boş olabilir?
        $authUser = \App\Services\AuthService::user();
        $isAdmin = ($authUser['role'] ?? '') === \App\Models\User::ROLE_ADMIN;
        $mapStats = $isAdmin ? Project::mapStats() : null;

        // JSON-LD: Organization + WebSite + WebPage + Breadcrumb + MapPlaces (ItemList+Place)
        $mapUrl = absolute_url('/harita');
        $renderer = (new SchemaRenderer())
            ->add(SchemaRenderer::siteOrganization())
            ->add(SchemaRenderer::siteWebsite())
            ->add(SchemaWebPage::build($mapUrl, 'Yapı Haritası', [
                'description' => 'Türkiye genelinde mimari proje, restorasyon ve koruma çalışmalarının harita üzerinde dağılımı.',
            ]))
            ->add(Breadcrumb::build([
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Yapı Haritası', 'url' => $mapUrl],
            ]))
            ->add(SchemaMapPlaces::build($points));
        $schemaJsonLd = $renderer->emit();

        return view('pages.map', [
            'title' => 'Yapı Haritası',
            'description' => 'Türkiye genelinde tamamlanan mimari projeler, restorasyon ve koruma çalışmaları — interaktif harita görünümü.',
            'css_extra' => 'map',
            'canonical' => $mapUrl,
            'schema_jsonld' => $schemaJsonLd,
            'points' => $points,
            'role_counts' => $roleCounts,
            'type_counts' => $orderedTypeCounts,
            'year_range' => $yearRange,
            'is_admin' => $isAdmin,
            'map_stats' => $mapStats,
            'body_extra_js' => [
                // Leaflet (self-hosted — CSP/adblock immune), sonra building-map.js
                asset('vendor/leaflet/leaflet.js'),
                asset('js/building-map.js'),
            ],
            'breadcrumbs' => [
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Yapı Haritası', 'url' => url('/harita')],
            ],
        ]);
    }

    // ─────────────── Schema yardımcıları ───────────────

    private static function buildCollectionPage(string $url, string $name, string $description): array
    {
        return [
            '@type'      => 'CollectionPage',
            '@id'        => $url . '#webpage',
            'url'        => $url,
            'name'       => $name,
            'description'=> $description,
            'inLanguage' => (string) Setting::get('site_locale', 'tr', 'general'),
            'isPartOf'   => [
                '@id' => rtrim((string) Setting::get('canonical_base', \App\Core\Config::get('APP_URL', ''), 'seo'), '/') . '#website',
            ],
        ];
    }

    private static function buildItemListFromProjects(array $items, string $listUrl): array
    {
        $listItems = [];
        $i = 1;
        foreach ($items as $p) {
            $listItems[] = [
                '@type'    => 'ListItem',
                'position' => $i++,
                'url'      => absolute_url('/proje/' . $p['slug']),
                'name'     => (string) $p['name'],
            ];
        }
        return [
            '@type'           => 'ItemList',
            '@id'             => $listUrl . '#proje-listesi',
            'numberOfItems'   => count($listItems),
            'itemListElement' => $listItems,
        ];
    }
}
