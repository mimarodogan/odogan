<?php
/**
 * Önceki / Sonraki Yazı — yazı altında, aynı kategoride sıralı navigasyon.
 *
 * @var array|null $prev_next ['prev' => ?array, 'next' => ?array]
 */
if (empty($prev_next) || (empty($prev_next['prev']) && empty($prev_next['next']))) {
    return;
}
$_prev = $prev_next['prev'] ?? null;
$_next = $prev_next['next'] ?? null;
?>
<nav class="prev-next-nav" aria-label="Aynı kategoride sıralı yazılar">
    <?php if ($_prev): ?>
        <a class="pn-card pn-prev" href="<?= esc(url('/' . $_prev['category_slug'] . '/' . $_prev['slug'])) ?>"
           title="Önceki: <?= esc($_prev['title']) ?>" rel="prev">
            <span class="pn-dir">← Önceki Yazı</span>
            <span class="pn-title"><?= esc($_prev['title']) ?></span>
        </a>
    <?php else: ?>
        <span class="pn-card pn-empty" aria-hidden="true"></span>
    <?php endif; ?>

    <?php if ($_next): ?>
        <a class="pn-card pn-next" href="<?= esc(url('/' . $_next['category_slug'] . '/' . $_next['slug'])) ?>"
           title="Sonraki: <?= esc($_next['title']) ?>" rel="next">
            <span class="pn-dir">Sonraki Yazı →</span>
            <span class="pn-title"><?= esc($_next['title']) ?></span>
        </a>
    <?php else: ?>
        <span class="pn-card pn-empty" aria-hidden="true"></span>
    <?php endif; ?>
</nav>
