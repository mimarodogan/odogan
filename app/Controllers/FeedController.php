<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Cache\CacheManager;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Setting;
use App\Services\MarkdownService;

/**
 * Site içeriklerini RSS 2.0, Atom 1.0 ve JSON Feed 1.1 formatlarında yayımlar.
 * Son 20 yayımlanmış yazı, 1 saat cache.
 */
final class FeedController
{
    private const LIMIT = 20;
    private const CACHE_TTL = 3600;

    public function rss(Request $req): Response
    {
        $xml = CacheManager::driver()->remember(
            'feed:rss',
            self::CACHE_TTL,
            fn() => $this->render('rss', $this->posts()),
            ['feed']
        );
        return new Response((string) $xml, 200, [
            'Content-Type'  => 'application/rss+xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function atom(Request $req): Response
    {
        $xml = CacheManager::driver()->remember(
            'feed:atom',
            self::CACHE_TTL,
            fn() => $this->render('atom', $this->posts()),
            ['feed']
        );
        return new Response((string) $xml, 200, [
            'Content-Type'  => 'application/atom+xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function json(Request $req): Response
    {
        $json = CacheManager::driver()->remember(
            'feed:json',
            self::CACHE_TTL,
            fn() => $this->render('json', $this->posts()),
            ['feed']
        );
        return new Response((string) $json, 200, [
            'Content-Type'  => 'application/feed+json; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * @return array<int,array>
     */
    private function posts(): array
    {
        return Database::instance()->fetchAll(
            'SELECT p.id, p.title, p.slug, p.excerpt, p.body, p.body_format,
                    p.published_at, p.updated_at, p.cover_image,
                    c.name AS category_name, c.slug AS category_slug,
                    u.name AS author_name, u.slug AS author_slug, u.email AS author_email
             FROM posts p
             INNER JOIN categories c ON c.id = p.category_id
             LEFT JOIN users u ON u.id = p.user_id
             WHERE p.status = "published"
             ORDER BY p.published_at DESC
             LIMIT ' . self::LIMIT
        );
    }

    private function render(string $format, array $posts): string
    {
        $siteName = (string) Setting::get('site_name', \App\Core\Config::get('APP_NAME', 'Odogan'));
        $siteDesc = (string) Setting::get('site_description', '');
        $siteUrl  = absolute_url('/');
        $feedUrlMap = [
            'rss'  => absolute_url('/rss'),
            'atom' => absolute_url('/atom.xml'),
            'json' => absolute_url('/feed.json'),
        ];
        $feedUrl = $feedUrlMap[$format];

        ob_start();
        $view = dirname(__DIR__) . '/Views/feed/' . $format . '.php';
        if (!is_file($view)) {
            return '';
        }
        require $view;
        return (string) ob_get_clean();
    }
}
