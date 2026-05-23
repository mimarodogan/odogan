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

        // Wikipedia-stili otomatik iç linkleme (max 2/sayfa).
        // Self-link engellenir; <a>, <code>, <pre>, başlıklar dokunulmaz.
        $item['definition'] = \App\Services\AutoLinkService::enrich(
            (string) ($item['definition'] ?? ''),
            'glossary',
            (int) $item['id'],
            ['category' => (string) ($item['category'] ?? '')]
        );

        $url = absolute_url('/sozluk/' . $slug);
        $related = Glossary::relatedByCategory(
            (int) $item['id'],
            (string) ($item['category'] ?? ''),
            6
        );

        // JSON-LD: DefinedTerm — sözlük girişleri için Google'ın anladığı schema
        $definedTerm = array_filter([
            '@context'      => 'https://schema.org',
            '@type'         => 'DefinedTerm',
            'name'          => $item['term'],
            'description'   => mb_substr(strip_tags((string) $item['definition']), 0, 300),
            'url'           => $url,
            'dateModified'  => !empty($item['updated_at']) ? date('c', strtotime((string) $item['updated_at'])) : null,
            'inDefinedTermSet' => [
                '@type' => 'DefinedTermSet',
                'name'  => 'Mimari Sözlük',
                'url'   => absolute_url('/sozluk'),
            ],
            'alternateName' => !empty($item['aliases'])
                ? array_values(array_filter(array_map('trim', explode(',', (string) $item['aliases']))))
                : null,
        ]);

        // FAQPage schema — AI üretici "Sıkça Sorulan Sorular" bölümünü H3 olarak
        // gömüyor. HTML body'den otomatik çıkarıp ek schema bloğu üretiriz.
        $faqEntities = self::extractFaqEntities((string) $item['definition']);
        $schemas = [$definedTerm];
        if ($faqEntities !== []) {
            $schemas[] = [
                '@context'   => 'https://schema.org',
                '@type'      => 'FAQPage',
                'mainEntity' => $faqEntities,
            ];
        }

        $jsonld = '';
        foreach ($schemas as $sch) {
            $jsonld .= '<script type="application/ld+json">'
                . json_encode($sch, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
                . '</script>' . "\n";
        }

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

    /**
     * Definition HTML'inden "Sıkça Sorulan Sorular" bölümünü ayrıştırır
     * ve FAQPage schema entities'e dönüştürür.
     *
     * AI ürettiği yapı:
     *   <h2>Sıkça Sorulan Sorular</h2>
     *   <h3>Soru?</h3>
     *   <p>Cevap…</p>
     *   <h3>İkinci soru?</h3>
     *   <p>İkinci cevap…</p>
     *
     * @return array<int,array<string,mixed>>
     */
    private static function extractFaqEntities(string $html): array
    {
        if (trim($html) === '') return [];

        // FAQ bölümünü H2 başlığından sonraki kısımdan bul (mümkün varyantlar)
        $faqHeadingPattern = '/<h2[^>]*>\s*(?:Sıkça\s+Sorulan\s+Sorular|SSS|S\.S\.S)\s*<\/h2>(.*?)(?=<h2[^>]*>|$)/siu';
        if (!preg_match($faqHeadingPattern, $html, $m)) {
            return [];
        }
        $body = $m[1];

        // İçindeki H3 (soru) + sonraki <p> (cevap) çiftlerini topla
        $entities = [];
        if (preg_match_all('/<h3[^>]*>(.+?)<\/h3>\s*(.*?)(?=<h3[^>]*>|$)/siu', $body, $pairs, PREG_SET_ORDER)) {
            foreach ($pairs as $pair) {
                $q = trim(strip_tags($pair[1]));
                // Cevap için ilk <p>…</p>'yi al, yoksa tüm metni temizle
                $aRaw = trim($pair[2]);
                $aPlain = '';
                if (preg_match('/<p[^>]*>(.+?)<\/p>/siu', $aRaw, $pm)) {
                    $aPlain = trim(strip_tags($pm[1]));
                } else {
                    $aPlain = trim(strip_tags($aRaw));
                }
                if ($q === '' || $aPlain === '') continue;
                $entities[] = [
                    '@type'          => 'Question',
                    'name'           => mb_substr($q, 0, 500),
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text'  => mb_substr($aPlain, 0, 2000),
                    ],
                ];
                if (count($entities) >= 6) break;
            }
        }
        return $entities;
    }
}
