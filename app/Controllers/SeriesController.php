<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Series;
use App\Services\Schema\Breadcrumb;
use App\Services\Schema\ItemList as SchemaItemList;
use App\Services\Schema\Renderer as SchemaRenderer;
use App\Services\Schema\WebPage as SchemaWebPage;

/**
 * /dizi/{slug} — public series detay sayfası.
 * Feature flag: series_enabled. Off ise 404.
 */
final class SeriesController
{
    public function show(Request $req, array $args): Response
    {
        if (!function_exists('feature') || !feature('series_enabled')) {
            return Response::notFound();
        }
        $slug = (string) ($args['slug'] ?? '');
        $series = Series::findBySlug($slug);
        if (!$series) {
            return Response::notFound();
        }
        $posts = Series::postsFor((int) $series['id'], true);
        $url = absolute_url('/dizi/' . $slug);

        $schema = (new SchemaRenderer())
            ->add(SchemaRenderer::siteOrganization())
            ->add(SchemaRenderer::siteWebsite())
            ->add(SchemaWebPage::build($url, $series['name'], [
                'type' => 'CollectionPage',
                'description' => (string) ($series['description'] ?? ''),
                'breadcrumb_id' => $url . '#breadcrumb',
            ]))
            ->add(Breadcrumb::build([
                ['name' => 'Ana Sayfa', 'url' => absolute_url('/')],
                ['name' => 'Diziler',   'url' => absolute_url('/diziler')],
                ['name' => $series['name'], 'url' => $url],
            ], $url . '#breadcrumb'))
            ->add(SchemaItemList::build(
                ['name' => $series['name'], 'slug' => 'dizi/' . $series['slug'], 'description' => ''],
                $posts,
                $url,
                1
            ));

        return view('pages.series', [
            'title' => $series['name'],
            'description' => mb_substr((string) ($series['description'] ?? ''), 0, 220) ?: ('"' . $series['name'] . '" dizisindeki yazılar'),
            'canonical' => $url,
            'page_type' => 'collection',
            'schema_jsonld' => $schema->emitCached(
                'schema:series:' . $series['id'] . ':' . date('Y-m-d-H'),
                3600
            ),
            'series' => $series,
            'posts' => $posts,
            'breadcrumbs' => [
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => $series['name'], 'url' => $url],
            ],
        ]);
    }

    /**
     * /diziler — tüm aktif seriler listesi (basit indeks).
     */
    public function index(Request $req): Response
    {
        if (!function_exists('feature') || !feature('series_enabled')) {
            return Response::notFound();
        }
        $list = Series::listActive(100);
        $canonical = absolute_url('/diziler');

        // WebPage + ItemList schema — Google'a "burası sıralı bir dizi koleksiyonu" işareti.
        $listItems = [];
        $i = 1;
        foreach ($list as $s) {
            $listItems[] = [
                '@type'    => 'ListItem',
                'position' => $i++,
                'url'      => absolute_url('/dizi/' . ($s['slug'] ?? '')),
                'name'     => (string) ($s['name'] ?? ''),
            ];
        }
        $schema = (new SchemaRenderer())
            ->add(SchemaRenderer::siteOrganization())
            ->add(SchemaRenderer::siteWebsite())
            ->add(SchemaWebPage::build($canonical, 'Diziler', [
                'type'          => 'CollectionPage',
                'description'   => 'Sıralı, bölümlere ayrılmış uzun-form yazılar.',
                'breadcrumb_id' => $canonical . '#breadcrumb',
            ]))
            ->add(Breadcrumb::build([
                ['name' => 'Ana Sayfa', 'url' => absolute_url('/')],
                ['name' => 'Diziler',   'url' => $canonical],
            ], $canonical . '#breadcrumb'))
            ->add([
                '@type'           => 'ItemList',
                '@id'             => $canonical . '#dizi-listesi',
                'numberOfItems'   => count($listItems),
                'itemListElement' => $listItems,
            ]);
        $schemaJsonLd = $schema->emitCached('schema:series:index:' . count($list), 1800);

        return view('pages.series-index', [
            'title' => 'Diziler',
            'description' => 'Sıralı, bölümlere ayrılmış uzun-form yazılar.',
            'canonical' => $canonical,
            'schema_jsonld' => $schemaJsonLd,
            'list' => $list,
            'breadcrumbs' => [
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Diziler', 'url' => url('/diziler')],
            ],
        ]);
    }
}
