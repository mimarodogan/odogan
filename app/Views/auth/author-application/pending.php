<?php \App\Core\View::layout('base'); ?>
<section class="author-app aa-thanks">
    <header class="aa-head">
        <h1>📋 Başvurunuz İncelemede</h1>
        <p class="lead">Merhaba <strong><?= esc($user['name'] ?? '') ?></strong>, daha önce gönderdiğiniz başvuru hâlâ inceleniyor.</p>
    </header>

    <div class="aa-block">
        <p>
            Editör ekibimiz başvurunuzu değerlendiriyor. Sonuçlandığında size e-posta ile haber vereceğiz.
            Süreç genellikle 3–5 iş günü sürer.
        </p>
    </div>

    <p style="margin-top:2rem">
        <a class="btn btn-primary" href="<?= esc(url('/')) ?>">Anasayfaya Dön</a>
    </p>
</section>
