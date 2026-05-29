<?php
/**
 * Ana layout iskeleti. Sadece scope hesaplaması yapar; tüm büyük HTML
 * blokları partials/layout/ altında yaşar.
 *
 * @var string|null $title
 * @var string|null $description
 * @var array|null  $seo
 * @var string|null $schema_jsonld
 * @var string|null $head_extra
 * @var string|null $page_type
 * @var string|null $css_extra
 * @var array|null  $body_extra_js
 */

// — SEO args (per-page override vs. helper'dan üretim) —
$seoArgs = is_array($seo ?? null) ? $seo : [
    'title'       => $title ?? null,
    'description' => $description ?? null,
    'url'         => isset($canonical) ? $canonical : ($_SERVER['REQUEST_URI'] ?? null),
    'type'        => $page_type ?? 'website',
    // Controller'dan gelen og:image (opsiyonel) — article controller post cover'ı set eder
    'image'       => !empty($image) ? (string) $image : null,
    // Robots meta — noindex, nofollow gibi değerler için (örn. auth sayfaları)
    'robots'      => !empty($robots) ? (string) $robots : null,
];
if (!empty($seoArgs['url']) && !preg_match('#^https?://#i', (string) $seoArgs['url'])) {
    $seoArgs['url'] = absolute_url((string) $seoArgs['url']);
}

// — Admin context tespiti —
$_adminUri  = (string) ($_SERVER['REQUEST_URI'] ?? '');
$_adminAuth = \App\Services\AuthService::check();
$_inAdmin   = $_adminAuth && (
    str_starts_with($_adminUri, '/panel') ||
    str_starts_with($_adminUri, '/admin') ||
    str_starts_with($_adminUri, '/editor')
);
$_adminUser  = $_inAdmin ? \App\Services\AuthService::user() : null;
$_adminRole  = $_adminUser['role'] ?? 'guest';
$_isAdmin    = in_array($_adminRole, ['admin'], true);
$_isEditor   = in_array($_adminRole, ['admin', 'editor'], true);
$_roleLabels = ['admin' => 'YÖNETİCİ', 'editor' => 'EDİTÖR', 'author' => 'YAZAR'];
$_roleLabel  = $_roleLabels[$_adminRole] ?? mb_strtoupper((string) $_adminRole);
$_curUri     = $_adminUri;
$_active     = static function (string $prefix) use ($_curUri): string {
    return str_starts_with($_curUri, $prefix) ? ' aria-current="page"' : '';
};

$_partials = __DIR__ . '/../partials/layout';
?>
<!doctype html>
<html lang="<?= esc((string) \App\Core\Config::get('APP_LOCALE', 'tr')) ?>"<?= $_inAdmin ? ' data-admin' : '' ?>>
<head>
<?php require $_partials . '/head-meta.php'; ?>
</head>
<?php
// Public sayfalar için aktif feature flag'leri body'ye data-attr ile geçir
// (JS bunları okuyarak özellik davranışını adapte eder).
$_bodyAttrs = '';
if ($_inAdmin) {
    $_bodyAttrs .= ' class="admin-mode"';
} else {
    if (function_exists('feature') && feature('lightbox_zoom_enabled')) {
        $_bodyAttrs .= ' data-lightbox-zoom="1"';
    }
}
?>
<body<?= $_bodyAttrs ?>>
<a class="skip-link" href="#content" title="Ana içeriğe atla">İçeriğe atla</a>

<?php if ($_inAdmin): ?>
    <?php require $_partials . '/admin-bar.php'; ?>
    <div class="admin-shell">
        <div class="admin-backdrop" hidden></div>
        <?php require $_partials . '/admin-sidebar.php'; ?>
        <main class="admin-main" id="content">
            <?= \App\Core\View::yield('content') ?>
        </main>
    </div>
<?php else: ?>
    <?php require $_partials . '/public-header.php'; ?>
    <main class="container main" id="content">
        <?= \App\Core\View::yield('content') ?>
    </main>
    <?php require $_partials . '/public-footer.php'; ?>
    <button type="button" class="back-to-top" aria-label="Sayfa başına dön" title="Yukarı çık" hidden>
        <span aria-hidden="true">↑</span>
    </button>
<?php endif; ?>

<?php if ($_inAdmin) require $_partials . '/admin-drawer-script.php'; ?>
<script src="<?= esc(\App\Services\AssetMinifier::asset('assets/js/app.js')) ?>" defer></script>
<?php if (!$_inAdmin && function_exists('feature') && feature('pwa_enabled')): ?>
<script nonce="<?= esc(csp_nonce()) ?>">
(function(){
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function(){
      navigator.serviceWorker.register('<?= esc(url('/sw.js')) ?>', { scope: '/' }).catch(function(){});
    });
  }
})();
</script>
<?php endif; ?>
<?php
// BlurHash decoder — feature aktifse global yüklenir (her sayfadaki picture'leri yakalar).
if (!$_inAdmin && function_exists('feature') && feature('blurhash_enabled')): ?>
    <script src="<?= esc(\App\Services\AssetMinifier::asset('assets/js/blurhash-decode.js')) ?>" defer></script>
<?php endif; ?>
<?php
// Saved-post feature: header'daki badge counter her sayfada güncellenir.
// /kaydedilenler sayfası body_extra_js olarak da yüklediğinden çift yüklemeyi önlemek için
// $body_extra_js array'inde zaten varsa burada atla.
if (!$_inAdmin && function_exists('feature') && feature('save_post_enabled')):
    $_savePostSrc = \App\Services\AssetMinifier::asset('assets/js/save-post.js');
    $_alreadyIncluded = false;
    if (!empty($body_extra_js)) {
        foreach ((array) $body_extra_js as $_s) {
            if (strpos((string) $_s, 'save-post') !== false) {
                $_alreadyIncluded = true;
                break;
            }
        }
    }
    if (!$_alreadyIncluded): ?>
    <script src="<?= esc($_savePostSrc) ?>" defer></script>
    <?php endif; ?>
<?php endif; ?>
<?php if (!empty($body_extra_js)) foreach ((array) $body_extra_js as $src): ?>
    <script src="<?= esc($src) ?>" defer></script>
<?php endforeach; ?>

<?php
// KVKK / GDPR çerez onay banner'ı — yalnızca public sayfalarda (K3).
// Banner kendi içinde `privacy.cookie_banner_enabled` kontrol ediyor;
// kapalıysa hiç render edilmez.
if (!$_inAdmin):
    require __DIR__ . '/../partials/cookie-consent.php';
?>
<script src="<?= esc(\App\Services\AssetMinifier::asset('assets/js/cookie-consent.js')) ?>" defer></script>
<?php endif; ?>

<?php
// Hover prefetch — kullanıcı linke hover olunca hedef sayfayı önyükle (O6).
// Feature flag arkasında — Settings'ten açılır.
if (!$_inAdmin && function_exists('feature') && feature('prefetch_on_hover_enabled')): ?>
<script src="<?= esc(\App\Services\AssetMinifier::asset('assets/js/prefetch-hover.js')) ?>" defer></script>
<?php endif; ?>
</body>
</html>
