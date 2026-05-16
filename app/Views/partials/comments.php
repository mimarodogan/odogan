<?php
/**
 * @var array $comments
 * @var int   $post_id
 * @var bool  $logged_in
 */

// Threading — parent_id ile nested grupla (Tier 8)
$_topLevel = [];
$_byParent = [];
foreach ($comments as $c) {
    if (!empty($c['parent_id'])) {
        $_byParent[(int) $c['parent_id']][] = $c;
    } else {
        $_topLevel[] = $c;
    }
}

$_renderComment = function (array $c, int $depth = 0) use (&$_renderComment, &$_byParent, $logged_in): void {
    $hasReplies = isset($_byParent[(int) $c['id']]);
    $depthClass = $depth > 0 ? ' is-reply depth-' . min($depth, 3) : '';
    ?>
    <li class="comment-item<?= $depthClass ?>" id="comment-<?= (int) $c['id'] ?>">
        <article class="comment">
            <header class="comment-meta">
                <strong class="comment-author"><?= esc($c['user_name'] ?: $c['author_name'] ?: 'Misafir') ?></strong>
                <time class="comment-date" datetime="<?= esc(date('c', strtotime((string) $c['created_at']))) ?>"><?= esc(tr_date($c['created_at'], true)) ?></time>
            </header>
            <div class="comment-body"><?= nl2br(esc($c['body'])) ?></div>
            <footer class="comment-actions">
                <button type="button" class="comment-reply-btn"
                        data-reply-to="<?= (int) $c['id'] ?>"
                        data-reply-name="<?= esc($c['user_name'] ?: $c['author_name'] ?: 'Misafir') ?>">
                    ↳ Yanıtla
                </button>
            </footer>
        </article>
        <?php if ($hasReplies): ?>
            <ul class="comment-replies">
                <?php foreach ($_byParent[(int) $c['id']] as $reply): ?>
                    <?php $_renderComment($reply, $depth + 1); ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </li>
    <?php
};
?>
<section id="yorumlar" class="author-section">
    <h2>Yorumlar (<?= count($comments) ?>)</h2>

    <?php if ($s = flash('success_comment')): ?>
        <div class="flash flash-success"><?= esc($s) ?></div>
    <?php endif; ?>
    <?php if ($e = flash('error_comment')): ?>
        <div class="flash flash-error"><?= esc($e) ?></div>
    <?php endif; ?>

    <?php if (!$comments): ?>
        <p class="muted">İlk yorumu siz yapın.</p>
    <?php else: ?>
        <ul class="comments-list">
            <?php foreach ($_topLevel as $c): ?>
                <?php $_renderComment($c, 0); ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h3 style="margin-top:1.5rem">Yorum Yaz</h3>
    <form method="post" action="<?= esc(url('/yorum')) ?>" class="form form-wide comment-form" id="comment-form">
        <?= csrf_field() ?>
        <input type="hidden" name="post_id" value="<?= (int) $post_id ?>">
        <input type="hidden" name="parent_id" id="comment-parent-id" value="">
        <input type="hidden" name="form_ts" value="<?= time() ?>">

        <p class="comment-replying-to" id="comment-replying-to" hidden role="status" aria-live="polite" style="background:var(--bone-2);padding:.75rem 1rem;border-left:2px solid var(--cobalt);font-family:var(--mono);font-size:.78rem;text-transform:uppercase;letter-spacing:var(--tracked)">
            <span id="replying-to-text"></span>
            <button type="button" id="cancel-reply" style="background:transparent;border:0;color:var(--cobalt);cursor:pointer;font-family:inherit;text-decoration:underline">İptal</button>
        </p>

        <div aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden">
            <label>Website (boş bırakın)
                <input type="text" name="website" tabindex="-1" autocomplete="off" value="">
            </label>
        </div>
        <?php if (!$logged_in): ?>
            <label><span>Adınız</span>
                <input type="text" name="author_name" required minlength="2" maxlength="120">
            </label>
            <label><span>E-posta (yayınlanmaz)</span>
                <input type="email" name="author_email" required maxlength="190">
            </label>
        <?php endif; ?>
        <label><span>Yorum</span>
            <textarea name="body" id="comment-body" rows="4" required minlength="3" maxlength="4000"></textarea>
        </label>
        <p class="muted" style="font-size:.85rem">Yorumunuz editör onayından sonra yayınlanır.</p>
        <button class="btn btn-primary" type="submit">Gönder</button>
    </form>
</section>

<script>
// Reply threading — "Yanıtla" tıklayınca form'a parent_id set + scroll
(function(){
    document.querySelectorAll('.comment-reply-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var pid = btn.getAttribute('data-reply-to');
            var name = btn.getAttribute('data-reply-name');
            var input = document.getElementById('comment-parent-id');
            var info = document.getElementById('comment-replying-to');
            var infoText = document.getElementById('replying-to-text');
            if (input) input.value = pid;
            if (infoText) infoText.textContent = name + ' kullanıcısına yanıt veriyorsun';
            if (info) info.hidden = false;
            var body = document.getElementById('comment-body');
            if (body) body.focus();
            document.getElementById('comment-form').scrollIntoView({ behavior: 'smooth' });
        });
    });
    var cancel = document.getElementById('cancel-reply');
    if (cancel) cancel.addEventListener('click', function(){
        document.getElementById('comment-parent-id').value = '';
        document.getElementById('comment-replying-to').hidden = true;
    });
})();
</script>
