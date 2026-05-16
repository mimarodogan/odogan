<?php \App\Core\View::layout('base'); ?>
<?php
$errs = [];
foreach (['name','email','password','password_confirm'] as $f) {
    $errs[$f] = flash('error_' . $f);
}
?>
<section class="auth">
    <h1>Kayıt Ol</h1>
    <?php require dirname(__DIR__) . '/partials/flash.php'; ?>
    <?php foreach ($errs as $key => $msg): if ($msg): ?>
        <div class="flash flash-error" id="err-<?= esc($key) ?>"><?= esc($msg) ?></div>
    <?php endif; endforeach; ?>
    <form method="post" action="<?= esc(url('/kayit')) ?>" class="form" novalidate>
        <?= csrf_field() ?>
        <label>
            <span>Adınız</span>
            <input type="text" name="name" required minlength="2" maxlength="120"
                   value="<?= esc((string) old('name')) ?>"
                   <?= $errs['name'] ? 'aria-invalid="true" aria-describedby="err-name"' : '' ?>>
        </label>
        <label>
            <span>E-posta</span>
            <input type="email" name="email" required autocomplete="email"
                   value="<?= esc((string) old('email')) ?>"
                   <?= $errs['email'] ? 'aria-invalid="true" aria-describedby="err-email"' : '' ?>>
        </label>
        <label>
            <span>Parola</span>
            <input type="password" name="password" required minlength="8" autocomplete="new-password"
                   <?= $errs['password'] ? 'aria-invalid="true" aria-describedby="err-password"' : '' ?>>
        </label>
        <label>
            <span>Parola (Tekrar)</span>
            <input type="password" name="password_confirm" required minlength="8" autocomplete="new-password"
                   <?= $errs['password_confirm'] ? 'aria-invalid="true" aria-describedby="err-password_confirm"' : '' ?>>
        </label>

        <?php
        $_termsDoc = \App\Models\LegalDocument::findBySlug('uyelik-sozlesmesi');
        $_privacyDoc = \App\Models\LegalDocument::findBySlug('gizlilik-politikasi');
        $termsErr = flash('error_terms');
        ?>
        <?php if ($_termsDoc || $_privacyDoc): ?>
        <label class="reg-agree" style="display:flex;align-items:flex-start;gap:.65rem;padding:1rem;background:var(--bone-2);border-left:2px solid var(--cobalt);margin-top:.5rem">
            <input type="checkbox" name="accept_terms" value="1" required
                   style="width:18px;height:18px;margin-top:.15rem;flex-shrink:0;accent-color:var(--cobalt)">
            <span style="font-family:var(--serif);font-size:.95rem;line-height:1.55;color:var(--soot-2)">
                <?php if ($_termsDoc): ?>
                    <a href="<?= esc(url('/sozlesmeler/' . $_termsDoc['slug'])) ?>" target="_blank" rel="noopener" style="color:var(--cobalt);text-decoration:underline"><?= esc($_termsDoc['title']) ?></a>'ni
                <?php else: ?>
                    Üyelik sözleşmesini
                <?php endif; ?>
                <?php if ($_privacyDoc): ?>
                    ve <a href="<?= esc(url('/sozlesmeler/' . $_privacyDoc['slug'])) ?>" target="_blank" rel="noopener" style="color:var(--cobalt);text-decoration:underline"><?= esc($_privacyDoc['title']) ?></a>'nı
                <?php endif; ?>
                okudum, kabul ediyorum.
            </span>
        </label>
        <?php if ($termsErr): ?>
            <div class="flash flash-error"><?= esc($termsErr) ?></div>
        <?php endif; ?>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary">Hesap Oluştur</button>
        <p class="muted">Zaten üye misin? <a href="<?= esc(url('/giris')) ?>">Giriş yap</a></p>
    </form>
</section>
