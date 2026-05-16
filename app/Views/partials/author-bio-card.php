<?php
/**
 * Yazar Bio Kartı — yazı sonunda, comments'tan önce render edilir.
 * Co-author varsa her biri için de ayrı kart üretilir (max 3 yazar).
 *
 * @var array      $author    User row (id, name, slug, avatar, profile_json, bio)
 * @var array|null $profile   ProfileService::decode() çıktısı (headline, bio, ...)
 * @var int        $excludeId Mevcut yazı id'si (kartın "diğer yazıları"ndan çıkar)
 */
if (empty($author) || empty($author['id'])) {
    return;
}
$_recent = \App\Models\Post::recentByAuthor((int) $author['id'], 3, $excludeId ?? null);
$_headline = '';
$_bio = '';
if (!empty($profile) && is_array($profile)) {
    $_headline = (string) ($profile['headline'] ?? '');
    $_bio      = (string) ($profile['bio'] ?? '');
}
if ($_bio === '' && !empty($author['bio'])) {
    $_bio = (string) $author['bio'];
}
?>
<aside class="author-bio-card" aria-label="Yazar hakkında">
    <div class="abc-layout">

        <!-- Bio kolonu -->
        <div class="abc-bio-col">
            <div class="abc-head">
                <a class="abc-avatar" href="<?= esc(url('/yazar/' . $author['slug'])) ?>"
                   aria-label="<?= esc($author['name']) ?> profili">
                    <?php if (!empty($author['avatar'])): ?>
                        <img src="<?= esc(url($author['avatar'])) ?>"
                             alt="<?= esc($author['name']) ?> avatarı"
                             width="88" height="88"
                             loading="lazy" decoding="async">
                    <?php else: ?>
                        <span class="abc-avatar-init" aria-hidden="true"><?= esc(mb_substr((string) $author['name'], 0, 1)) ?></span>
                    <?php endif; ?>
                </a>

                <div class="abc-identity">
                    <h3 class="abc-name">
                        <a href="<?= esc(url('/yazar/' . $author['slug'])) ?>" rel="author"><?= esc($author['name']) ?></a>
                    </h3>
                    <?php if ($_headline !== ''): ?>
                        <p class="abc-headline"><?= esc($_headline) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($_bio !== ''): ?>
                <p class="abc-bio"><?= esc(mb_substr($_bio, 0, 280)) ?><?= mb_strlen($_bio) > 280 ? '…' : '' ?></p>
            <?php endif; ?>

            <p class="abc-all-link">
                <a href="<?= esc(url('/yazar/' . $author['slug'])) ?>">Tüm yazıları gör <span aria-hidden="true">→</span></a>
            </p>
        </div>

        <!-- Son yazılar kolonu -->
        <?php if ($_recent): ?>
        <div class="abc-posts-col">
            <p class="abc-posts-eyebrow">Aynı yazardan</p>
            <ul class="abc-posts-list">
                <?php foreach ($_recent as $_p):
                    $_pu = url('/' . $_p['category_slug'] . '/' . $_p['slug']); ?>
                    <li class="abc-posts-item">
                        <a href="<?= esc($_pu) ?>" title="<?= esc($_p['title']) ?>">
                            <span class="abc-posts-cat"><?= esc($_p['category_name']) ?></span>
                            <span class="abc-posts-title"><?= esc($_p['title']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

    </div>
</aside>
