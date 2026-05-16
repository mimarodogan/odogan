<?php \App\Core\View::layout('base'); ?>
<section class="auth">
    <h1>Bağlantı Gönderildi</h1>
    <?php require dirname(__DIR__) . '/partials/flash.php'; ?>
    <p>
        Eğer bu e-posta sistemde kayıtlıysa, kısa süre içinde bir şifre
        sıfırlama bağlantısı alacaksınız. Bağlantı <strong>60 dakika</strong> geçerlidir
        ve yalnızca <strong>bir kez</strong> kullanılabilir.
    </p>
    <p class="muted">
        E-postayı görmüyor musunuz? Spam veya istenmeyen klasörüne göz atın.
        Sorun devam ederse <a href="<?= esc(url('/sifremi-unuttum')) ?>">tekrar talep edin</a>.
    </p>
    <p>
        <a class="btn" href="<?= esc(url('/giris')) ?>">← Giriş ekranına dön</a>
    </p>
</section>
