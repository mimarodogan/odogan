<?php
/**
 * @var array $tag      ['id','name','slug','post_count']
 * @var array $posts    Tag::postsForTag
 * @var array $breadcrumbs
 */
\App\Core\View::layout('base');
?>
<?= breadcrumbs_html($breadcrumbs ?? []) ?>

<section class="hero">
    <h1>Etiket: <?= esc($tag['name']) ?></h1>
    <p class="lead muted">
        "<strong><?= esc($tag['name']) ?></strong>" etiketli <?= count($posts) ?> yazı.
    </p>
</section>

<?php if (!$posts): ?>
    <p class="muted">Bu etikete ait yayımlanmış yazı yok.</p>
<?php else: ?>
<section class="mag-grid">
    <?php foreach ($posts as $_idx => $p): $u = url('/' . $p['category_slug'] . '/' . $p['slug']); ?>
        <article class="mag-card">
            <a class="mag-cover <?= empty($p['cover_image']) ? 'mag-cover-empty' : '' ?>"
               href="<?= esc($u) ?>" title="<?= esc($p['title']) ?>">
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
                <a href="<?= esc(url('/' . $p['category_slug'])) ?>" title="<?= esc($p['category_name']) ?> kategorisi"><?= esc($p['category_name']) ?></a>
                <span class="sep">·</span>
                <a href="<?= esc(url('/yazar/' . $p['author_slug'])) ?>" title="<?= esc($p['author_name']) ?> profili"><strong><?= esc($p['author_name']) ?></strong></a>
                <span class="sep">·</span>
                <time datetime="<?= esc(date('c', strtotime((string) $p['published_at']))) ?>"><?= esc(tr_date($p['published_at'])) ?></time>
                <?php if ($p['reading_minutes']): ?>
                    <span class="sep">·</span> <?= (int) $p['reading_minutes'] ?> dk
                <?php endif; ?>
            </p>
        </article>
    <?php endforeach; ?>
</section>
<?php endif; ?>
