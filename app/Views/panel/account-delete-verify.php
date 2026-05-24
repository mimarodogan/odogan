<?php
/** @var array $user */
/** @var string $expires_at */
\App\Core\View::layout('base');
?>
<section class="hero post-editor-hero">
    <div>
        <p class="post-editor-meta">
            <a href="<?= esc(url('/panel/profil')) ?>" class="muted">← Profile Dön</a>
        </p>
        <h1 style="color:#B0241D">Hesabımı Sil — 2. Adım</h1>
        <p class="post-editor-meta">
            <span class="badge badge-pending">Kod Doğrulama</span>
            <span class="muted">·</span>
            <span class="muted">Son onay aşaması</span>
        </p>
    </div>
    <?php require dirname(__DIR__) . '/partials/flash.php'; ?>
</section>

<div class="info-card" style="border-left-color:#B0241D">
    <p>
        <strong>E-postanıza 6-haneli kod gönderildi.</strong>
        <code><?= esc((string) $user['email']) ?></code> adresini kontrol edin.
    </p>
    <p class="muted">
        Kod <strong><?= esc(substr($expires_at, 0, 16)) ?></strong> tarihine kadar geçerlidir.
        Bu süre dolarsa yeni bir kod isteyebilirsiniz.
    </p>
</div>

<form method="post" action="<?= esc(url('/panel/hesap/sil/dogrula')) ?>" class="form form-wide">
    <?= csrf_field() ?>

    <fieldset class="form-group">
        <legend>Doğrulama</legend>
        <label>
            <span>6 Haneli Kod *</span>
            <input type="text"
                   name="code"
                   required
                   inputmode="numeric"
                   pattern="\d{6}"
                   maxlength="6"
                   autocomplete="one-time-code"
                   placeholder="123456"
                   style="font-family:var(--mono);font-size:1.5rem;letter-spacing:.3em;text-align:center">
            <small class="muted">E-postadaki kod.</small>
        </label>
        <label>
            <span>Onay için "SİL" yazın *</span>
            <input type="text" name="confirm_text" required placeholder="SİL" pattern="(?i)^sil$">
        </label>
    </fieldset>

    <div class="info-card" style="border-left-color:#B0241D">
        <p><strong>Son uyarı:</strong> Bu butona basınca hesabınız kapatılır ve oturumunuz sonlandırılır. Geri alınamaz.</p>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-danger">Hesabımı Şimdi Sil</button>
    </div>
</form>

<form method="post" action="<?= esc(url('/panel/hesap/sil/iptal')) ?>" class="form" style="margin-top:1rem">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-link">İsteği İptal Et</button>
    <span class="muted" style="font-size:.85rem">— hiçbir veri silinmez, doğrulama kodu geçersiz olur</span>
</form>
