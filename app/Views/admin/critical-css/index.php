<?php \App\Core\View::layout('base'); ?>
<?php
/** @var string $content */
/** @var bool $enabled */
?>
<section class="hero">
    <h1>Critical CSS</h1>
    <p class="lead">İlk render için zorunlu CSS'i <code>&lt;head&gt;</code> içine inline'la — geri kalan stylesheet <code>rel=preload</code> ile lazy yüklenir. Core Web Vitals (LCP, FCP) iyileşir.</p>
    <?php require dirname(__DIR__, 2) . '/partials/flash.php'; ?>
</section>

<div class="info-card">
    <?php if ($enabled): ?>
        <p><span class="badge badge-accent">Aktif</span> Bu içerik anasayfa ve yazı sayfalarının <code>&lt;head&gt;</code> bölümünde inline yayınlanıyor.</p>
    <?php else: ?>
        <p><span class="badge badge-muted">Pasif</span> <a href="<?= esc(url('/admin/ozellikler')) ?>">Özellikler ekranından</a> <strong>Critical CSS</strong> bayrağını açın.</p>
    <?php endif; ?>
    <p class="muted">Sınır: 100 KB · Mevcut: <strong><?= number_format(mb_strlen($content)) ?> karakter</strong></p>
</div>

<form method="post" class="form form-wide" action="<?= esc(url('/admin/critical-css/kaydet')) ?>">
    <?= csrf_field() ?>

    <label>
        <span>Critical CSS (üst-fold için kritik kurallar)</span>
        <textarea name="content" rows="22" spellcheck="false" class="code-textarea"><?= esc($content) ?></textarea>
        <small class="muted">Üretmek için: <a href="https://www.sitelocity.com/critical-path-css-generator" target="_blank" rel="noopener">sitelocity.com</a> veya <a href="https://criticalcss.com" target="_blank" rel="noopener">criticalcss.com</a> kullanılabilir. Manuel: <code>tokens.css</code> + <code>header-nav.css</code> + <code>hero.css</code> birleştirip minify edin.</small>
    </label>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Kaydet</button>
    </div>
</form>
