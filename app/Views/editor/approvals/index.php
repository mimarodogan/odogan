<?php \App\Core\View::layout('base'); ?>
<?php
/** @var array<int,array<string,mixed>> $review_items */
/** @var array<int,array<string,mixed>> $approved_items */
?>
<section class="hero">
    <h1>Onay Süreci</h1>
    <p class="lead">Çok aşamalı yazı onayı: yazar → editör → admin. İncelenmek veya yayına alınmak üzere bekleyen yazılar.</p>
    <?php require dirname(__DIR__, 2) . '/partials/flash.php'; ?>
</section>

<section class="approval-stage">
    <h2 class="stage-title">
        Editör İncelemesi Bekleyen
        <span class="badge"><?= count($review_items) ?></span>
    </h2>
    <?php if (empty($review_items)): ?>
        <p class="muted">Sırada bekleyen yazı yok.</p>
    <?php else: ?>
        <ul class="approval-list">
            <?php foreach ($review_items as $p): ?>
                <li class="approval-row">
                    <div class="approval-meta">
                        <strong class="approval-title"><?= esc($p['title']) ?></strong>
                        <p class="muted">
                            <?= esc($p['author_name']) ?> · <?= esc($p['category_name']) ?>
                            <?php if (!empty($p['submitted_at'])): ?>
                                · gönderildi: <?= esc($p['submitted_at']) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="approval-actions">
                        <a class="btn btn-secondary btn-sm" href="<?= esc(url('/editor/onaylar/' . $p['id'])) ?>">İncele</a>
                        <form method="post" action="<?= esc(url('/editor/onaylar/' . $p['id'] . '/onayla')) ?>" style="display:inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-primary btn-sm">Onayla → Admin</button>
                        </form>
                        <form method="post" action="<?= esc(url('/editor/onaylar/' . $p['id'] . '/reddet')) ?>" style="display:inline"
                              onsubmit="this.elements.note.value = prompt('Revizyon notu (yazara iletilir):') || ''; if(!this.elements.note.value) return false;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="note" value="">
                            <button type="submit" class="btn btn-link">Revizyon İste</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="approval-stage">
    <h2 class="stage-title">
        Admin Final Onayı Bekleyen
        <span class="badge"><?= count($approved_items) ?></span>
    </h2>
    <?php if (empty($approved_items)): ?>
        <p class="muted">Sırada bekleyen yazı yok.</p>
    <?php else: ?>
        <ul class="approval-list">
            <?php foreach ($approved_items as $p): ?>
                <li class="approval-row">
                    <div class="approval-meta">
                        <strong class="approval-title"><?= esc($p['title']) ?></strong>
                        <p class="muted"><?= esc($p['author_name']) ?> · <?= esc($p['category_name']) ?></p>
                    </div>
                    <div class="approval-actions">
                        <a class="btn btn-secondary btn-sm" href="<?= esc(url('/editor/onaylar/' . $p['id'])) ?>">İncele</a>
                        <form method="post" action="<?= esc(url('/editor/onaylar/' . $p['id'] . '/yayinla')) ?>" style="display:inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-primary btn-sm">Yayına Al</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
