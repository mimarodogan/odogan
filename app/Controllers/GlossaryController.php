<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Glossary;

/**
 * Public Glossary (Tier 7 — Architecture niche).
 *
 *  /sozluk         → alfabetik index
 *  /sozluk/{slug}  → tek terim
 */
final class GlossaryController
{
    public function index(Request $req): Response
    {
        if (!function_exists('feature') || !feature('glossary_enabled')) {
            return Response::notFound();
        }
        $q = trim((string) $req->input('q', ''));
        if ($q !== '') {
            $results = Glossary::search($q);
            return view('pages.glossary-search', [
                'title'       => 'Sözlük araması: ' . $q,
                'description' => '"' . $q . '" araması — Mimari Sözlük',
                'canonical'   => absolute_url('/sozluk?q=' . urlencode($q)),
                'query'       => $q,
                'results'     => $results,
                'robots'      => 'noindex, follow', // arama sayfalarını indexleme
                'breadcrumbs' => [
                    ['name' => 'Ana Sayfa', 'url' => url('/')],
                    ['name' => 'Sözlük', 'url' => url('/sozluk')],
                    ['name' => 'Arama: ' . $q, 'url' => url('/sozluk?q=' . urlencode($q))],
                ],
            ]);
        }

        $grouped = Glossary::groupedByLetter();
        $allTerms = Glossary::all(true);

        // JSON-LD: Organization + WebSite + WebPage + Breadcrumb + ItemList + DefinedTermSet
        $canonical = absolute_url('/sozluk');

        // ItemList — terim dizini (DefinedTermSet zaten zengin ama ItemList "list" sinyali verir).
        $listItems = [];
        $i = 1;
        foreach ($allTerms as $t) {
            $slug = (string) ($t['slug'] ?? '');
            if ($slug === '') { continue; }
            $listItems[] = [
                '@type'    => 'ListItem',
                'position' => $i++,
                'url'      => absolute_url('/sozluk/' . $slug),
                'name'     => (string) ($t['term'] ?? ''),
            ];
        }

        $renderer = (new \App\Services\Schema\Renderer())
            ->add(\App\Services\Schema\Renderer::siteOrganization())
            ->add(\App\Services\Schema\Renderer::siteWebsite())
            ->add(\App\Services\Schema\WebPage::build($canonical, 'Mimari Sözlük', [
                'type'          => 'CollectionPage',
                'description'   => 'Mimarlık, mühendislik ve şehir planlama terimleri.',
                'breadcrumb_id' => $canonical . '#breadcrumb',
            ]))
            ->add(\App\Services\Schema\Breadcrumb::build([
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Sözlük', 'url' => $canonical],
            ], $canonical . '#breadcrumb'))
            ->add([
                '@type'           => 'ItemList',
                '@id'             => $canonical . '#terim-listesi',
                'numberOfItems'   => count($listItems),
                'itemListElement' => $listItems,
            ])
            ->add(\App\Services\Schema\DefinedTermSet::build($allTerms));
        $schemaJsonLd = $renderer->emitCached('schema:sozluk:' . count($allTerms), 1800);

        return view('pages.glossary-index', [
            'title'       => 'Mimari Sözlük',
            'description' => 'Mimari ve mühendislik terimleri sözlüğü — Atelier referans kitaplığı.',
            'canonical'   => $canonical,
            'schema_jsonld' => $schemaJsonLd,
            'grouped'     => $grouped,
            'breadcrumbs' => [
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Sözlük', 'url' => url('/sozluk')],
            ],
        ]);
    }

    public function show(Request $req, array $args): Response
    {
        if (!function_exists('feature') || !feature('glossary_enabled')) {
            return Response::notFound();
        }
        $slug = (string) ($args['slug'] ?? '');
        $item = Glossary::findBySlug($slug);
        if (!$item) {
            return Response::notFound();
        }
        Glossary::bumpView((int) $item['id']);

        $url = absolute_url('/sozluk/' . $slug);
        $related = Glossary::relatedByCategory(
            (int) $item['id'],
            (string) ($item['category'] ?? ''),
            6
        );

        // JSON-LD: DefinedTerm — sözlük girişleri için Google'ın anladığı schema
        $jsonld = '<script type="application/ld+json">' . json_encode([
            '@context'      => 'https://schema.org',
            '@type'         => 'DefinedTerm',
            'name'          => $item['term'],
            'description'   => mb_substr(strip_tags((string) $item['definition']), 0, 300),
            'url'           => $url,
            'inDefinedTermSet' => [
                '@type' => 'DefinedTermSet',
                'name'  => 'Mimari Sözlük',
                'url'   => absolute_url('/sozluk'),
            ],
            'alternateName' => !empty($item['aliases'])
                ? array_values(array_filter(array_map('trim', explode(',', (string) $item['aliases']))))
                : null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';

        return view('pages.glossary-term', [
            'title'       => $item['term'] . ' · Sözlük',
            'description' => mb_substr(strip_tags((string) $item['definition']), 0, 200),
            'canonical'   => $url,
            'css_extra'   => 'post', // post.css yüklensin — blog yazısı stili
            'schema_jsonld' => $jsonld,
            'item'        => $item,
            'related'     => $related,
            'breadcrumbs' => [
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Sözlük', 'url' => url('/sozluk')],
                ['name' => $item['term'], 'url' => $url],
            ],
        ]);
    }
}
