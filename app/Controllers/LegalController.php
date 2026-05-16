<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\LegalDocument;

/**
 * /sozlesmeler/{slug} — public sözleşme görüntüleyici (Tier 6).
 */
final class LegalController
{
    public function show(Request $req, array $args): Response
    {
        $slug = (string) ($args['slug'] ?? '');
        $doc = LegalDocument::findBySlug($slug);
        if (!$doc) {
            return Response::notFound();
        }
        $url = absolute_url('/sozlesmeler/' . $slug);
        return view('pages.legal', [
            'title'       => $doc['title'],
            'description' => 'Yasal sözleşme — ' . $doc['title'],
            'canonical'   => $url,
            'robots'      => 'noindex, follow', // hukuki belgeler indexlenmesin
            'doc'         => $doc,
            'breadcrumbs' => [
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Sözleşmeler', 'url' => url('/sozlesmeler')],
                ['name' => $doc['title'], 'url' => $url],
            ],
        ]);
    }

    /**
     * /sozlesmeler — tüm aktif sözleşmelerin listesi.
     */
    public function index(Request $req): Response
    {
        return view('pages.legal-index', [
            'title' => 'Sözleşmeler',
            'description' => 'Üyelik sözleşmesi, yazar sözleşmesi, gizlilik politikası ve kullanım koşulları.',
            'canonical' => absolute_url('/sozlesmeler'),
            'robots'    => 'noindex, follow', // hukuki belgeler indexlenmesin
            'list'  => LegalDocument::all(),
            'breadcrumbs' => [
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Sözleşmeler', 'url' => url('/sozlesmeler')],
            ],
        ]);
    }
}
