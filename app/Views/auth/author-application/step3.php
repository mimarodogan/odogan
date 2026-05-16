<?php \App\Core\View::layout('base'); ?>
<section class="author-app">
    <header class="aa-head">
        <p class="aa-progress">Adım <strong>3</strong> / 3</p>
        <h1>Örnek Yazı</h1>
        <p class="lead">Daha önce yayınladığınız bir yazıyı paylaşın veya buraya bir örnek metin yapıştırın.</p>
        <div class="aa-steps">
            <span class="aa-step is-done">1. Hakkınızda</span>
            <span class="aa-step is-done">2. Uzmanlık</span>
            <span class="aa-step is-active">3. Örnek</span>
        </div>
        <?php require dirname(__DIR__, 2) . '/partials/flash.php'; ?>
    </header>

    <form method="post" action="<?= esc(url('/yazar-ol')) ?>" class="form aa-form">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="3">

        <label>
            <span>Örnek Yazı URL'si (önerilen)</span>
            <input type="url" name="sample_url" maxlength="500"
                   placeholder="https://blog.com/yazim"
                   value="<?= esc((string) ($state['data']['sample_url'] ?? '')) ?>">
            <small class="muted">Daha önce yayınladığınız bir yazıya bağlantı.</small>
            <?= form_error('sample_url') ?>
        </label>

        <label>
            <span>… veya buraya yazınızı yapıştırın</span>
            <textarea name="sample_text" rows="10" maxlength="6000"
                      placeholder="En az 200 karakterlik bir örnek metin paste edin. Markdown / düz metin kabul edilir."><?= esc((string) ($state['data']['sample_text'] ?? '')) ?></textarea>
            <small class="muted">URL girmediyseniz buraya 200+ karakterlik bir örnek metin yapıştırın.</small>
            <?= form_error('sample') ?>
        </label>

        <?php $_writerDoc = \App\Models\LegalDocument::findBySlug('yazar-sozlesmesi'); ?>
        <?php if ($_writerDoc): ?>
        <div class="aa-contract" style="padding:1.25rem 1.5rem;background:var(--bone-2);border:1px solid var(--hair);max-height:280px;overflow-y:auto;margin-top:.5rem">
            <p style="font-family:var(--mono);font-size:.66rem;letter-spacing:var(--tracked-l);text-transform:uppercase;color:var(--ash);margin:0 0 .85rem;font-weight:700">YAZAR SÖZLEŞMESİ · v<?= (int) $_writerDoc['version'] ?></p>
            <div style="font-family:var(--serif);font-size:.92rem;line-height:1.55;color:var(--soot-2)">
                <?= $_writerDoc['body_html'] ?>
            </div>
        </div>
        <?php endif; ?>

        <label class="aa-agree">
            <input type="checkbox" name="agree" value="1"
                   <?= !empty($state['data']['agree']) ? 'checked' : '' ?>>
            <span>
                <?php if ($_writerDoc): ?>
                    Yukarıdaki <strong><?= esc($_writerDoc['title']) ?></strong>'ni (yayın yönergeleri ve telif şartları dahil) okudum ve kabul ediyorum.
                <?php else: ?>
                    Yayın yönergelerini ve telif şartlarını okuduğumu, gönderdiğim yazılarda yer alan görsel
                    ve metnin telifinin bana ait olduğunu kabul ediyorum.
                <?php endif; ?>
            </span>
            <?= form_error('agree') ?>
        </label>

        <div class="aa-actions">
            <button type="submit" name="direction" value="back" class="btn">← Geri</button>
            <button type="submit" name="direction" value="next" class="btn btn-primary">📤 Başvuruyu Gönder</button>
        </div>
    </form>
</section>
