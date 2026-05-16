<?php
/**
 * @var string $title
 *
 * Login sırasında 2FA aktif kullanıcının 6-haneli kod girdiği ekran.
 * Pending state AuthService::pendingTotpUserId() ile tutulur.
 */
\App\Core\View::layout('base');
?>
<section class="auth-page">
    <div class="auth-card">
        <h1>İki Adımlı Doğrulama</h1>
        <p class="muted">
            Authenticator uygulamanızdaki 6 haneli kodu girin. Kodlar 30 saniyede bir yenilenir.
        </p>

        <?php if ($e = flash('error_code')): ?>
            <div class="flash flash-error" role="alert"><?= esc($e) ?></div>
        <?php endif; ?>
        <?php if ($e = flash('error_email')): ?>
            <div class="flash flash-error" role="alert"><?= esc($e) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= esc(url('/giris/dogrulama')) ?>" class="form form-wide">
            <?= csrf_field() ?>
            <label>
                <span>Doğrulama Kodu</span>
                <input type="text"
                       name="code"
                       inputmode="numeric"
                       pattern="[0-9]{6}|[A-Z0-9\-]{8,12}"
                       autocomplete="one-time-code"
                       autofocus required
                       maxlength="12"
                       placeholder="6 haneli kod veya recovery kodu"
                       style="font-family:monospace;font-size:1.5rem;letter-spacing:.3em;text-align:center">
                <small class="muted">
                    Telefonunuza erişiminiz yoksa <strong>recovery kodu</strong> (XXXX-XXXXXX formatlı) kullanabilirsiniz.
                </small>
            </label>
            <button class="btn btn-primary" type="submit">Doğrula ve Giriş Yap</button>
        </form>

        <p style="margin-top:1.5rem">
            <a href="<?= esc(url('/giris')) ?>" class="muted" title="Yeniden giriş ekranı">← Yeniden giriş yap</a>
        </p>
    </div>
</section>
