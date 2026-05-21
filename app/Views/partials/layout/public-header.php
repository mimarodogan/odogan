<?php
/**
 * Public site header — marka + tagline + primary nav.
 *
 * @var bool $_adminAuth
 */
$_brandName = (string) \App\Models\Setting::get('site_name', \App\Core\Config::get('APP_NAME', 'Otorite Yayin'));
$_brandTag  = (string) \App\Models\Setting::get('site_tagline', '');
?>
<header class="site-header">
    <div class="container">
        <div class="brand-group">
            <a class="brand" href="<?= esc(url('/')) ?>" title="<?= esc($_brandName) ?> · Anasayfa">
                <span class="brand-name"><?= esc($_brandName) ?></span>
                <?php if ($_brandTag !== ''): ?>
                    <span class="brand-tag"><?= esc($_brandTag) ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= esc(url('/ara')) ?>" class="brand-search-btn"
               title="Site içinde ara" aria-label="Arama">
                <span aria-hidden="true">⌕</span>
                <span class="visually-hidden">Arama</span>
            </a>
        </div>
        <button type="button" class="nav-toggle" aria-label="Menü" aria-controls="primary-nav" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
        <nav id="primary-nav" class="nav" aria-label="Ana navigasyon">
            <a href="<?= esc(url('/')) ?>" title="Anasayfa">Ana Sayfa</a>
            <a href="<?= esc(url('/kategoriler')) ?>" title="Tüm kategoriler ve yazı sayıları">Kategoriler</a>
            <a href="<?= esc(url('/yazarlar')) ?>" title="Yazarlar listesi">Yazarlar</a>
            <?php if (function_exists('feature') && feature('project_portfolio_enabled')): ?>
                <a href="<?= esc(url('/projeler')) ?>" title="Mimari proje portfolyosu">Projeler</a>
            <?php endif; ?>
            <?php
            // Kaydedilenler — sadece GİRİŞ YAPMIŞ üyelere göster.
            // (Misafir ziyaretçi çerezini temizleyince hepsi kaybolur, yanlış UX olur.)
            if (function_exists('feature') && feature('save_post_enabled')
                && \App\Services\AuthService::check()): ?>
                <a href="<?= esc(url('/kaydedilenler')) ?>" title="Kaydedilen yazılar (bu cihazda)" class="nav-saved">
                    <span aria-hidden="true">♡</span>
                    <span>Kaydedilenler</span>
                    <span class="saved-badge" data-saved-count hidden></span>
                </a>
            <?php endif; ?>
            <?php if (function_exists('feature') && feature('glossary_enabled')): ?>
                <a href="<?= esc(url('/sozluk')) ?>" title="Mimari sözlük">Sözlük</a>
            <?php endif; ?>
            <?php
            // Bağış butonu — Settings'te URL tanımlanmışsa görünür
            if (function_exists('feature') && feature('donation_enabled')) {
                $_donateUrl = trim((string) \App\Models\Setting::get('donation_url', '', 'general'));
                if ($_donateUrl !== ''): ?>
                <a href="<?= esc($_donateUrl) ?>" class="nav-donate" target="_blank" rel="noopener" title="Bağış yap">
                    <span aria-hidden="true">♡</span>
                    <span>Destek Ol</span>
                </a>
            <?php endif; } ?>
            <?php
            // Yazar başvuru linki — feature aktif + giriş yapmış MEMBER kullanıcı için
            $_userRole = (string) (\App\Services\AuthService::user()['role'] ?? '');
            $_canApply = function_exists('feature') && feature('author_application')
                && \App\Services\AuthService::check()
                && $_userRole === \App\Models\User::ROLE_MEMBER;
            ?>
            <?php if ($_canApply): ?>
                <a href="<?= esc(url('/yazar-ol')) ?>" title="Yazar olmak için başvur">Yazar Ol</a>
            <?php endif; ?>
            <?php if ($_adminAuth): ?>
                <a href="<?= esc(url('/panel')) ?>" title="Yönetim paneli">Panel</a>
            <?php elseif (\App\Services\AuthService::check()): ?>
                <a href="<?= esc(url('/panel')) ?>" title="Hesabım">Hesabım</a>
            <?php else: ?>
                <a href="<?= esc(url('/giris')) ?>" title="Üye girişi">Giriş</a>
                <a href="<?= esc(url('/kayit')) ?>" title="Yeni hesap oluştur">Kayıt Ol</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
