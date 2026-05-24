<?php \App\Core\View::layout('base'); ?>

<?= breadcrumbs_html($breadcrumbs ?? []) ?>

<section class="page-saved">
    <header class="page-saved-head">
        <h1>Kaydedilen Yazılar</h1>
        <p class="muted">Daha sonra okumak için kaydettiğin yazılar — yalnızca bu cihazda görünür.</p>
    </header>

    <noscript>
        <p class="flash flash-warn">
            Kaydedilenleri görmek için JavaScript gerekiyor. Liste tarayıcının LocalStorage'ında tutulur.
        </p>
    </noscript>

    <div data-saved-list class="page-saved-list" aria-live="polite" aria-busy="true">
        <p class="muted" style="text-align:center;padding:3rem 0">Yükleniyor…</p>
    </div>
</section>
