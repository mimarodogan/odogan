<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Setting;
use App\Models\User;
use App\Services\ProfileService;
use App\Services\Schema\Breadcrumb;
use App\Services\Schema\Person;
use App\Services\Schema\Renderer;
use App\Services\Schema\WebPage as SchemaWebPage;

/**
 * /hakkimda — site yazarı/kurucusu için niş manifestosu sayfası.
 *
 * principal_author_slug (Settings → organization) ile belirlenen
 * kanonik yazardan bio/eğitim/uzmanlık/projeler çeker. AboutPage +
 * Person + Organization + BreadcrumbList schema graph üretir.
 */
final class AboutController
{
    public function show(Request $req): Response
    {
        $principalSlug = trim((string) Setting::get('principal_author_slug', '', 'organization'));
        if ($principalSlug === '') {
            return Response::notFound();
        }
        $user = User::findBySlug($principalSlug);
        if ($user === null || ($user['status'] ?? '') !== 'active') {
            return Response::notFound();
        }
        $profile = ProfileService::decode($user['profile_json'] ?? null);
        $url     = absolute_url('/hakkimda');
        $authorUrl = absolute_url('/yazar/' . $user['slug']);

        // Yayındaki yazı + proje + sözlük girdisi sayıları — "üretim göstergesi"
        $stats = [
            'posts'    => 0,
            'projects' => 0,
            'glossary' => 0,
        ];
        try {
            $stats['posts'] = (int) Database::instance()->fetchColumn(
                'SELECT COUNT(*) FROM posts WHERE status = "published"'
            );
        } catch (\Throwable) { /* ignore */ }
        try {
            $stats['projects'] = (int) Database::instance()->fetchColumn(
                'SELECT COUNT(*) FROM projects WHERE status = "approved"'
            );
        } catch (\Throwable) { /* ignore */ }
        try {
            $stats['glossary'] = (int) Database::instance()->fetchColumn(
                'SELECT COUNT(*) FROM glossary WHERE is_active = 1'
            );
        } catch (\Throwable) { /* ignore */ }

        // Yazarın son 3 yazısı — "ne yazıyorum" örneği
        $recent = [];
        try {
            $recent = Database::instance()->fetchAll(
                'SELECT p.title, p.slug, p.published_at, p.excerpt, c.slug AS category_slug
                 FROM posts p
                 LEFT JOIN categories c ON c.id = p.category_id
                 WHERE p.user_id = :uid AND p.status = "published"
                 ORDER BY p.published_at DESC LIMIT 3',
                [':uid' => (int) $user['id']]
            );
        } catch (\Throwable) {
            $recent = [];
        }

        // Schema graph — AboutPage + Person (kanonik @id) + Breadcrumb
        $personId      = absolute_url('/yazar/' . $user['slug']) . '#person';
        $breadcrumbId  = $url . '#breadcrumb';

        $aboutPage = SchemaWebPage::build($url, 'Hakkımda — ' . $user['name'], [
            'type'           => 'AboutPage',
            'description'    => 'Mimar ve inşaat mühendisi Osman Doğan, yapı teknolojisi ve mimarlık üzerine notlar yazıyor.',
            'breadcrumb_id'  => $breadcrumbId,
            'main_entity_id' => $personId,
            'dateModified'   => $user['updated_at'] ?? null,
        ]);

        $person = Person::build($user, $profile, $authorUrl);
        if (is_array($person)) {
            $person['@id'] = $personId;
        }

        $breadcrumbList = Breadcrumb::build([
            ['name' => 'Ana Sayfa', 'url' => url('/')],
            ['name' => 'Hakkımda',  'url' => $url],
        ], $breadcrumbId);

        $jsonld = (new Renderer())
            ->add(Renderer::siteOrganization())
            ->add(Renderer::siteWebsite())
            ->add($aboutPage)
            ->add($person)
            ->add($breadcrumbList)
            ->emit();

        // Title sadece "Hakkımda" — seo_meta() layout'ta site adını otomatik ekler
        // ("Hakkımda — Osman Doğan"). Eski "Hakkımda — Osman Doğan — Osman Doğan"
        // duplikasyonu burayı kısaltarak çözüldü.
        return view('pages.about', [
            'title'         => 'Hakkımda',
            'description'   => mb_substr(trim((string) ($profile['bio'] ?? '')), 0, 200),
            'canonical'     => $url,
            'schema_jsonld' => $jsonld,
            'user'          => $user,
            'profile'       => $profile,
            'stats'         => $stats,
            'recent'        => $recent,
            'author_url'    => $authorUrl,
            'breadcrumbs'   => [
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Hakkımda',  'url' => $url],
            ],
        ]);
    }
}
