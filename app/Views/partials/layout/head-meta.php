<?php
/**
 * <head> meta + CSS bundles + analytics/verification tags.
 *
 * Beklenen scope değişkenleri (base.php'den miras):
 * @var bool        $_inAdmin
 * @var bool        $_adminAuth
 * @var bool        $_isEditor
 * @var array       $seoArgs
 * @var string|null $page_type
 * @var string|null $css_extra
 * @var string|null $head_extra
 * @var string|null $schema_jsonld
 */
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light only">
<?= seo_meta($seoArgs) ?>
<?php
// Pagination rel=prev/next (kategori vb. sayfalanmış sayfalarda)
$_paging = $pagination ?? null;
if (is_array($_paging)):
    $_prev = $_paging['prev_abs_url'] ?? $_paging['prev_url'] ?? '';
    $_next = $_paging['next_abs_url'] ?? $_paging['next_url'] ?? '';
    if ($_prev): ?>
<link rel="prev" href="<?= esc($_prev) ?>">
    <?php endif; ?>
    <?php if ($_next): ?>
<link rel="next" href="<?= esc($_next) ?>">
    <?php endif; ?>
<?php endif; ?>
<?php
// Feed alternate links — RSS/Atom/JSON Feed
if (!$_inAdmin): ?>
<link rel="alternate" type="application/rss+xml"  title="Odogan RSS"  href="<?= esc(url('/rss')) ?>">
<link rel="alternate" type="application/atom+xml" title="Odogan Atom" href="<?= esc(url('/atom.xml')) ?>">
<link rel="alternate" type="application/feed+json" title="Odogan JSON" href="<?= esc(url('/feed.json')) ?>">
<?php endif; ?>
<?php
// Resource hints — analytics yüklemede preconnect kazancı (LCP ~100ms)
$_gaIdHint = $_inAdmin ? '' : (string) \App\Models\Setting::get('google_analytics_id', '', 'analytics');
if ($_gaIdHint !== ''): ?>
<link rel="preconnect" href="https://www.googletagmanager.com" crossorigin>
<link rel="dns-prefetch" href="https://www.google-analytics.com">
<?php endif; ?>

<?php
// LCP optimization — hero/cover görsel preload (Core Web Vitals'ı en çok etkileyen
// kaynak: yazı sayfası post.cover, anasayfa featured cover).
// Controller `$preload_image` set ederse buraya gelir; aksi halde otomatik tespit.
if (!$_inAdmin) {
    $_preloadImg = $preload_image ?? null;
    if (!$_preloadImg) {
        // Yazı sayfası: $post['cover_image']
        if (!empty($post['cover_image'])) {
            $_preloadImg = (string) $post['cover_image'];
        }
        // Anasayfa hero: $featured['cover_image']
        elseif (!empty($featured['cover_image'])) {
            $_preloadImg = (string) $featured['cover_image'];
        }
    }
    if ($_preloadImg) {
        $_preloadUrl = preg_match('#^https?://#i', $_preloadImg)
            ? $_preloadImg
            : url($_preloadImg);
        ?>
<link rel="preload" as="image" href="<?= esc($_preloadUrl) ?>" fetchpriority="high">
        <?php
    }
}
?>

<?php
// Public CSS bundle — sıra cascade için kritik:
// önce tokens/reset, sonra layout/chrome, sonra component'lar, sonra
// pattern'lar, en son responsive ve page-spesifik override'lar.
$publicCss = [
    'assets/css/app/tokens.css',         // CSS değişkenleri, reset, typography, utilities
    'assets/css/app/header-nav.css',     // site header + brand + primary nav
    'assets/css/app/buttons.css',        // .btn ve varyantları
    'assets/css/app/forms.css',          // public form öğeleri (auth)
    'assets/css/app/flash.css',          // flash mesajları
    'assets/css/app/badges.css',         // post status badge'leri
    'assets/css/app/hero.css',           // hero + featured article
    'assets/css/app/cat-pill.css',       // kategori pill
    'assets/css/app/mag-grid.css',       // magazine grid kart sistemi
    'assets/css/app/blocks.css',         // block-title, two-col, rank-list, showcase
    'assets/css/app/share-buttons.css',  // sosyal paylaşım butonları
    'assets/css/app/lightbox.css',       // görsel lightbox overlay
    'assets/css/app/pagination.css',     // kategori sayfalama bileşeni
    'assets/css/app/cookie-consent.css', // KVKK çerez onay banner'ı
    'assets/css/app/responsive.css',     // mobile/tablet breakpoints
    'assets/css/app/authors-grid.css',   // yazarlar sayfası (kendi mobile dahil)
    'assets/css/app/error-pages.css',    // 404 / 500
    'assets/css/app/tier9.css',          // Tier 9 — projects, map, paywall, sponsor
    'assets/css/app/print.css',          // @media print — temiz çıktı
];
?>
<?php
// Critical CSS (Tier 9) — inline edip ana stylesheet'i lazy yükle.
$_criticalCss = '';
$_criticalOn = !$_inAdmin
    && function_exists('feature')
    && feature('critical_css_enabled');
if ($_criticalOn) {
    $_criticalCss = (string) \App\Models\Setting::get('critical_css_content', '', 'features');
    // Setting boşsa → koda gömülü fallback critical CSS (above-the-fold: tokens +
    // base + header). Admin /admin/critical-css'ten elle içerik girerse o öncelikli.
    if (trim($_criticalCss) === '') {
        $_critFallback = dirname(__DIR__, 4) . '/assets/css/critical.css';
        if (is_file($_critFallback)) {
            $_criticalCss = (string) file_get_contents($_critFallback);
        }
    }
    // Defense-in-depth: admin trusted input bile olsa @import ve url(...)
    // ifadelerini düşür — exfiltration / SSRF / mixed-content yolu kapansın.
    // data:image/... gibi inline data URI'lar tutulur (kontrol altında, leak yok).
    if ($_criticalCss !== '') {
        $_criticalCss = preg_replace('#@import[^;]*;#i', '', $_criticalCss) ?? '';
        $_criticalCss = preg_replace('#url\s*\(\s*["\']?\s*(?!data:)[^)]*\)#i', '', $_criticalCss) ?? '';
        // Boş kaldıysa <style> bloğu basmayalım
        $_criticalCss = trim($_criticalCss);
    }
}
$_mainCssHref = \App\Services\AssetMinifier::bundle($publicCss, 'assets/css/app.min.css');
if ($_criticalCss !== ''): ?>
<style id="critical-css"><?= $_criticalCss /* trusted admin input, @import/url() stripped */ ?></style>
<link rel="preload" as="style" href="<?= esc($_mainCssHref) ?>" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="<?= esc($_mainCssHref) ?>"></noscript>
<?php else: ?>
<link rel="stylesheet" href="<?= esc($_mainCssHref) ?>">
<?php endif; ?>
<?php if (($page_type ?? '') === 'article' || in_array($css_extra ?? '', ['post','author'], true)): ?>
<link rel="stylesheet" href="<?= esc(\App\Services\AssetMinifier::asset('assets/css/post.css')) ?>">
<?php endif; ?>
<?php if ($_adminAuth && $_inAdmin): ?>
<link rel="stylesheet" href="<?= esc(\App\Services\AssetMinifier::asset('assets/css/panel.css')) ?>">
<?php
// Admin CSS bundle — TÜM panel/admin/editor auth user'larına yüklenir.
// (AUTHOR rolü post yazma formuna ulaşır; MEMBER de panel'i kullanır.)
// post-editor.css olmadan form çıplak görünüyor.
$adminCss = [
    'assets/css/admin/tables-base.css',     // admin tablolar, pagination, log lines
    'assets/css/admin/wysiwyg.css',         // WYSIWYG editor + toolbar + dropdowns
    'assets/css/admin/media.css',           // medya kütüphanesi + picker modal + upload
    'assets/css/admin/dashboard.css',       // dashboard stats + recent activity
    'assets/css/admin/settings-base.css',   // site settings form (temel layout)
    'assets/css/admin/polish.css',          // v2 polish — Atelier refinements (override)
    'assets/css/admin/settings-extra.css',  // select stilleri + mail test formu
    'assets/css/admin/mail-debug.css',      // SMTP debug transcript + OAuth2 uyarısı
    'assets/css/admin/post-editor.css',     // post editor 8/4 split + SEO preview
    'assets/css/admin/media-input.css',     // tek/çoklu görsel seçici input
    'assets/css/admin/tier9.css',           // Tier 9 — approval, A/B test, projects
    'assets/css/admin/responsive.css',      // mobile (≤720px) overrides
];
?>
<link rel="stylesheet" href="<?= esc(\App\Services\AssetMinifier::bundle($adminCss, 'assets/css/admin.min.css')) ?>">
<?php endif; ?>
<?php if (function_exists('feature') && feature('pwa_enabled') && !$_inAdmin): ?>
<link rel="manifest" href="<?= esc(url('/manifest.webmanifest')) ?>">
<meta name="theme-color" content="#1F3A8A">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="<?= esc((string) \App\Models\Setting::get('site_name', 'Odogan')) ?>">
<?php endif; ?>
<?php
// Yapı Haritası sayfası — Leaflet CSS head'de yüklenmeli (render-blocking
// olarak değil; preload + onload ile non-blocking). css_extra='map' işareti
// MapController'dan gelir.
if (($css_extra ?? '') === 'map' && !$_inAdmin): ?>
<link rel="stylesheet" href="<?= esc(asset('vendor/leaflet/leaflet.css')) ?>">
<link rel="stylesheet" href="<?= esc(\App\Services\AssetMinifier::asset('assets/css/app/building-map.css')) ?>">
<?php endif; ?>
<?php
// Projeler portfolyo sayfası — kendi CSS dosyası
if (($css_extra ?? '') === 'projects' && !$_inAdmin): ?>
<link rel="stylesheet" href="<?= esc(\App\Services\AssetMinifier::asset('assets/css/app/projects.css')) ?>">
<?php endif; ?>
<?php if (!empty($head_extra)) echo $head_extra; ?>
<?php if (!empty($schema_jsonld)) echo $schema_jsonld; ?>
<?php
// Site genel meta — hem public hem admin'de geçerli.
$_siteName   = (string) \App\Models\Setting::get('site_name', \App\Core\Config::get('APP_NAME', 'Otorite Yayın'));
$_authorMeta = (string) \App\Models\Setting::get('site_name', $_siteName);
$_publisher  = (string) \App\Models\Setting::get('org_legal_name', '', 'organization') ?: $_siteName;
$_favicon    = (string) \App\Models\Setting::get('site_favicon', '', 'general');
?>
<meta name="author" content="<?= esc($_authorMeta) ?>">
<meta name="publisher" content="<?= esc($_publisher) ?>">
<?php
// Favicon type detection
$_favType = 'image/x-icon';
if ($_favicon !== '') {
    $ext = strtolower(pathinfo(parse_url($_favicon, PHP_URL_PATH) ?: $_favicon, PATHINFO_EXTENSION));
    $_favType = match ($ext) {
        'svg'   => 'image/svg+xml',
        'png'   => 'image/png',
        'webp'  => 'image/webp',
        'jpg', 'jpeg' => 'image/jpeg',
        default => 'image/x-icon',
    };
    $_favUrl = preg_match('#^https?://#i', $_favicon) ? $_favicon : url($_favicon);
}
?>
<?php if ($_favicon !== ''): ?>
<link rel="icon" type="<?= esc($_favType) ?>" href="<?= esc($_favUrl) ?>">
<link rel="apple-touch-icon" href="<?= esc($_favUrl) ?>">
<?php else: ?>
<link rel="icon" type="image/svg+xml" href="<?= esc(url('favicon.svg')) ?>">
<link rel="icon" type="image/x-icon" href="<?= esc(url('favicon.ico')) ?>">
<link rel="apple-touch-icon" href="<?= esc(url('favicon.svg')) ?>">
<?php endif; ?>
<?php
// Site-wide analytics & verification (public pages only — never leak from admin).
if (!$_inAdmin):
    $_gaId         = (string) \App\Models\Setting::get('google_analytics_id', '', 'analytics');
    $_gscToken     = (string) \App\Models\Setting::get('google_site_verify', '', 'analytics');
    $_bingToken    = (string) \App\Models\Setting::get('bing_site_verify', '', 'analytics');
    // Per-page robots override: controller veya view '$robots' değişkeni set ederse
    // o değer kullanılır; yoksa site defaultu.
    $_robotsDir    = isset($robots) && $robots !== ''
        ? (string) $robots
        : (string) \App\Models\Setting::get('robots_default', 'index, follow', 'seo');
    $_siteKeywords = (string) \App\Models\Setting::get('site_keywords', '', 'general');
?>
    <?php if ($_gscToken !== ''): ?>
    <meta name="google-site-verification" content="<?= esc($_gscToken) ?>">
    <?php endif; ?>
    <?php if ($_bingToken !== ''): ?>
    <meta name="msvalidate.01" content="<?= esc($_bingToken) ?>">
    <?php endif; ?>
    <meta name="robots" content="<?= esc($_robotsDir) ?>">
    <?php if ($_siteKeywords !== ''): ?>
    <meta name="keywords" content="<?= esc($_siteKeywords) ?>">
    <?php endif; ?>
    <?php
    // Consent Mode V2 default 'denied' — GA4 etiketinden ÖNCE çalışmalı (K3 KVKK)
    require __DIR__ . '/cookie-consent-init.php';
    ?>
    <?php if ($_gaId !== '' && preg_match('/^[A-Z0-9-]{6,30}$/', $_gaId)): ?>
    <?php /* Consent-gated: gtag.js onay verilene kadar YÜKLENMEZ (performans + KVKK).
             dataLayer + gtag fonksiyonu tanımlanır; gerçek script ve config'i
             cookie-consent.js, kullanıcı 'all' onayı verince window.__gaId'den ekler. */ ?>
    <script>window.__gaId=<?= json_encode($_gaId, JSON_UNESCAPED_SLASHES) ?>;window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());</script>
    <?php endif; ?>
<?php endif; ?>
