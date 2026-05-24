<?php \App\Core\View::layout('base'); ?>
<section class="author-app">
    <header class="aa-head">
        <p class="aa-progress">Adım <strong>1</strong> / 3</p>
        <h1>Yazar Olmak için Başvuru</h1>
        <p class="lead">Sizi tanıyalım. Bu bilgiler kabul edildikten sonra profil sayfanızda da görünecek.</p>
        <div class="aa-steps">
            <span class="aa-step is-active">1. Hakkınızda</span>
            <span class="aa-step">2. Uzmanlık</span>
            <span class="aa-step">3. Örnek</span>
        </div>
        <?php require dirname(__DIR__, 2) . '/partials/flash.php'; ?>
    </header>

    <form method="post" action="<?= esc(url('/yazar-ol')) ?>" class="form aa-form">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="1">

        <label>
            <span>Kısa Tanıtım (Headline)</span>
            <input type="text" name="headline" minlength="10" maxlength="160" required
                   placeholder="Örn: 20 yıllık deneyimli mimar · sürdürülebilir yapı tasarımı"
                   value="<?= esc((string) ($state['data']['headline'] ?? '')) ?>">
            <small class="muted">10–160 karakter. Profilinizde başlık olarak görünür.</small>
            <?= form_error('headline') ?>
        </label>

        <label>
            <span>Biyografi</span>
            <textarea name="bio" rows="6" minlength="80" maxlength="2000" required
                      placeholder="Eğitim, kariyer, yayınlar, projeler — sizi yansıtan kısa ve net bir özet."><?= esc((string) ($state['data']['bio'] ?? '')) ?></textarea>
            <small class="muted">80–2000 karakter. Yazılarınızın altında ve profil sayfanızda görünecek.</small>
            <?= form_error('bio') ?>
        </label>

        <div class="aa-actions">
            <a class="btn" href="<?= esc(url('/')) ?>">İptal</a>
            <button type="submit" name="direction" value="next" class="btn btn-primary">Devam et →</button>
        </div>
    </form>
</section>
