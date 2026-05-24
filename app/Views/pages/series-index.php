<?php \App\Core\View::layout('base'); ?>

<?= breadcrumbs_html($breadcrumbs ?? []) ?>

<section class="hero">
    <h1>Diziler</h1>
    <p class="lead">Sıralı, bölüm-bölüm uzun-form yazılar.</p>
</section>

<?php if (empty($list)): ?>
    <p class="muted" style="text-align:center;padding:3rem 0">Henüz yayında dizi yok.</p>
<?php else: ?>
<section>
    <div class="mag-grid">
        <?php foreach ($list as $s): ?>
            <article class="mag-card">
                <a class="mag-cover <?= empty($s['cover_image']) ? 'mag-cover-empty' : '' ?>"
                   href="<?= esc(url('/dizi/' . $s['slug'])) ?>"
                   title="<?= esc($s['name']) ?>" aria-label="<?= esc($s['name']) ?>">
                    <?php if (!empty($s['cover_image'])): ?>
                        <?= picture_from_path((string) $s['cover_image'], esc($s['name']), ['width' => 800, 'height' => 600]) ?>
                    <?php endif; ?>
                </a>
                <h3><a href="<?= esc(url('/dizi/' . $s['slug'])) ?>"><?= esc($s['name']) ?></a></h3>
                <?php if (!empty($s['description'])): ?>
                    <p><?= esc(mb_substr((string) $s['description'], 0, 160)) ?></p>
                <?php endif; ?>
                <p class="mag-meta"><strong><?= (int) $s['post_count'] ?></strong> bölüm</p>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
