<?php
/**
 * Engagement Bar (Tier 7) — Clap + Bookmark + Follow Author.
 *
 * @var array $post   — id, user_id (author), title, slug, category_slug, clap_count
 * @var array|null $author — id, name, slug
 */
$_clapEnabled    = function_exists('feature') && feature('clap_enabled');
$_bookmarkEnabled = function_exists('feature') && feature('bookmark_db_enabled');
$_followEnabled  = function_exists('feature') && feature('author_follow_enabled');
if (!$_clapEnabled && !$_bookmarkEnabled && !$_followEnabled) return;

$_authUser = \App\Services\AuthService::user();
$_postId = (int) ($post['id'] ?? 0);
$_authorId = (int) ($author['id'] ?? $post['user_id'] ?? 0);
$_clapTotal = (int) ($post['clap_count'] ?? 0);
$_isBookmarked = false;
$_isFollowing = false;
if ($_authUser) {
    if ($_bookmarkEnabled) {
        $_isBookmarked = \App\Models\Bookmark::isBookmarked((int) $_authUser['id'], $_postId);
    }
    if ($_followEnabled && $_authorId > 0) {
        $_isFollowing = \App\Models\AuthorFollow::isFollowing((int) $_authUser['id'], $_authorId);
    }
}
?>
<aside class="engagement-bar" aria-label="Etkileşim"
       data-engagement-state-url="<?= esc(url('/etkilesim/durum/' . $_postId)) ?>">
    <?php if ($_clapEnabled): ?>
        <button type="button" class="eng-btn eng-clap"
                data-engagement-action="clap"
                data-engagement-post="<?= $_postId ?>"
                title="Beğen (1-50 clap)"
                aria-label="Beğen">
            <span class="eng-icon">👏</span>
            <span class="eng-count" data-clap-count><?= $_clapTotal ?></span>
            <span class="eng-label">clap</span>
        </button>
    <?php endif; ?>

    <?php if ($_bookmarkEnabled): ?>
        <button type="button" class="eng-btn eng-bookmark <?= $_isBookmarked ? 'is-on' : '' ?>"
                data-engagement-action="bookmark"
                data-engagement-post="<?= $_postId ?>"
                <?= !$_authUser ? 'data-engagement-auth-required="1"' : '' ?>
                title="<?= $_authUser ? 'Daha sonra okumak için kaydet' : 'Kaydetmek için giriş yapın' ?>"
                aria-label="Kaydet"
                aria-pressed="<?= $_isBookmarked ? 'true' : 'false' ?>">
            <span class="eng-icon"><?= $_isBookmarked ? '★' : '☆' ?></span>
            <span class="eng-label"><?= $_isBookmarked ? 'kayıtlı' : 'kaydet' ?></span>
        </button>
    <?php endif; ?>

    <?php if ($_followEnabled && $_authorId > 0 && $_authUser && (int) $_authUser['id'] !== $_authorId): ?>
        <button type="button" class="eng-btn eng-follow <?= $_isFollowing ? 'is-on' : '' ?>"
                data-engagement-action="follow"
                data-engagement-author="<?= $_authorId ?>"
                title="<?= $_isFollowing ? 'Takipten çık' : 'Yazara abone ol' ?>"
                aria-label="Yazarı takip et"
                aria-pressed="<?= $_isFollowing ? 'true' : 'false' ?>">
            <span class="eng-icon">⊕</span>
            <span class="eng-label"><?= $_isFollowing ? 'Takipte' : 'Takip et' ?></span>
        </button>
    <?php endif; ?>
</aside>
