<?php
declare(strict_types=1);

use App\Core\Config;
use App\Models\Setting;

if (!function_exists('site_setting')) {
    /**
     * Read a site setting with .env fallback.
     */
    function site_setting(string $key, mixed $default = null, string $group = 'general'): mixed
    {
        return Setting::get($key, $default, $group);
    }
}

if (!function_exists('seo_meta')) {
    /**
     * Render <title>, description, canonical, OG and Twitter Card tags.
     *
     * Recognized keys: title, description, url, image, type (article|website),
     *                  published_time, modified_time, author, section,
     *                  twitter_handle.
     */
    function seo_meta(array $meta = []): string
    {
        $siteName     = (string) site_setting('site_name', Config::get('APP_NAME', 'Otorite Yayin'));
        $defaultDesc  = (string) site_setting('site_description', '');
        $defaultImage = (string) site_setting('default_og_image', '', 'seo');
        $titleSep     = (string) site_setting('meta_title_sep', ' — ', 'seo');
        $twitterDef   = (string) site_setting('twitter_handle', '', 'seo');
        // Ana sayfa için tagline: "Osman Doğan — Mimar & İnşaat Mühendisi"
        // Admin Settings > Genel > site_tagline ile ayarlanır.
        $siteTagline  = (string) site_setting('site_tagline', '', 'general');

        $title = (string) ($meta['title'] ?? $siteName);
        // Ana sayfada (title === siteName) tagline varsa ekle; yoksa sadece site adı
        $fullTitle = $title === $siteName
            ? ($siteTagline !== '' ? $siteName . $titleSep . $siteTagline : $siteName)
            : $title . $titleSep . $siteName;
        // Cümle sonu farkındalıklı kesim: 155 karakter üst limit, yarım kelime/cümle bırakmaz
        $_descRaw = (string) ($meta['description'] ?? $defaultDesc);
        if (mb_strlen($_descRaw) > 155) {
            $_cut = mb_substr($_descRaw, 0, 155);
            $_pos = max(
                (int) mb_strrpos($_cut, '. '),
                (int) mb_strrpos($_cut, '! '),
                (int) mb_strrpos($_cut, '? ')
            );
            $description = $_pos > 60 ? mb_substr($_descRaw, 0, $_pos + 1) : rtrim($_cut) . '…';
        } else {
            $description = $_descRaw;
        }
        unset($_descRaw, $_cut, $_pos);
        $canonical = (string) ($meta['url'] ?? '');
        $image = (string) ($meta['image'] ?? $defaultImage);
        $type = (string) ($meta['type'] ?? 'website');
        if (empty($meta['twitter_handle']) && $twitterDef !== '') {
            $meta['twitter_handle'] = $twitterDef;
        }

        $out = '<title>' . esc($fullTitle) . '</title>' . "\n";
        // Robots meta — controller 'noindex, nofollow' gibi değer geçebilir
        $robots = trim((string) ($meta['robots'] ?? ''));
        if ($robots !== '') {
            $out .= '<meta name="robots" content="' . esc($robots) . '">' . "\n";
        }
        if ($description !== '') {
            $out .= '<meta name="description" content="' . esc($description) . '">' . "\n";
        }
        if ($canonical !== '') {
            $out .= '<link rel="canonical" href="' . esc($canonical) . '">' . "\n";
        }

        // Open Graph
        $out .= '<meta property="og:site_name" content="' . esc($siteName) . '">' . "\n";
        $out .= '<meta property="og:title" content="' . esc($title) . '">' . "\n";
        if ($description !== '') {
            $out .= '<meta property="og:description" content="' . esc($description) . '">' . "\n";
        }
        $out .= '<meta property="og:type" content="' . esc($type) . '">' . "\n";
        if ($canonical !== '') {
            $out .= '<meta property="og:url" content="' . esc($canonical) . '">' . "\n";
        }
        if ($image !== '') {
            $out .= '<meta property="og:image" content="' . esc($image) . '">' . "\n";
        }
        $out .= '<meta property="og:locale" content="'
            . esc(str_replace('-', '_', (string) Config::get('APP_LOCALE', 'tr_TR'))) . '">' . "\n";

        if ($type === 'article') {
            foreach (['published_time', 'modified_time', 'author', 'section'] as $k) {
                if (!empty($meta[$k])) {
                    $out .= '<meta property="article:' . esc($k) . '" content="' . esc((string) $meta[$k]) . '">' . "\n";
                }
            }
        }

        // Twitter
        $out .= '<meta name="twitter:card" content="' . esc($image !== '' ? 'summary_large_image' : 'summary') . '">' . "\n";
        $out .= '<meta name="twitter:title" content="' . esc($title) . '">' . "\n";
        if ($description !== '') {
            $out .= '<meta name="twitter:description" content="' . esc($description) . '">' . "\n";
        }
        if ($image !== '') {
            $out .= '<meta name="twitter:image" content="' . esc($image) . '">' . "\n";
        }
        if (!empty($meta['twitter_handle'])) {
            $out .= '<meta name="twitter:site" content="' . esc((string) $meta['twitter_handle']) . '">' . "\n";
        }
        return $out;
    }
}

if (!function_exists('breadcrumbs_html')) {
    /**
     * @param array<int,array{name:string,url:string}> $items
     */
    function breadcrumbs_html(array $items): string
    {
        if (!$items) {
            return '';
        }
        $links = [];
        $count = count($items);
        foreach ($items as $i => $row) {
            $name = esc((string) ($row['name'] ?? ''));
            $url = esc((string) ($row['url'] ?? ''));
            $links[] = ($i < $count - 1)
                ? '<a href="' . $url . '" title="' . $name . '">' . $name . '</a>'
                : '<span aria-current="page">' . $name . '</span>';
        }
        return '<nav class="breadcrumbs" aria-label="Sayfa konumu">'
            . implode(' <span class="sep">›</span> ', $links)
            . '</nav>';
    }
}

if (!function_exists('absolute_url')) {
    function absolute_url(string $path): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        return rtrim((string) Config::get('APP_URL', ''), '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('og_image_for_post')) {
    /**
     * Bir post için kullanılacak OG image URL'ini seçer (absolute URL).
     * Öncelik:
     *  1) posts.og_image kolon değeri
     *  2) posts.cover_image (oluşturulmuş varyantlardan)
     *  3) Otomatik üretim (OgImageGenerator — başlık + brand layer)
     *  4) Settings::default_og_image
     */
    function og_image_for_post(array $post, mixed $coverMedia = null): ?string
    {
        // 1) og_image kolonu
        if (!empty($post['og_image'])) {
            return absolute_url((string) $post['og_image']);
        }
        // 2) cover_image üzerinden
        if ($coverMedia !== null) {
            $u = og_image($coverMedia);
            if ($u) return preg_match('#^https?://#i', $u) ? $u : absolute_url($u);
        } elseif (!empty($post['cover_image'])) {
            return absolute_url((string) $post['cover_image']);
        }
        // 3) Otomatik üretim (GD)
        $url = \App\Services\OgImageGenerator::ensureFor($post);
        if ($url) {
            return absolute_url($url);
        }
        // 4) Site varsayılan OG image
        $default = (string) site_setting('default_og_image', '', 'seo');
        return $default !== '' ? absolute_url($default) : null;
    }
}
