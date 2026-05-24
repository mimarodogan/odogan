<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Glossary;
use App\Services\Schema\Breadcrumb;
use App\Services\Schema\Renderer;
use App\Services\Schema\WebPage as SchemaWebPage;

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

        // G1: Sözlük kategori filtresi kaldırıldı, alfabetik dizilim tek görünüm.
        $grouped   = Glossary::groupedByLetter();
        $allTerms  = Glossary::all(true);
        $canonical = absolute_url('/sozluk');

        // JSON-LD: Organization + WebSite + WebPage + Breadcrumb + ItemList + DefinedTermSet

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

        $breadcrumbs = [
            ['name' => 'Ana Sayfa', 'url' => url('/')],
            ['name' => 'Sözlük',    'url' => url('/sozluk')],
        ];

        return view('pages.glossary-index', [
            'title'           => 'Mimari Sözlük',
            'description'     => 'Mimari ve mühendislik terimleri sözlüğü — Atelier referans kitaplığı.',
            'canonical'       => $canonical,
            'schema_jsonld'   => $schemaJsonLd,
            'grouped'         => $grouped,
            'breadcrumbs'     => $breadcrumbs,
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

        // Bu terimin geçtiği son yayınlanmış yazılar — geri linkleme widget'ı
        $postsWithTerm = Glossary::postsContainingTerm(
            (string) $item['term'],
            (string) ($item['aliases'] ?? ''),
            5
        );

        // Schema graph — Renderer pattern (post sayfasıyla aynı yaklaşım).
        // DefinedTerm + WebPage wrapper + BreadcrumbList + FAQPage.
        //
        // Validator uyarısı düzeltmesi: DefinedTerm üzerinde "dateModified"
        // standart bir property değil (yalnızca CreativeWork ailesinde var).
        // Bu yüzden dateModified'i SARMALAYICI WebPage'e taşıdık. WebPage
        // mainEntity → DefinedTerm'e @id ile bağlanır.
        $termId       = $url . '#term';
        $breadcrumbId = $url . '#breadcrumb';

        $definedTerm = array_filter([
            '@type'            => 'DefinedTerm',
            '@id'              => $termId,
            'name'             => $item['term'],
            'description'      => mb_substr(strip_tags((string) $item['definition']), 0, 300),
            'url'              => $url,
            'inDefinedTermSet' => [
                '@type' => 'DefinedTermSet',
                'name'  => 'Mimari Sözlük',
                'url'   => absolute_url('/sozluk'),
            ],
            'alternateName'    => !empty($item['aliases'])
                ? array_values(array_filter(array_map('trim', explode(',', (string) $item['aliases']))))
                : null,
        ]);

        // FAQ entities — faq_json'dan üret. Legacy fallback: HTML parse.
        $faqItems = [];
        if (!empty($item['faq_json'])) {
            $faqItems = \App\Services\FaqService::decode((string) $item['faq_json']);
        }
        if ($faqItems === []) {
            $faqItems = self::extractFaqFromHtml((string) $item['definition']);
        }
        $faqPage = null;
        if ($faqItems !== []) {
            $mainEntity = [];
            foreach ($faqItems as $f) {
                $mainEntity[] = [
                    '@type'          => 'Question',
                    'name'           => mb_substr((string) $f['q'], 0, 500),
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text'  => mb_substr((string) $f['a'], 0, 2000),
                    ],
                ];
            }
            $faqPage = [
                '@type'      => 'FAQPage',
                '@id'        => $url . '#faq',
                'mainEntity' => $mainEntity,
            ];
        }

        // WebPage wrapper — sayfa metadata + dateModified BURADA
        $webPage = SchemaWebPage::build($url, (string) $item['term'], [
            'type'           => 'WebPage',
            'description'    => mb_substr(strip_tags((string) $item['definition']), 0, 200),
            'dateModified'   => $item['updated_at'] ?? null,
            'breadcrumb_id'  => $breadcrumbId,
            'main_entity_id' => $termId,
        ]);

        $breadcrumbList = Breadcrumb::build(
            [
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Sözlük',    'url' => url('/sozluk')],
                ['name' => $item['term'], 'url' => $url],
            ],
            $breadcrumbId
        );

        $jsonld = (new Renderer())
            ->add(Renderer::siteOrganization())
            ->add(Renderer::siteWebsite())
            ->add($webPage)
            ->add($definedTerm)
            ->add($breadcrumbList)
            ->add($faqPage)
            ->emit();

        return view('pages.glossary-term', [
            'title'            => $item['term'] . ' · Sözlük',
            'description'      => mb_substr(strip_tags((string) $item['definition']), 0, 200),
            'canonical'        => $url,
            'css_extra'        => 'post', // post.css yüklensin — blog yazısı stili
            'schema_jsonld'    => $jsonld,
            'item'             => $item,
            'related'          => $related,
            'posts_with_term'  => $postsWithTerm,
            'breadcrumbs'      => [
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Sözlük', 'url' => url('/sozluk')],
                ['name' => $item['term'], 'url' => $url],
            ],
        ]);
    }

    /**
     * LEGACY: eski sözlük girdilerinde FAQ definition HTML'i içinde
     * "Sıkça Sorulan Sorular" H2 olarak gömülü olabilir. Yeni girdilerde
     * faq_json kolonu kullanılır. Bu metot sadece geriye dönük uyum için.
     *
     * @return array<int,array{q:string,a:string}>
     */
    private static function extractFaqFromHtml(string $html): array
    {
        if (trim($html) === '') return [];

        $faqHeadingPattern = '/<h2[^>]*>\s*(?:Sıkça\s+Sorulan\s+Sorular|SSS|S\.S\.S)\s*<\/h2>(.*?)(?=<h2[^>]*>|$)/siu';
        if (!preg_match($faqHeadingPattern, $html, $m)) {
            return [];
        }
        $body = $m[1];

        $items = [];
        if (preg_match_all('/<h3[^>]*>(.+?)<\/h3>\s*(.*?)(?=<h3[^>]*>|$)/siu', $body, $pairs, PREG_SET_ORDER)) {
            foreach ($pairs as $pair) {
                $q = trim(strip_tags($pair[1]));
                $aRaw = trim($pair[2]);
                $aPlain = '';
                if (preg_match('/<p[^>]*>(.+?)<\/p>/siu', $aRaw, $pm)) {
                    $aPlain = trim(strip_tags($pm[1]));
                } else {
                    $aPlain = trim(strip_tags($aRaw));
                }
                if ($q === '' || $aPlain === '') continue;
                $items[] = ['q' => $q, 'a' => $aPlain];
                if (count($items) >= 6) break;
            }
        }
        return $items;
    }
}
