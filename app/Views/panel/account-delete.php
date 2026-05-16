<?php \App\Core\View::layout('base'); ?>
<section class="hero post-editor-hero">
    <div>
        <p class="post-editor-meta">
            <a href="<?= esc(url('/panel/profil')) ?>" class="muted">← Profile Dön</a>
        </p>
        <h1 style="color:#B0241D">Hesabımı Sil — 1. Adım</h1>
        <p class="post-editor-meta">
            <span class="badge badge-rejected">İki adımlı doğrulama</span>
            <span class="muted">·</span>
            <span class="muted">Mail kodu gerekli</span>
        </p>
    </div>
    <?php require dirname(__DIR__) . '/partials/flash.php'; ?>
</section>

<div class="info-card" style="border-left-color:#B0241D">
    <p><strong>Bu işlem geri alınamaz.</strong> Hesabınız kapatılır, tekrar giriş yapamazsınız.</p>
    <p class="muted">Yazdığınız içerikler korunur — sadece kullanıcı oturumunuz devre dışı kalır. Yazar adı yazıların altında görünmeye devam eder.</p>
</div>

<form method="post" action="<?= esc(url('/panel/hesap/sil')) ?>" class="form form-wide">
    <?= csrf_field() ?>

    <fieldset class="form-group">
        <legend>Onay</legend>
        <label>
            <span>Mevcut Şifre *</span>
            <input type="password" name="password" required autocomplete="current-password">
            <small class="muted">Hesabınızın doğru sahibi olduğunuzu doğrulamak için.</small>
        </label>
        <label>
            <span>Silme Sebebi (opsiyonel)</span>
            <textarea name="reason" rows="3" maxlength="255" placeholder="Geri bildirim için isteğe bağlı"></textarea>
            <small class="muted">Sadece audit log'a düşer, kimseyle paylaşılmaz.</small>
        </label>
    </fieldset>

    <div class="info-card">
        <p>
            <strong>Sıradaki adım:</strong> E-postanıza 6-haneli doğrulama kodu gönderilir.
            10 dakika içinde kodu girip son onayı verebilirsiniz.
        </p>
        <p class="muted">Bu süre içinde fikrinizi değiştirirseniz "İsteği İptal Et" diyebilirsiniz — hiçbir veri silinmez.</p>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-danger">Doğrulama Kodu Gönder</button>
        <a href="<?= esc(url('/panel/profil')) ?>" class="btn btn-secondary">Vazgeç</a>
    </div>
</form>
