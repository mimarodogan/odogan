<?php \App\Core\View::layout('base'); ?>
<?php
$emailErr = flash('error_email');
?>
<section class="auth">
    <h1>Şifremi Unuttum</h1>
    <?php require dirname(__DIR__) . '/partials/flash.php'; ?>
    <p class="muted">
        Hesabınıza kayıtlı e-posta adresini girin. Eğer bu adres kayıtlıysa
        şifre sıfırlama bağlantısı gönderilir (link 60 dakika geçerli).
    </p>
    <?php if ($emailErr): ?>
        <div class="flash flash-error" id="err-email"><?= esc($emailErr) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= esc(url('/sifremi-unuttum')) ?>" class="form" novalidate>
        <?= csrf_field() ?>
        <label>
            <span>E-posta</span>
            <input type="email" name="email" required autocomplete="email"
                   value="<?= esc((string) old('email')) ?>"
                   <?= $emailErr ? 'aria-invalid="true" aria-describedby="err-email"' : '' ?>>
        </label>
        <button type="submit" class="btn btn-primary">Bağlantı Gönder</button>
        <p class="muted">
            Şifrenizi hatırladınız mı? <a href="<?= esc(url('/giris')) ?>">Giriş yap</a>
        </p>
    </form>
</section>
