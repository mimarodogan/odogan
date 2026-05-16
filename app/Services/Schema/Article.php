<?php
declare(strict_types=1);

namespace App\Services\Schema;

use App\Core\Config;
use App\Models\Setting;
use App\Services\MarkdownService;

/**
 * Article JSON-LD üreticisi.
 *
 * Özellikler:
 *  - `posts.article_type` kolonuna göre Schema.org alt-tipi seçer
 *    (BlogPosting / NewsArticle / TechArticle / HowTo / Article)
 *  - @graph içinde Person ve Organization node'larına `@id` ile referanslar
 *  - timeRequired (okuma süresi), interactionStatistic (views + yorumlar),
 *    speakable (sesli arama), about (kategori), isPartOf (WebSite)
 *  - license + copyrightHolder + copyrightYear
 */
final class Article
{
    private const ALLOWED_TYPES = [
        'Article',        // jenerik
        'BlogPosting',    // blog yazısı (default)
        'NewsArticle',    // güncel haber
        'TechArticle',    // teknik makale
        'HowTo',          // adım-adım rehber (HowTo builder ayrıca devreye girer)
    ];

    public static function build(array $post, array $author, array $profile, ?array $cover): array
    {
        $url = url('/' . $post['category_slug'] . '/' . $post['slug']);
        $authorUrl = url('/yazar/' . $author['slug']);
        $siteUrl = rtrim((string) Setting::get('canonical_base', Config::get('APP_URL', ''), 'seo'), '/');

        $type = (string) ($post['article_type'] ?? 'BlogPosting');
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            $type = 'BlogPosting';
        }

        $node = [
            '@type'            => $type,
            '@id'              => $url . '#article',
            'headline'         => mb_substr((string) $post['title'], 0, 110),
            'description'      => self::description($post),
            // TL;DR — AI engine'ler (ChatGPT, Perplexity) için yapısal özet
            'abstract'         => !empty($post['excerpt']) ? mb_substr((string) $post['excerpt'], 0, 500) : self::description($post),
            'mainEntityOfPage' => ['@id' => $url . '#webpage'],
            'url'              => $url,
            'inLanguage'       => (string) Setting::get('site_locale', Config::get('APP_LOCALE', 'tr'), 'general'),
            'datePublished'    => self::iso($post['published_at'] ?? null),
            'dateModified'     => self::iso($post['updated_at'] ?? $post['published_at'] ?? null),
            'wordCount'        => self::words((string) $post['body'], (string) ($post['body_format'] ?? 'markdown')),
            'isPartOf'         => $siteUrl !== '' ? ['@id' => $siteUrl . '#website'] : null,
            'author'           => ['@id' => $authorUrl . '#person'],
            'publisher'        => $siteUrl !== '' ? ['@id' => $siteUrl . '#org'] : null,
        ];

        // Image — @id ile referanslanabilir bir ImageObject
        if ($cover) {
            $node['image'] = self::imageObject($cover, $url);
        }

        // Kategori → Article.articleSection + about
        if (!empty($post['category_name'])) {
            $node['articleSection'] = (string) $post['category_name'];
            $node['about'] = [
                '@type' => 'Thing',
                'name'  => (string) $post['category_name'],
                'url'   => url('/' . $post['category_slug']),
            ];
        }

        // Keywords — yazarın uzmanlık alanları
        if (!empty($profile['expertise'])) {
            $node['keywords'] = implode(', ', array_slice((array) $profile['expertise'], 0, 10));
        }

        // Okuma süresi — ISO 8601 (örn. PT5M)
        $minutes = (int) ($post['reading_minutes'] ?? 0);
        if ($minutes > 0) {
            $node['timeRequired'] = 'PT' . $minutes . 'M';
        }

        // Etkileşim istatistikleri
        $stats = [];
        $views = (int) ($post['view_count'] ?? 0);
        if ($views > 0) {
            $stats[] = [
                '@type'                 => 'InteractionCounter',
                'interactionType'       => 'https://schema.org/ReadAction',
                'userInteractionCount'  => $views,
            ];
        }
        $comments = (int) ($post['comment_count'] ?? 0);
        if ($comments > 0) {
            $stats[] = [
                '@type'                 => 'InteractionCounter',
                'interactionType'       => 'https://schema.org/CommentAction',
                'userInteractionCount'  => $comments,
            ];
            $node['commentCount'] = $comments;
        }
        if ($stats) {
            $node['interactionStatistic'] = $stats;
        }

        // Speakable — Google Assistant sesli arama için
        $node['speakable'] = [
            '@type'       => 'SpeakableSpecification',
            'cssSelector' => ['h1', '.post .lead', '.post-body > p:first-of-type'],
        ];

        // License + copyright
        $licenseUrl = trim((string) Setting::get('org_license_url', '', 'organization'));
        if ($licenseUrl !== '' && preg_match('#^https?://#i', $licenseUrl)) {
            $node['license'] = $licenseUrl;
        }
        if (!empty($post['published_at'])) {
            $year = (int) date('Y', strtotime((string) $post['published_at']));
            if ($year > 1990) {
                $node['copyrightYear'] = $year;
            }
        }
        if ($siteUrl !== '') {
            $node['copyrightHolder'] = ['@id' => $siteUrl . '#org'];
        }

        return self::clean($node);
    }

    private static function description(array $post): string
    {
        if (!empty($post['meta_description'])) {
            return (string) $post['meta_description'];
        }
        if (!empty($post['excerpt'])) {
            return (string) $post['excerpt'];
        }
        return MarkdownService::plain(
            (string) ($post['body'] ?? ''),
            240,
            (string) ($post['body_format'] ?? 'markdown')
        );
    }

    /**
     * ImageObject — @id ile referanslanabilir.
     */
    private static function imageObject(array $media, string $postUrl): array
    {
        $node = [
            '@type' => 'ImageObject',
            '@id'   => $postUrl . '#primaryimage',
            'url'   => og_image($media),
        ];
        if (!empty($media['width']) && !empty($media['height'])) {
            $node['width']  = (int) $media['width'];
            $node['height'] = (int) $media['height'];
        }
        return $node;
    }

    private static function iso(?string $datetime): ?string
    {
        if (!$datetime) {
            return null;
        }
        $ts = strtotime($datetime);
        return $ts ? date('c', $ts) : null;
    }

    private static function words(string $body, string $format = 'markdown'): int
    {
        $html = $format === 'html' ? MarkdownService::fromHtml($body) : MarkdownService::toHtml($body);
        $plain = strip_tags($html);
        $tokens = preg_split('/\s+/u', trim($plain)) ?: [];
        return count(array_filter($tokens));
    }

    private static function clean(array $node): array
    {
        return array_filter($node, static fn($v) => $v !== null && $v !== '' && $v !== []);
    }
}
