<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Cache\CacheManager;
use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Models\Post;
use App\Services\Schema\Renderer as SchemaRenderer;
use App\Services\Schema\WebPage as SchemaWebPage;
use App\Services\ViewCounter;

final class HomeController
{
    public function index(Request $req): Response
    {
        $cache = CacheManager::driver();

        $trending = $cache->remember('home:trending', 300, fn() => Post::trending(6, 30), ['home']);
        $mostRead = $cache->remember('home:most-read', 600, fn() => self::mostRead(8), ['home']);
        $mostCommented = $cache->remember('home:most-commented', 600, fn() => Post::mostCommented(6), ['home']);
        // 12'lik havuz: featured (1) + Editörün Seçimi (≤4) çıkınca "Yeni Yayınlar" için 6 garanti kalır.
        $recent = $cache->remember('home:recent', 120, fn() => Post::recent(12), ['home']);
        $showcase = $cache->remember('home:showcase', 600, fn() => self::categoryShowcase(4, 4), ['home']);

        // Yeni eklenen 3 proje — anasayfa portfolyo cameo'su
        $recentProjects = [];
        if (function_exists('feature') && feature('project_portfolio_enabled')) {
            $recentProjects = $cache->remember(
                'home:recent-projects',
                300,
                fn() => \App\Models\Project::published(4, 0),
                ['home', 'projects']
            );
        }

        // Featured = en yeni yayında olan ilk içerik. Listeden çıkar ki tekrar görünmesin.
        $featured = $recent[0] ?? null;
        $recent = array_slice($recent, 1);

        // Editörün Seçimi (feature flag korumalı). Hero featured ile çakışmaması için
        // hero post id'sini exclude eder, sonra recent listesinden de filtrelenir.
        $editorsPicks = [];
        if (function_exists('feature') && feature('editors_pick_enabled')) {
            $heroId = isset($featured['id']) ? (int) $featured['id'] : null;
            $editorsPicks = $cache->remember(
                'home:editors-picks:' . ($heroId ?? 0),
                300,
                fn() => Post::editorsPicks(4, $heroId),
                ['home']
            );
            if ($editorsPicks) {
                $pickIds = array_map(static fn($p) => (int) $p['id'], $editorsPicks);
                $recent = array_values(array_filter(
                    $recent,
                    static fn($r) => !in_array((int) $r['id'], $pickIds, true)
                ));
            }
        }

        // "Yeni Yayınlar" bloğu tam 6 yazı gösterir (Trend de Post::trending(6,…) ile 6).
        $recent = array_slice($recent, 0, 6);

        // Layer live Redis counters on top so trending reacts in near-real-time.
        foreach ($trending as &$p) {
            $p['live_views'] = (int) $p['view_count'] + ViewCounter::liveCount((int) $p['id']);
        }
        unset($p);
        usort($trending, fn($a, $b) => $b['live_views'] <=> $a['live_views']);

        // JSON-LD: Organization + WebSite (SearchAction) + WebPage + (varsa) ana yazar Person
        $homeUrl = absolute_url('/');
        $siteName = (string) \App\Models\Setting::get('site_name', \App\Core\Config::get('APP_NAME', 'Otorite Yayin'));

        // Ana yazar (entity home) — principal_author_slug ayarlıysa kanonik Person
        // node'u ana sayfaya eklenir, WebPage.about bu kişiye bağlanır ve
        // Organization.founder aynı @id'yi referanslar → tek varlıkta birleşir (E-E-A-T).
        $principalPerson = null;
        $aboutId = null;
        $principalCacheTag = '';
        $principalSlug = trim((string) \App\Models\Setting::get('principal_author_slug', '', 'organization'));
        if ($principalSlug !== '') {
            $principalUser = \App\Models\User::findBySlug($principalSlug);
            if ($principalUser !== null && ($principalUser['status'] ?? '') === 'active') {
                $principalProfile = \App\Services\ProfileService::decode($principalUser['profile_json'] ?? null);
                $authorUrl = absolute_url('/yazar/' . $principalSlug);
                $principalPerson = \App\Services\Schema\Person::build($principalUser, $principalProfile, $authorUrl);
                $aboutId = $authorUrl . '#person';
                $principalCacheTag = (string) ($principalUser['updated_at'] ?? '');
            }
        }

        $schema = (new SchemaRenderer())
            ->add(SchemaRenderer::siteOrganization())
            ->add(SchemaRenderer::siteWebsite())
            ->add(SchemaWebPage::build($homeUrl, $siteName, array_filter([
                'description' => (string) \App\Models\Setting::get('site_description', ''),
                'about_id'    => $aboutId,
            ])))
            ->add($principalPerson)
            ->emitCached('schema:home:' . $principalCacheTag . ':' . date('Y-m-d-H'), 3600);  // saatlik bucket

        // Ana sayfa OG image: featured yazının kapağı → yoksa site default_og_image
        $_homeOgImage = !empty($featured['cover_image'])
            ? absolute_url((string) $featured['cover_image'])
            : null;

        $resp = view('pages.home', [
            'title' => null, // homepage uses site name
            'description' => (string) \App\Models\Setting::get('site_description', ''),
            'image' => $_homeOgImage,   // og:image + twitter:image
            'canonical' => absolute_url('/'),
            'schema_jsonld' => $schema,
            'featured' => $featured,
            'editors_picks' => $editorsPicks,
            'trending' => $trending,
            'most_read' => $mostRead,
            'most_commented' => $mostCommented,
            'recent' => $recent,
            'showcase' => $showcase,
            'recent_projects' => $recentProjects,
        ]);
        return $resp->header('Cache-Control', 'public, max-age=60, s-maxage=300');
    }

    /**
     * /kaydedilenler — LocalStorage tabanlı kaydetme listesi.
     * Server tarafı sadece sayfa kabuğunu render eder; içerik tamamen JS ile dolar.
     * Feature: save_post_enabled → off ise 404.
     */
    public function saved(Request $req): Response
    {
        if (!function_exists('feature') || !feature('save_post_enabled')) {
            return Response::notFound();
        }
        // Sadece üyelere — guest çerez temizleyince kaybeder, beklenti kötü olur.
        if (!\App\Services\AuthService::check()) {
            flash('info', 'Yazıları kaydetmek için giriş yap.');
            return Response::redirect(url('/giris?next=' . urlencode('/kaydedilenler')));
        }
        return view('pages.saved', [
            'title'       => 'Kaydedilen Yazılar',
            'description' => 'Daha sonra okumak üzere kaydettiğin yazılar.',
            'canonical'   => absolute_url('/kaydedilenler'),
            'robots'      => 'noindex, nofollow',
            'page_type'   => 'collection',
            'body_extra_js' => [
                \App\Services\AssetMinifier::asset('assets/js/save-post.js'),
            ],
            'breadcrumbs' => [
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Kaydedilenler', 'url' => url('/kaydedilenler')],
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    private static function mostRead(int $limit): array
    {
        return \App\Core\Database::instance()->fetchAll(
            'SELECT p.id, p.title, p.slug, p.view_count, p.published_at,
                    c.name AS category_name, c.slug AS category_slug
             FROM posts p
             INNER JOIN categories c ON c.id = p.category_id
             WHERE p.status = "published"
             ORDER BY p.view_count DESC
             LIMIT ' . max(1, $limit)
        );
    }

    /**
     * @return array<int,array{category:array,posts:array}>
     */
    private static function categoryShowcase(int $categoryLimit, int $perCategory): array
    {
        $cats = Category::all(true);
        $cats = array_slice($cats, 0, max(1, $categoryLimit));
        $out = [];
        foreach ($cats as $c) {
            $posts = Post::listPublishedInCategory((int) $c['id'], $perCategory);
            if ($posts) {
                $out[] = ['category' => $c, 'posts' => $posts];
            }
        }
        return $out;
    }
}
