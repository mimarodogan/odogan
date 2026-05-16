<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Setting;

/**
 * /llms.txt — AI engine'ler (ChatGPT, Claude, Perplexity, vb.) için
 * site özet sitemap'i. Markdown formatında: marka, ana sayfa türleri,
 * son içerikler, kategoriler. Tek bir HTTP fetch ile AI'nin siteyi
 * "anlamasını" sağlar.
 *
 * Spec: https://llmstxt.org/
 *
 * Önbellekli (1 saat) — içerik değişse de TTL geçince yenilenir.
 */
final class LlmsController
{
    public function index(Request $req): Response
    {
        $cache = \App\Core\Cache\CacheManager::driver();
        $body  = $cache->remember('llms.txt', 3600, fn() => self::build(), ['llms']);

        return new Response($body, 200, [
            'Content-Type'  => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
            'X-Robots-Tag'  => 'noindex',
        ]);
    }

    private static function build(): string
    {
        $siteName    = (string) Setting::get('site_name', 'Osman Doğan');
        $tagline     = (string) Setting::get('site_tagline', '');
        $description = (string) Setting::get('site_description', '');
        $base        = rtrim((string) Setting::get('canonical_base', \App\Core\Config::get('APP_URL', ''), 'seo'), '/');

        $out  = "# {$siteName}\n";
        if ($tagline !== '') {
            $out .= "\n> {$tagline}\n";
        }
        $out .= "\n";
        if ($description !== '') {
            $out .= mb_substr($description, 0, 500) . "\n\n";
        }

        // ── Kategoriler ──
        $out .= "## Kategoriler\n\n";
        $cats = self::fetchAllSafe(
            'SELECT slug, name, description FROM categories WHERE is_active = 1 ORDER BY position, name'
        );
        foreach ($cats as $c) {
            $desc = trim((string) ($c['description'] ?? ''));
            $out .= "- [{$c['name']}]({$base}/{$c['slug']})";
            if ($desc !== '') {
                $out .= ': ' . mb_substr(strip_tags($desc), 0, 200);
            }
            $out .= "\n";
        }
        $out .= "\n";

        // ── Yazarlar ──
        $out .= "## Yazarlar\n\n";
        $authors = self::fetchAllSafe(
            "SELECT slug, name, headline FROM users
             WHERE role IN ('admin','editor','author')
               AND (deleted_at IS NULL)
             ORDER BY id ASC LIMIT 20"
        );
        foreach ($authors as $a) {
            $head = trim((string) ($a['headline'] ?? ''));
            $out .= "- [{$a['name']}]({$base}/yazar/{$a['slug']})";
            if ($head !== '') {
                $out .= ': ' . $head;
            }
            $out .= "\n";
        }
        $out .= "\n";

        // ── Son Yazılar (30) ──
        $out .= "## Son Yazılar\n\n";
        $posts = self::fetchAllSafe(
            "SELECT p.title, p.slug, p.excerpt, c.slug AS cslug, p.published_at
             FROM posts p
             INNER JOIN categories c ON c.id = p.category_id
             WHERE p.status='published'
             ORDER BY p.published_at DESC LIMIT 30"
        );
        foreach ($posts as $p) {
            $ex = trim(strip_tags((string) ($p['excerpt'] ?? '')));
            $out .= "- [{$p['title']}]({$base}/{$p['cslug']}/{$p['slug']})";
            if ($ex !== '') {
                $out .= ': ' . mb_substr($ex, 0, 180);
            }
            $out .= "\n";
        }
        $out .= "\n";

        // ── Projeler ──
        if (Setting::get('project_portfolio_enabled', false, 'features')) {
            $out .= "## Mimari Projeler\n\n";
            $projects = self::fetchAllSafe(
                "SELECT name, slug, subtitle, location, year_completed
                 FROM projects WHERE status='published'
                 ORDER BY year_completed DESC, name ASC LIMIT 30"
            );
            foreach ($projects as $pr) {
                $sub = trim((string) ($pr['subtitle'] ?? ''));
                $loc = trim((string) ($pr['location'] ?? ''));
                $yr  = (int) ($pr['year_completed'] ?? 0);
                $out .= "- [{$pr['name']}]({$base}/proje/{$pr['slug']})";
                $meta = [];
                if ($loc !== '') $meta[] = $loc;
                if ($yr > 0)     $meta[] = (string) $yr;
                if ($meta)       $out .= ' (' . implode(' · ', $meta) . ')';
                if ($sub !== '') $out .= ': ' . mb_substr($sub, 0, 150);
                $out .= "\n";
            }
            $out .= "\n";
        }

        // ── Sözlük (varsa) ──
        if (function_exists('feature') && feature('glossary_enabled')) {
            $out .= "## Mimari Sözlük\n\n";
            $out .= "Tüm terimlerin tam listesi: {$base}/sozluk\n\n";
            $terms = self::fetchAllSafe(
                "SELECT term, slug, category FROM glossary
                 WHERE is_active = 1 ORDER BY view_count DESC, term ASC LIMIT 40"
            );
            foreach ($terms as $t) {
                $cat = trim((string) ($t['category'] ?? ''));
                $out .= "- [{$t['term']}]({$base}/sozluk/{$t['slug']})";
                if ($cat !== '') $out .= " ({$cat})";
                $out .= "\n";
            }
            $out .= "\n";
        }

        // ── Önemli Sayfalar ──
        $out .= "## Önemli Bağlantılar\n\n";
        $out .= "- Sitemap (XML): {$base}/sitemap.xml\n";
        $out .= "- RSS feed: {$base}/rss\n";
        $out .= "- Atom feed: {$base}/atom.xml\n";
        $out .= "- JSON Feed: {$base}/feed.json\n";
        if (Setting::get('building_map_enabled', false, 'features')) {
            $out .= "- Yapı haritası: {$base}/harita\n";
        }
        $out .= "\n";

        $out .= "---\n";
        $out .= "Bu dosya AI engine'leri için site içeriğinin yapılandırılmış özetidir.\n";
        $out .= "Spec: https://llmstxt.org/\n";

        return $out;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function fetchAllSafe(string $sql): array
    {
        try {
            return Database::instance()->fetchAll($sql) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }
}
