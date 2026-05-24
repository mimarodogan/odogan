<?php \App\Core\View::layout('base'); ?>
<section class="hero">
    <h1>Yazarlar</h1>
</section>

<?php if ($authors): ?>
<section class="authors-grid">
    <?php foreach ($authors as $a): ?>
        <a class="author-circle" href="<?= esc(url('/yazar/' . $a['slug'])) ?>" title="<?= esc($a['name']) ?> profili">
            <span class="ac-avatar">
                <?php if (!empty($a['avatar'])): ?>
                    <img src="<?= esc(url($a['avatar'])) ?>" alt="<?= esc($a['name']) ?>" loading="lazy">
                <?php else: ?>
                    <span class="ac-initial" aria-hidden="true"><?= esc(mb_strtoupper(mb_substr((string) $a['name'], 0, 1))) ?></span>
                <?php endif; ?>
            </span>
            <span class="ac-name"><?= esc($a['name']) ?></span>
            <?php if (!empty($a['profile']['headline'])): ?>
                <span class="ac-headline"><?= esc($a['profile']['headline']) ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</section>
<?php else: ?>
    <p class="muted">Henüz yazar yok.</p>
<?php endif; ?>
