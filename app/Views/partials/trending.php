<?php
/**
 * "Çok Okunanlar" widget — son 30 günde view_count'a göre top 5 yazı.
 *
 * @var array $trending  Post::trending() çıktısı
 * @var string $heading  default "Çok Okunanlar"
 */
$heading = $heading ?? 'Çok Okunanlar';
if (empty($trending)) return;
?>
<details class="trending-widget" aria-label="<?= esc($heading) ?>">
    <summary><h2 class="block-title"><?= esc($heading) ?></h2></summary>
    <ol class="rank-list">
        <?php foreach ($trending as $p): ?>
            <li>
                <a href="<?= esc(url('/' . $p['category_slug'] . '/' . $p['slug'])) ?>"
                   title="<?= esc($p['title']) ?>">
                    <?= esc($p['title']) ?>
                    <span class="rank-meta">·
                        <?php if (!empty($p['category_name'])): ?>
                            <?= esc($p['category_name']) ?> ·
                        <?php endif; ?>
                        <?= number_format((int) ($p['view_count'] ?? 0)) ?> okunma
                    </span>
                </a>
            </li>
        <?php endforeach; ?>
    </ol>
</details>
