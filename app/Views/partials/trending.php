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
<aside class="trending-widget" aria-label="<?= esc($heading) ?>">
    <h2 class="block-title"><?= esc($heading) ?></h2>
    <ol class="rank-list">
        <?php foreach ($trending as $_i => $p): ?>
            <li>
                <span class="rank-num"><?= str_pad((string) ($_i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                <div class="rank-content">
                    <a href="<?= esc(url('/' . $p['category_slug'] . '/' . $p['slug'])) ?>"
                       title="<?= esc($p['title']) ?>">
                        <strong><?= esc($p['title']) ?></strong>
                    </a>
                    <p class="muted" style="font-size:.8rem;margin:.25rem 0 0">
                        <?php if (!empty($p['category_name'])): ?>
                            <?= esc($p['category_name']) ?> ·
                        <?php endif; ?>
                        <?= number_format((int) ($p['view_count'] ?? 0)) ?> okunma
                    </p>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>
</aside>
