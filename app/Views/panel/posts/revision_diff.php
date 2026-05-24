<?php \App\Core\View::layout('base'); ?>
<section class="hero">
    <h1>Sürüm #<?= (int) $revision['id'] ?></h1>
    <p class="lead muted"><?= esc(tr_date($revision['created_at'], true)) ?> · <?= esc($revision['title']) ?></p>
    <p>
        <a class="btn" href="<?= esc(url('/panel/yazilar/' . $post['id'] . '/surumler')) ?>">← Geçmişe dön</a>
        <form method="post" action="<?= esc(url('/panel/yazilar/' . $post['id'] . '/surumler/' . $revision['id'] . '/geri-yukle')) ?>"
              onsubmit="return confirm('Geri yüklensin mi?');" style="display:inline">
            <?= csrf_field() ?>
            <button class="btn btn-primary" type="submit">Bu Sürümü Geri Yükle</button>
        </form>
    </p>
</section>

<div class="grid two-col" style="margin-top:1rem">
    <article class="card">
        <h2>Bu Sürüm</h2>
        <p class="muted">Format: <code><?= esc((string) $revision['body_format']) ?></code></p>
        <?php if (!empty($revision['excerpt'])): ?>
            <p><strong>Özet:</strong> <?= esc((string) $revision['excerpt']) ?></p>
        <?php endif; ?>
        <pre class="log-ctx" style="max-width:none"><?= esc((string) $revision['body']) ?></pre>
    </article>

    <article class="card">
        <h2>Şu Anki Hâl</h2>
        <p class="muted">Format: <code><?= esc((string) $post['body_format']) ?></code></p>
        <?php if (!empty($post['excerpt'])): ?>
            <p><strong>Özet:</strong> <?= esc((string) $post['excerpt']) ?></p>
        <?php endif; ?>
        <pre class="log-ctx" style="max-width:none"><?= esc((string) $post['body']) ?></pre>
    </article>
</div>
