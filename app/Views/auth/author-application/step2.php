<?php \App\Core\View::layout('base'); ?>
<section class="author-app">
    <header class="aa-head">
        <p class="aa-progress">Adım <strong>2</strong> / 3</p>
        <h1>Uzmanlık & Motivasyon</h1>
        <p class="lead">Hangi konularda yazmayı planladığınızı belirtin.</p>
        <div class="aa-steps">
            <span class="aa-step is-done">1. Hakkınızda</span>
            <span class="aa-step is-active">2. Uzmanlık</span>
            <span class="aa-step">3. Örnek</span>
        </div>
        <?php require dirname(__DIR__, 2) . '/partials/flash.php'; ?>
    </header>

    <form method="post" action="<?= esc(url('/yazar-ol')) ?>" class="form aa-form">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="2">

        <label>
            <span>Uzmanlık Alanları</span>
            <input type="text" name="expertise" maxlength="500" required
                   placeholder="Örn: konut tasarımı, restorasyon, BIM"
                   value="<?= esc((string) ($state['data']['expertise'] ?? '')) ?>">
            <small class="muted">Virgülle ayrılmış birkaç anahtar konu. Profilinizde etiket olarak görünür.</small>
            <?= form_error('expertise') ?>
        </label>

        <label>
            <span>Neden burada yazmak istiyorsunuz?</span>
            <textarea name="motivation" rows="6" minlength="80" maxlength="2000" required
                      placeholder="Hangi tür içerikler üreteceksiniz, hedef okuyucu kim, ne sıklıkta yazmayı planlıyorsunuz?"><?= esc((string) ($state['data']['motivation'] ?? '')) ?></textarea>
            <small class="muted">80–2000 karakter. Editörlere bir mektup gibi düşünün.</small>
            <?= form_error('motivation') ?>
        </label>

        <div class="aa-actions">
            <button type="submit" name="direction" value="back" class="btn">← Geri</button>
            <button type="submit" name="direction" value="next" class="btn btn-primary">Devam et →</button>
        </div>
    </form>
</section>
