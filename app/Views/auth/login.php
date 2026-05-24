<?php \App\Core\View::layout('base'); ?>
<?php
$emailErr    = flash('error_email');
$passwordErr = flash('error_password');
?>
<section class="auth">
    <h1>Giriş Yap</h1>
    <?php require dirname(__DIR__) . '/partials/flash.php'; ?>
    <?php if ($emailErr): ?>
        <div class="flash flash-error" id="err-email"><?= esc($emailErr) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= esc(url('/giris')) ?>" class="form" novalidate>
        <?= csrf_field() ?>
        <label>
            <span>E-posta</span>
            <input type="email" name="email" required autocomplete="email"
                   value="<?= esc((string) old('email')) ?>"
                   <?= $emailErr ? 'aria-invalid="true" aria-describedby="err-email"' : '' ?>>
        </label>
        <label>
            <span>Parola</span>
            <input type="password" name="password" required autocomplete="current-password" minlength="8"
                   <?= $passwordErr ? 'aria-invalid="true"' : '' ?>>
        </label>
        <button type="submit" class="btn btn-primary">Giriş</button>
        <p class="muted">Hesabın yok mu? <a href="<?= esc(url('/kayit')) ?>">Kayıt ol</a></p>
    </form>
</section>
