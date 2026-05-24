<?php \App\Core\View::layout('base'); ?>
<?= breadcrumbs_html($breadcrumbs ?? []) ?>
<section class="hero">
    <h1><?= esc($category['name']) ?></h1>
    <?php if (!empty($category['description'])): ?>
        <p class="lead"><?= esc($category['description']) ?></p>
    <?php endif; ?>
</section>

<?php if (!$posts): ?>
    <p class="muted">Bu kategoride henüz yayımlanmış içerik yok.</p>
<?php else: ?>
<section class="mag-grid mag-grid-fill">
    <?php foreach ($posts as $_idx => $p): $u = url('/' . $category['slug'] . '/' . $p['slug']); ?>
        <article class="mag-card">
            <a class="mag-cover <?= empty($p['cover_image']) ? 'mag-cover-empty' : '' ?>" href="<?= esc($u) ?>" title="<?= esc($p['title']) ?>" aria-label="<?= esc($p['title']) ?>">
                <?php if (!empty($p['cover_image'])): ?>
                    <?= picture_from_path((string) $p['cover_image'], esc($p['title']), [
                        'width'  => 800, 'height' => 600,
                        'loading' => $_idx < 3 ? 'eager' : 'lazy',
                        'fetchpriority' => $_idx === 0 ? 'high' : '',
                    ]) ?>
                <?php endif; ?>
            </a>
            <h3><a href="<?= esc($u) ?>" title="<?= esc($p['title']) ?>"><?= esc($p['title']) ?></a></h3>
            <?php if (!empty($p['excerpt'])): ?>
                <p><?= esc(mb_substr((string) $p['excerpt'], 0, 150)) ?></p>
            <?php endif; ?>
            <p class="mag-meta">
                <a href="<?= esc(url('/yazar/' . $p['author_slug'])) ?>" title="<?= esc($p['author_name']) ?> profili"><strong><?= esc($p['author_name']) ?></strong></a>
                <span class="sep">·</span>
                <time datetime="<?= esc(date('c', strtotime((string) $p['published_at']))) ?>"><?= esc(tr_date($p['published_at'])) ?></time>
                <?php if ($p['reading_minutes']): ?>
                    <span class="sep">·</span> <span><?= (int) $p['reading_minutes'] ?> dk</span>
                <?php endif; ?>
            </p>
        </article>
    <?php endforeach; ?>
</section>

<?php if (!empty($pagination) && $pagination['total_pages'] > 1): ?>
<nav class="pagination" aria-label="Sayfalama">
    <?php if ($pagination['page'] > 1): ?>
        <a class="pagination-link pagination-prev"
           rel="prev"
           href="<?= esc($pagination['prev_url']) ?>">← Önceki</a>
    <?php else: ?>
        <span class="pagination-link pagination-disabled" aria-hidden="true">← Önceki</span>
    <?php endif; ?>

    <span class="pagination-status">
        <strong aria-current="page"><?= (int) $pagination['page'] ?></strong>
        <span class="muted">/ <?= (int) $pagination['total_pages'] ?></span>
    </span>

    <?php if ($pagination['page'] < $pagination['total_pages']): ?>
        <a class="pagination-link pagination-next"
           rel="next"
           href="<?= esc($pagination['next_url']) ?>">Sonraki →</a>
    <?php else: ?>
        <span class="pagination-link pagination-disabled" aria-hidden="true">Sonraki →</span>
    <?php endif; ?>
</nav>
<?php endif; ?>

<?php endif; ?>
