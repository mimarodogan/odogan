<?php \App\Core\View::layout('base'); ?>
<?php
/** @var array<string,mixed> $post */
/** @var array<int,array<string,mixed>> $history */
?>
<section class="hero">
    <p class="muted"><a href="<?= esc(url('/editor/onaylar')) ?>">← Onay Listesi</a></p>
    <h1><?= esc($post['title']) ?></h1>
    <p class="lead">
        Aşama: <strong><?= esc($post['approval_stage'] ?? 'none') ?></strong>
        · Durum: <?= esc($post['status'] ?? '—') ?>
    </p>
    <?php require dirname(__DIR__, 2) . '/partials/flash.php'; ?>
</section>

<article class="approval-preview">
    <?php if (!empty($post['cover_image'])): ?>
        <figure class="approval-cover">
            <img src="<?= esc($post['cover_image']) ?>" alt="" loading="lazy">
        </figure>
    <?php endif; ?>
    <?php if (!empty($post['excerpt'])): ?>
        <p class="approval-excerpt"><?= esc($post['excerpt']) ?></p>
    <?php endif; ?>
    <div class="approval-body prose">
        <?= $post['body'] /* trusted — yazar tarafında sanitize edildi */ ?>
    </div>
</article>

<section class="approval-history">
    <h2 class="stage-title">Onay Geçmişi</h2>
    <?php if (empty($history)): ?>
        <p class="muted">Henüz geçmiş kaydı yok.</p>
    <?php else: ?>
        <ol class="timeline">
            <?php foreach ($history as $h): ?>
                <li class="timeline-item">
                    <div class="timeline-marker"></div>
                    <div class="timeline-body">
                        <p class="timeline-meta">
                            <span class="timeline-when"><?= esc($h['created_at']) ?></span>
                            <strong><?= esc($h['stage']) ?></strong> ·
                            <span class="timeline-decision"><?= esc($h['decision']) ?></span>
                            <span class="muted">— <?= esc($h['reviewer_name'] ?? 'sistem') ?></span>
                        </p>
                        <?php if (!empty($h['note'])): ?>
                            <p class="timeline-note"><?= esc($h['note']) ?></p>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>
