<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Comment;
use App\Models\MediaResolver;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Services\AuthService;
use App\Services\FaqService;
use App\Services\MarkdownService;
use App\Services\ProfileService;
use App\Services\Schema\Article;
use App\Services\Schema\Breadcrumb;
use App\Services\Schema\FaqPage;
use App\Services\Schema\ImageGallery as SchemaImageGallery;
use App\Services\Schema\Person;
use App\Services\Schema\Renderer;
use App\Services\Schema\WebPage as SchemaWebPage;
use App\Services\ViewCounter;

final class PostController
{
    public function show(Request $req, array $args): Response
    {
        $categorySlug = (string) ($args['category'] ?? '');
        $postSlug = (string) ($args['slug'] ?? '');
        if ($categorySlug === '' || $postSlug === '') {
            return Response::notFound();
        }
        $post = Post::findBySlugInCategory($categorySlug, $postSlug);
        if ($post === null) {
            return Response::notFound();
        }
        if ($post['status'] !== Post::STATUS_PUBLISHED || $post['category_slug'] !== $categorySlug) {
            return Response::notFound();
        }
        ViewCounter::record((int) $post['id']);

        $author = User::findById((int) $post['user_id']);
        $profile = ProfileService::decode($author['profile_json'] ?? null);
        $cover = MediaResolver::fromPath($post['cover_image'] ?? null);
        // Per-post override: if og_image set, use it; else fall back to cover.
        $ogMedia = !empty($post['og_image'])
            ? MediaResolver::fromPath($post['og_image'])
            : $cover;
        $body = MarkdownService::render($post);
        $faq = FaqService::decode($post['faq_json'] ?? null);
        $comments = Comment::approvedForPost((int) $post['id']);
        $tags = Tag::listForPost((int) $post['id']);
        $related = Post::relatedSmart($post, 4);
        $trending = Post::trending(5, 30);

        // Aynı kategoride önceki/sonraki yayında yazı — feature flag korumalı.
        $prevNext = ['prev' => null, 'next' => null];
        if (function_exists('feature') && feature('prev_next_nav_enabled') && !empty($post['published_at'])) {
            $prevNext = Post::prevNextInCategory(
                (int) $post['id'],
                (int) $post['category_id'],
                (string) $post['published_at']
            );
        }

        // Series (dizi) navigation — feature aktif ve yazı bir seriye atanmışsa.
        $seriesNav = null;
        $seriesInfo = null;
        if (function_exists('feature') && feature('series_enabled') && !empty($post['series_id'])) {
            $seriesInfo = \App\Models\Series::findById((int) $post['series_id']);
            if ($seriesInfo) {
                $seriesNav = \App\Models\Series::navFor((int) $post['id'], (int) $post['series_id']);
            }
        }

        $url = absolute_url('/' . $categorySlug . '/' . $postSlug);
        $authorUrl = absolute_url('/yazar/' . $author['slug']);

        // Article'a comment_count + view_count zenginleştirme verisi geçir.
        // Bu iki kolon zaten posts tablosunda mevcut, mtime-bazlı update'le güncellenir.
        $post['view_count']    = (int) ($post['view_count']    ?? 0);
        $post['comment_count'] = (int) ($post['comment_count'] ?? count($comments));

        $schema = (new Renderer())
            ->add(Renderer::siteOrganization())
            ->add(Renderer::siteWebsite())
            ->add(SchemaWebPage::build($url, (string) $post['title'], [
                'type'             => 'WebPage',
                'description'      => $post['meta_description'] ?: ($post['excerpt'] ?? ''),
                'datePublished'    => $post['published_at'] ?? null,
                'dateModified'     => $post['updated_at']  ?? null,
                'primary_image_id' => $cover ? $url . '#primaryimage' : null,
                'breadcrumb_id'    => $url . '#breadcrumb',
                'main_entity_id'   => $url . '#article',
            ]))
            ->add(Person::build($author, $profile, $authorUrl))
            ->add(Article::build($post, $author, $profile, $cover))
            ->add(FaqPage::build($faq))
            ->add(SchemaImageGallery::build($body, $url, (string) $post['title']))
            ->add(self::buildBreadcrumbWithId($post, $categorySlug, $url));

        $resp = view('pages.post', [
            'title' => $post['meta_title'] ?: $post['title'],
            'description' => $post['meta_description'] ?: ($post['excerpt'] ?: MarkdownService::plain((string) $post['body'])),
            'canonical' => $url,
            'page_type' => 'article',
            'seo' => [
                'title' => $post['meta_title'] ?: $post['title'],
                'description' => $post['meta_description'] ?: ($post['excerpt'] ?: ''),
                'url' => $url,
                'image' => og_image_for_post($post, $ogMedia) ?? '',
                'type' => 'article',
                'published_time' => $post['published_at'] ? date('c', strtotime((string) $post['published_at'])) : null,
                'modified_time' => $post['updated_at'] ? date('c', strtotime((string) $post['updated_at'])) : null,
                'author' => $authorUrl,
                'section' => $post['category_name'] ?? null,
            ],
            'schema_jsonld' => $schema->emitCached(
                sprintf('schema:post:%d:%s', (int) $post['id'], (string) ($post['updated_at'] ?? '')),
                86400  // 24 saat — post update edilince key değişir, otomatik invalidate
            ),
            // Her yazıda yüklenen 4 dosya tek bundle'a (post-core) toplandı:
            //   toc.js + progress.js + share.js + lightbox.js
            // Feature-flag'li ek scriptler ayrı dosya olarak yüklenir (kullanılmazlarsa
            // user-agent hiç indirmesin).
            'body_extra_js' => array_filter([
                \App\Services\AssetMinifier::bundle([
                    'assets/js/toc.js',
                    'assets/js/progress.js',
                    'assets/js/share.js',
                    'assets/js/lightbox.js',
                ], 'assets/js/post-core.min.js'),
                feature('footnotes_enabled') ? \App\Services\AssetMinifier::asset('assets/js/footnotes.js') : null,
                feature('save_post_enabled') ? \App\Services\AssetMinifier::asset('assets/js/save-post.js') : null,
                (feature('clap_enabled') || feature('bookmark_db_enabled') || feature('author_follow_enabled'))
                    ? \App\Services\AssetMinifier::asset('assets/js/engagement.js') : null,
                feature('reactions_enabled') ? \App\Services\AssetMinifier::asset('assets/js/reactions.js') : null,
                feature('quote_share_enabled') ? \App\Services\AssetMinifier::asset('assets/js/quote-share.js') : null,
                feature('analytics_events_enabled') ? \App\Services\AssetMinifier::asset('assets/js/analytics-events.js') : null,
                feature('before_after_enabled') ? \App\Services\AssetMinifier::asset('assets/js/before-after.js') : null,
            ]),
            'head_extra' => '<meta name="post-id" content="' . (int) $post['id'] . '">'
                          . "\n" . '<meta name="csrf-token" content="' . esc(csrf_token()) . '">',
            'breadcrumbs' => [
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => $post['category_name'], 'url' => url('/' . $categorySlug)],
                ['name' => $post['title'], 'url' => $url],
            ],
            'post' => $post,
            'author' => $author,
            'cover' => $cover,
            'body_html' => $body,
            'faq' => $faq,
            'comments' => $comments,
            'related' => $related,
            'tags' => $tags,
            'trending' => $trending,
            'prev_next' => $prevNext,
            'series_info' => $seriesInfo,
            'series_nav' => $seriesNav,
            'logged_in' => AuthService::check(),
        ]);
        return $resp->header('Cache-Control', 'public, max-age=30');
    }

    /**
     * Tag arşiv sayfası — /etiket/{slug}
     */
    public function tag(Request $req, array $args): Response
    {
        $slug = (string) ($args['slug'] ?? '');
        $tag = Tag::findBySlug($slug);
        if (!$tag) {
            return Response::notFound();
        }
        $posts = Tag::postsForTag((int) $tag['id'], 30, 0);
        $url = absolute_url('/etiket/' . $slug);

        // JSON-LD: WebPage + ItemList (mevcut Schema classes)
        $schema = (new Renderer())
            ->add(Renderer::siteOrganization())
            ->add(Renderer::siteWebsite())
            ->add(SchemaWebPage::build($url, 'Etiket: ' . $tag['name'], [
                'type' => 'CollectionPage',
                'description' => '"' . $tag['name'] . '" etiketli ' . (int) $tag['post_count'] . ' yazı',
                'breadcrumb_id' => $url . '#breadcrumb',
            ]))
            ->add(Breadcrumb::build([
                ['name' => 'Ana Sayfa', 'url' => absolute_url('/')],
                ['name' => 'Etiket: ' . $tag['name'], 'url' => $url],
            ], $url . '#breadcrumb'))
            ->add(\App\Services\Schema\ItemList::build(
                ['name' => 'Etiket: ' . $tag['name'], 'slug' => 'etiket/' . $tag['slug'], 'description' => ''],
                $posts,
                $url,
                1
            ));

        return view('pages.tag', [
            'title' => 'Etiket: ' . $tag['name'],
            'description' => '"' . $tag['name'] . '" etiketli yazılar',
            'canonical' => $url,
            'schema_jsonld' => $schema->emitCached(
                'schema:tag:' . $tag['id'] . ':' . date('Y-m-d-H'),
                3600
            ),
            'tag' => $tag,
            'posts' => $posts,
            'breadcrumbs' => [
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Etiket: ' . $tag['name'], 'url' => $url],
            ],
        ]);
    }

    /**
     * WebPage.breadcrumb için @id'li breadcrumb node üretir.
     */
    private static function buildBreadcrumbWithId(array $post, string $categorySlug, string $url): ?array
    {
        return Breadcrumb::build(
            [
                ['name' => 'Ana Sayfa', 'url' => absolute_url('/')],
                ['name' => $post['category_name'], 'url' => absolute_url('/' . $categorySlug)],
                ['name' => $post['title'], 'url' => $url],
            ],
            $url . '#breadcrumb'
        );
    }
}
