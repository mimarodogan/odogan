<?php
/**
 * Emoji Reactions Bar (Tier 8).
 *
 * @var array $post
 */
if (!function_exists('feature') || !feature('reactions_enabled')) return;
$_postId = (int) ($post['id'] ?? 0);
if ($_postId <= 0) return;

$_user = \App\Services\AuthService::user();
$_summary = \App\Models\PostReaction::summary(
    $_postId,
    $_user ? (int) $_user['id'] : null,
    $_SERVER['REMOTE_ADDR'] ?? null
);
$_emojis = \App\Models\PostReaction::EMOJIS;
?>
<aside class="reactions-bar" aria-label="Yazıya tepki ver" data-reaction-post="<?= $_postId ?>">
    <p class="reactions-label">Bu yazıya tepki ver</p>
    <div class="reactions-row">
        <?php foreach ($_emojis as $key => $emoji): ?>
            <?php $isMine = in_array($key, $_summary['mine'], true); ?>
            <button type="button"
                    class="reaction-btn <?= $isMine ? 'is-on' : '' ?>"
                    data-reaction="<?= esc($key) ?>"
                    aria-label="<?= esc($key) ?> tepkisi"
                    aria-pressed="<?= $isMine ? 'true' : 'false' ?>">
                <span class="reaction-emoji"><?= $emoji ?></span>
                <span class="reaction-count" data-reaction-count="<?= esc($key) ?>"><?= (int) ($_summary['counts'][$key] ?? 0) ?></span>
            </button>
        <?php endforeach; ?>
    </div>
</aside>
