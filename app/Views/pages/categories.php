<?php \App\Core\View::layout('base'); ?>
<?= breadcrumbs_html($breadcrumbs ?? []) ?>

<section class="hero">
    <h1>Kategoriler</h1>
    <p class="lead">Tüm içerik kategorileri ve her birindeki yazı sayısı.</p>
</section>

<?php if (empty($categories)): ?>
    <p class="muted">Henüz kategori yok.</p>
<?php else: ?>
<section class="cat-index-grid" aria-label="Kategori listesi">
    <?php foreach ($categories as $c):
        $u   = url('/' . $c['slug']);
        $cnt = (int) ($c['post_count'] ?? 0);
    ?>
        <a class="cat-index-card" href="<?= esc($u) ?>"
           title="<?= esc($c['name']) ?> kategorisindeki yazılar">
            <span class="cat-index-num" aria-hidden="true"><?= $cnt ?></span>
            <span class="cat-index-body">
                <span class="cat-index-name"><?= esc($c['name']) ?></span>
                <?php if (!empty($c['description'])): ?>
                    <span class="cat-index-desc"><?= esc(mb_substr((string) $c['description'], 0, 110)) ?></span>
                <?php endif; ?>
                <span class="cat-index-count"><?= $cnt ?> yazı</span>
            </span>
            <span class="cat-index-arrow" aria-hidden="true">→</span>
        </a>
    <?php endforeach; ?>
</section>
<?php endif; ?>
