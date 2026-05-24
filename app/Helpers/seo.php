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
            // Standart OG image boyutları (Twitter Card validator faydalı bulur).
            // Genelde 1200×630 — site OgImageGenerator'ı bu boyutu üretir.
            $imgW = (int) ($meta['image_width']  ?? 1200);
            $imgH = (int) ($meta['image_height'] ?? 630);
            if ($imgW > 0) $out .= '<meta property="og:image:width" content="' . $imgW . '">' . "\n";
            if ($imgH > 0) $out .= '<meta property="og:image:height" content="' . $imgH . '">' . "\n";
            $imgAlt = trim((string) ($meta['image_alt'] ?? $title));
            if ($imgAlt !== '') {
                $out .= '<meta property="og:image:alt" content="' . esc($imgAlt) . '">' . "\n";
            }
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
            $twImgAlt = trim((string) ($meta['image_alt'] ?? $title));
            if ($twImgAlt !== '') {
                $out .= '<meta name="twitter:image:alt" content="' . esc($twImgAlt) . '">' . "\n";
            }
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

if (!function_exists('social_icon_svg')) {
    /**
     * Inline SVG ikon — sosyal medya / iletişim platformları.
     *
     * `currentColor` stroke kullanır; renk CSS'ten kontrol edilir (cobalt
     * hover, soot default). 24×24 viewBox, 1.6 stroke-width — Phosphor /
     * Lucide stil. About + Footer'da kullanılır.
     *
     * Desteklenen anahtarlar:
     *   twitter, x, linkedin, instagram, facebook, youtube, github,
     *   mastodon, website, email, rss
     */
    function social_icon_svg(string $key): string
    {
        $key = mb_strtolower(trim($key));
        // Twitter eski API → X
        if ($key === 'twitter') $key = 'x';

        $paths = [
            'x'         => '<path d="M4 4l16 16M20 4L4 20" stroke-linecap="round"/>',
            'linkedin'  => '<rect x="3" y="3" width="18" height="18" rx="1.5"/>'
                         . '<line x1="7" y1="9.5" x2="7" y2="17"/>'
                         . '<circle cx="7" cy="6.5" r=".9" fill="currentColor" stroke="none"/>'
                         . '<path d="M11 17V12.5c0-1.6 1-2.5 2.3-2.5 1.4 0 2.2.9 2.2 2.6V17M11 17v-7"/>',
            'instagram' => '<rect x="3" y="3" width="18" height="18" rx="4.5"/>'
                         . '<circle cx="12" cy="12" r="4"/>'
                         . '<circle cx="17.5" cy="6.5" r=".9" fill="currentColor" stroke="none"/>',
            'facebook'  => '<path d="M14.5 21v-7h2.5l.4-3.2h-2.9V8.5c0-.9.3-1.5 1.7-1.5h1.6V4.2c-.3 0-1.2-.1-2.3-.1-2.3 0-3.8 1.4-3.8 3.9v2.3H9v3.2h2.7V21z"/>',
            'youtube'   => '<rect x="2" y="6" width="20" height="12" rx="3"/>'
                         . '<path d="M10 9.5v5l5-2.5z" fill="currentColor" stroke="none"/>',
            'github'    => '<path d="M9 19c-3.5 1-3.5-1.5-5-2m10 4v-3.2c0-.9-.1-1.3-.5-1.8 3-.3 6-1.4 6-6.4 0-1.4-.5-2.5-1.3-3.4.1-.4.6-1.6-.1-3.3 0 0-1-.3-3.4 1.3-1-.3-2-.4-3-.4s-2 .1-3 .4C6.2 2.7 5.2 3 5.2 3c-.7 1.7-.2 2.9-.1 3.3-.8.9-1.3 2-1.3 3.4 0 5 3 6.1 6 6.4-.4.4-.5.9-.5 1.7V21" stroke-linecap="round" stroke-linejoin="round"/>',
            'mastodon'  => '<path d="M19 11c0 5-3 6.5-7 6.5-2.3 0-4.1-.4-4.1-.4s.1 1.6 1.8 2.4c1.6.7 5.2.2 7-.5l.2 1.5c-2 1-5.5 1.5-7.6 1-3.5-.9-4-3.5-4-7v-4C5.3 6 7.6 4.5 12 4.5s6.7 1.5 7 4.5z"/>'
                         . '<path d="M9 14V9c0-.8.7-1.5 1.5-1.5S12 8.2 12 9v4M15 14V9c0-.8-.7-1.5-1.5-1.5S12 8.2 12 9"/>',
            'website'   => '<circle cx="12" cy="12" r="9"/>'
                         . '<path d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/>',
            'email'     => '<rect x="3" y="5" width="18" height="14" rx="1.5"/>'
                         . '<path d="M3 7l9 6.5L21 7" stroke-linejoin="round"/>',
            'rss'       => '<path d="M4 11a9 9 0 019 9M4 4a16 16 0 0116 16" stroke-linecap="round"/>'
                         . '<circle cx="5" cy="19" r="1.4" fill="currentColor" stroke="none"/>',
        ];
        $svgBody = $paths[$key] ?? null;
        if ($svgBody === null) {
            return '';
        }
        return '<svg class="social-icon social-icon-' . esc($key) . '" '
             . 'viewBox="0 0 24 24" width="22" height="22" '
             . 'fill="none" stroke="currentColor" stroke-width="1.6" '
             . 'aria-hidden="true" focusable="false">'
             . $svgBody
             . '</svg>';
    }
}

if (!function_exists('social_icon_label')) {
    /**
     * Sosyal platform için okunabilir etiket — screen reader için.
     */
    function social_icon_label(string $key): string
    {
        $key = mb_strtolower(trim($key));
        if ($key === 'twitter') $key = 'x';
        return [
            'x'         => 'X (Twitter)',
            'linkedin'  => 'LinkedIn',
            'instagram' => 'Instagram',
            'facebook'  => 'Facebook',
            'youtube'   => 'YouTube',
            'github'    => 'GitHub',
            'mastodon'  => 'Mastodon',
            'website'   => 'Web Sitesi',
            'email'     => 'E-posta',
            'rss'       => 'RSS Beslemesi',
        ][$key] ?? ucfirst($key);
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
