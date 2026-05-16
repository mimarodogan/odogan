<?php \App\Core\View::layout('base'); ?>
<?php
/** @var string $token */
$pwdErr     = flash('error_new_password');
$confirmErr = flash('error_new_password_confirm');
?>
<section class="auth">
    <h1>Yeni Şifre Belirle</h1>
    <?php require dirname(__DIR__) . '/partials/flash.php'; ?>
    <p class="muted">
        Lütfen en az 8 karakterli yeni bir şifre belirleyin. Şifreyi kaydettikten
        sonra aktif tüm oturumlarınız kapatılacak ve yeni şifrenizle giriş yapmanız istenecektir.
    </p>
    <?php if ($pwdErr): ?>
        <div class="flash flash-error"><?= esc($pwdErr) ?></div>
    <?php endif; ?>
    <?php if ($confirmErr): ?>
        <div class="flash flash-error"><?= esc($confirmErr) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= esc(url('/sifre-sifirla/' . $token)) ?>" class="form" novalidate>
        <?= csrf_field() ?>
        <label>
            <span>Yeni Şifre</span>
            <input type="password" name="new_password" required autocomplete="new-password" minlength="8"
                   <?= $pwdErr ? 'aria-invalid="true"' : '' ?>>
        </label>
        <label>
            <span>Yeni Şifre (tekrar)</span>
            <input type="password" name="new_password_confirm" required autocomplete="new-password" minlength="8"
                   <?= $confirmErr ? 'aria-invalid="true"' : '' ?>>
        </label>
        <button type="submit" class="btn btn-primary">Şifreyi Güncelle</button>
    </form>
</section>
