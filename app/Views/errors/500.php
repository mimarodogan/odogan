<?php
/**
 * 500 — Sunucu Hatası
 * Atelier design system
 */
\App\Core\View::layout('base');
?>

<section class="err-500" aria-labelledby="err500-heading">
    <p class="err-eyebrow">Sunucu Hatası</p>
    <span class="err-code" aria-hidden="true">500</span>
    <h1 id="err500-heading">Beklenmeyen bir hata oluştu.</h1>
    <p>Sunucumuzda geçici bir sorun var. Lütfen birkaç dakika sonra tekrar deneyin.</p>
    <nav class="err-nav">
        <a class="btn btn-primary" href="<?= esc(url('/')) ?>">← Ana Sayfa</a>
        <a class="btn" href="javascript:history.back()">Geri Dön</a>
    </nav>
</section>
