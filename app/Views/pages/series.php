<?php \App\Core\View::layout('base'); ?>

<?= breadcrumbs_html($breadcrumbs ?? []) ?>

<section class="series-hero">
    <h1><?= esc($series['name']) ?></h1>
    <?php if (!empty($series['description'])): ?>
        <p class="lead"><?= esc($series['description']) ?></p>
    <?php endif; ?>
    <p class="series-meta">
        <?= count($posts) ?> Bölüm
    </p>
</section>

<?php if (!empty($posts)): ?>
<section class="series-list">
    <ol class="series-ol">
        <?php foreach ($posts as $_i => $p): $u = url('/' . $p['category_slug'] . '/' . $p['slug']); ?>
            <li class="series-li">
                <span class="series-num"><?= (int) $p['series_position'] ?: ($_i + 1) ?></span>
                <article class="series-item">
                    <?php if (!empty($p['cover_image'])): ?>
                        <a class="series-cover" href="<?= esc($u) ?>" title="<?= esc($p['title']) ?>">
                            <?= picture_from_path((string) $p['cover_image'], esc($p['title']), ['width' => 240, 'height' => 180]) ?>
                        </a>
                    <?php endif; ?>
                    <div class="series-text">
                        <a class="cat-pill" href="<?= esc(url('/' . $p['category_slug'])) ?>"><?= esc($p['category_name']) ?></a>
                        <h2 class="series-title"><a href="<?= esc($u) ?>"><?= esc($p['title']) ?></a></h2>
                        <?php if (!empty($p['excerpt'])): ?>
                            <p class="series-excerpt"><?= esc(mb_substr((string) $p['excerpt'], 0, 180)) ?></p>
                        <?php endif; ?>
                        <p class="series-meta-row muted">
                            <a href="<?= esc(url('/yazar/' . $p['author_slug'])) ?>"><?= esc($p['author_name']) ?></a>
                            <?php if (!empty($p['published_at'])): ?>
                                <span class="sep">·</span>
                                <time datetime="<?= esc(date('c', strtotime((string) $p['published_at']))) ?>"><?= esc(tr_date($p['published_at'])) ?></time>
                            <?php endif; ?>
                            <?php if (!empty($p['reading_minutes'])): ?>
                                <span class="sep">·</span> <?= (int) $p['reading_minutes'] ?> dk
                            <?php endif; ?>
                        </p>
                    </div>
                </article>
            </li>
        <?php endforeach; ?>
    </ol>
</section>
<?php else: ?>
    <p class="muted" style="text-align:center;padding:3rem 0">Bu dizide henüz yayında bölüm yok.</p>
<?php endif; ?>
