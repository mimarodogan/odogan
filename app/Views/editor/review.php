<?php \App\Core\View::layout('base'); ?>
<section class="hero">
    <h1><?= esc($post['title']) ?></h1>
    <p class="lead muted">
        Yazar: <?= esc((string) ($author['name'] ?? '—')) ?>
        · Durum: <strong><?= esc(ucfirst((string) $post['status'])) ?></strong>
    </p>
    <?php require dirname(__DIR__) . '/partials/flash.php'; ?>
</section>

<article class="post-body">
    <?= $preview_html ?>
</article>

<?php if ($faq): ?>
<section class="author-section">
    <h2>SSS Öngörüsü</h2>
    <dl class="faq">
        <?php foreach ($faq as $row): ?>
            <dt><?= esc($row['q']) ?></dt>
            <dd><?= esc($row['a']) ?></dd>
        <?php endforeach; ?>
    </dl>
</section>
<?php endif; ?>

<section class="author-section">
    <h2>Editör Aksiyonu</h2>
    <div class="grid">
        <form method="post" action="<?= esc(url('/editor/onay/' . $post['id'] . '/onayla')) ?>" class="card">
            <?= csrf_field() ?>
            <h3>Onayla & Yayınla</h3>
            <label><span>Editör notu (opsiyonel)</span>
                <textarea name="note" rows="2" maxlength="500"></textarea>
            </label>
            <button class="btn btn-primary" type="submit">Yayınla</button>
        </form>

        <form method="post" action="<?= esc(url('/editor/onay/' . $post['id'] . '/reddet')) ?>" class="card">
            <?= csrf_field() ?>
            <h3>Revizyon İste</h3>
            <label><span>Sebep (yazara iletilir)</span>
                <textarea name="reason" rows="3" maxlength="500" required></textarea>
            </label>
            <button class="btn" type="submit">Geri Gönder</button>
        </form>
    </div>
</section>
