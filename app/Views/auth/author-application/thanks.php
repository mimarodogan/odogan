<?php \App\Core\View::layout('base'); ?>
<section class="author-app aa-thanks">
    <header class="aa-head">
        <h1>🎉 Başvurunuz Alındı</h1>
        <p class="lead">Teşekkürler! Başvurunuzu editöryal ekibimiz inceleyecek.</p>
    </header>

    <div class="aa-block">
        <p>
            Genellikle 3–5 iş günü içinde sonuçlanır. Onaylandığında size e-posta ile haber vereceğiz
            ve hesabınız otomatik olarak yazar yetkisine sahip olacak.
        </p>
        <p>
            O zamana kadar üye olarak siteyi gezebilir, yazıları okuyabilir, yorum yapabilirsiniz.
        </p>
    </div>

    <p style="margin-top:2rem">
        <a class="btn btn-primary" href="<?= esc(url('/')) ?>">Anasayfaya Dön</a>
        <a class="btn" href="<?= esc(url('/panel')) ?>">Hesabıma Git</a>
    </p>
</section>
