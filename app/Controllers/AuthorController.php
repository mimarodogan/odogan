<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Models\User;
use App\Services\ProfileService;
use App\Services\Schema\Breadcrumb;
use App\Services\Schema\Person;
use App\Services\Schema\ProfilePage as SchemaProfilePage;
use App\Models\Setting;
use App\Services\Schema\Renderer;

final class AuthorController
{
    public function index(Request $req): Response
    {
        $authors = User::publicAuthors(50);
        foreach ($authors as &$a) {
            $a['profile'] = ProfileService::decode($a['profile_json'] ?? null);
        }
        unset($a);

        $canonical = absolute_url('/yazarlar');
        $description = Setting::get('site_name', 'Bu sitede', 'general') . '\'da içerik üreten uzman yazarlar.';

        // WebPage + ItemList schema — yazar dizini için.
        $listItems = [];
        $i = 1;
        foreach ($authors as $a) {
            $listItems[] = [
                '@type'    => 'ListItem',
                'position' => $i++,
                'url'      => absolute_url('/yazar/' . ($a['slug'] ?? '')),
                'name'     => (string) ($a['name'] ?? ''),
            ];
        }
        $schema = (new Renderer())
            ->add(Renderer::siteOrganization())
            ->add(Renderer::siteWebsite())
            ->add(\App\Services\Schema\WebPage::build($canonical, 'Yazarlar', [
                'type'          => 'CollectionPage',
                'description'   => $description,
                'breadcrumb_id' => $canonical . '#breadcrumb',
            ]))
            ->add(Breadcrumb::build([
                ['name' => 'Ana Sayfa', 'url' => absolute_url('/')],
                ['name' => 'Yazarlar',  'url' => $canonical],
            ], $canonical . '#breadcrumb'))
            ->add([
                '@type'           => 'ItemList',
                '@id'             => $canonical . '#yazar-listesi',
                'numberOfItems'   => count($listItems),
                'itemListElement' => $listItems,
            ]);

        return view('author.index', [
            'title' => 'Yazarlar',
            'description' => $description,
            'canonical' => $canonical,
            'schema_jsonld' => $schema->emitCached('schema:authors:index:' . count($authors), 1800),
            'authors' => $authors,
        ]);
    }

    public function show(Request $req, array $args): Response
    {
        $slug = (string) ($args['slug'] ?? '');
        $user = User::findBySlug($slug);
        if ($user === null || ($user['status'] ?? '') !== 'active') {
            return Response::notFound();
        }
        $profile = ProfileService::decode($user['profile_json'] ?? null);
        $url = absolute_url('/yazar/' . $user['slug']);

        // "Otomatik portfolyo" — yazarın yayındaki tüm içerikleri.
        $portfolio = Database::instance()->fetchAll(
            'SELECT p.id, p.title, p.slug, p.excerpt, p.published_at,
                    p.reading_minutes, c.slug AS category_slug, c.name AS category_name
             FROM posts p
             INNER JOIN categories c ON c.id = p.category_id
             WHERE p.user_id = :uid AND p.status = "published"
             ORDER BY p.published_at DESC
             LIMIT 50',
            [':uid' => (int) $user['id']]
        );

        // ProfilePage için son yayın tarihi (dateModified)
        $lastPublished = $portfolio ? ($portfolio[0]['published_at'] ?? null) : null;

        // Person node'una bio'yu user'a eşle (ProfilePage builder description için bio'yu okur)
        $userForSchema = $user + ['bio' => (string) ($profile['bio'] ?? '')];

        $schema = (new Renderer())
            ->add(Renderer::siteOrganization())
            ->add(Renderer::siteWebsite())
            ->add(SchemaProfilePage::build($url, $userForSchema, count($portfolio), $lastPublished))
            ->add(Person::build($user, $profile, $url))
            ->add(Breadcrumb::build([
                ['name' => 'Ana Sayfa', 'url' => absolute_url('/')],
                ['name' => 'Yazarlar', 'url' => absolute_url('/yazarlar')],
                ['name' => $user['name'], 'url' => $url],
            ], $url . '#breadcrumb'));

        return view('author.show', [
            'title' => $user['name'] . ' — Yazar Profili',
            'description' => $profile['bio'] ?: ($profile['headline'] ?: $user['name']),
            'canonical' => $url,
            'page_type' => 'profile',
            'css_extra' => 'author',
            'schema_jsonld' => $schema->emitCached(
                sprintf('schema:author:%d:%s', (int) $user['id'], (string) ($user['updated_at'] ?? '')),
                86400
            ),
            'breadcrumbs' => [
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Yazarlar', 'url' => url('/yazarlar')],
                ['name' => $user['name'], 'url' => $url],
            ],
            'author' => $user,
            'profile' => $profile,
            'portfolio' => $portfolio,
        ]);
    }
}
