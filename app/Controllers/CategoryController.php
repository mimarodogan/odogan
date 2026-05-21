<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Models\Post;
use App\Services\Schema\Breadcrumb;
use App\Services\Schema\ItemList as SchemaItemList;
use App\Services\Schema\Renderer;
use App\Services\Schema\WebPage as SchemaWebPage;

final class CategoryController
{
    /**
     * /kategoriler — tüm kategoriler + her birindeki yazı sayısı.
     */
    public function index(Request $req): Response
    {
        $categories = Category::allWithCounts(true);

        $canonical = absolute_url('/kategoriler');
        $schema = (new Renderer())
            ->add(Renderer::siteOrganization())
            ->add(Renderer::siteWebsite())
            ->add(SchemaWebPage::build($canonical, 'Kategoriler', [
                'description' => 'Tüm içerik kategorileri ve yazı sayıları.',
            ]))
            ->add(Breadcrumb::build([
                ['name' => 'Ana Sayfa', 'url' => absolute_url('/')],
                ['name' => 'Kategoriler', 'url' => $canonical],
            ]));
        $schemaJson = $schema->emitCached('schema:categories:index', 21600);

        return view('pages.categories', [
            'title' => 'Kategoriler',
            'description' => 'Tüm içerik kategorileri ve her birindeki yazı sayısı.',
            'canonical' => $canonical,
            'schema_jsonld' => $schemaJson,
            'breadcrumbs' => [
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Kategoriler', 'url' => url('/kategoriler')],
            ],
            'categories' => $categories,
        ]);
    }

    public function show(Request $req, array $args): Response
    {
        $slug = (string) ($args['category'] ?? '');
        if ($slug === '') {
            return Response::notFound();
        }
        $category = Category::findBySlug($slug);
        if ($category === null || ($category['is_active'] ?? 1) == 0) {
            return Response::notFound();
        }

        // Pagination — varsayılan 12 / sayfa, ?sayfa=N ile gez
        $perPage = (int) \App\Models\Setting::get('posts_per_page', 12, 'content');
        if ($perPage < 4)  { $perPage = 4;  }
        if ($perPage > 48) { $perPage = 48; }
        $page = max(1, (int) $req->input('sayfa', 1));

        $result = Post::listByCategoryPaged((int) $category['id'], $page, $perPage);
        $posts  = $result['posts'];
        $page   = $result['page'];
        $totalPages = $result['total_pages'];

        $baseUrl    = '/' . $category['slug'];
        $canonical  = $page > 1
            ? absolute_url($baseUrl . '?sayfa=' . $page)
            : absolute_url($baseUrl);
        $prevUrl    = $page > 1
            ? ($page === 2 ? url($baseUrl) : url($baseUrl . '?sayfa=' . ($page - 1)))
            : null;
        $nextUrl    = $page < $totalPages
            ? url($baseUrl . '?sayfa=' . ($page + 1))
            : null;
        $prevAbs    = $prevUrl ? (preg_match('#^https?://#i', $prevUrl) ? $prevUrl : absolute_url($prevUrl)) : null;
        $nextAbs    = $nextUrl ? (preg_match('#^https?://#i', $nextUrl) ? $nextUrl : absolute_url($nextUrl)) : null;

        $startOffset = ($page - 1) * $perPage + 1;

        // CollectionPage zaten WebPage'in alt-tipi → ItemList build içinde WebPage olarak çıkar.
        // Yine de WebPage olarak da bir node ekleyebiliriz ama duplikasyon olur — ItemList yeterli.
        $schema = (new Renderer())
            ->add(Renderer::siteOrganization())
            ->add(Renderer::siteWebsite())
            ->add(SchemaItemList::build($category, $posts, $canonical, $startOffset))
            ->add(Breadcrumb::build([
                ['name' => 'Ana Sayfa', 'url' => absolute_url('/')],
                ['name' => $category['name'], 'url' => absolute_url($baseUrl)],
            ]));
        $cacheKey = sprintf(
            'schema:cat:%d:p%d:%s',
            (int) $category['id'],
            $page,
            (string) ($category['updated_at'] ?? '0')
        );
        $schemaJson = $schema->emitCached($cacheKey, 21600);  // 6 saat

        $titleSuffix = $page > 1 ? ' · Sayfa ' . $page : '';

        return view('pages.category', [
            'title' => ($category['meta_title'] ?: $category['name']) . $titleSuffix,
            'description' => $category['meta_description'] ?: $category['description'] ?: $category['name'],
            'canonical' => $canonical,
            'schema_jsonld' => $schemaJson,
            'breadcrumbs' => [
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => $category['name'], 'url' => url($baseUrl)],
            ],
            'category' => $category,
            'posts' => $posts,
            'pagination' => [
                'page'         => $page,
                'total_pages'  => $totalPages,
                'per_page'     => $perPage,
                'total'        => $result['total'],
                'prev_url'     => $prevUrl,
                'next_url'     => $nextUrl,
                'prev_abs_url' => $prevAbs,  // head <link rel=prev> için
                'next_abs_url' => $nextAbs,
            ],
        ]);
    }
}
