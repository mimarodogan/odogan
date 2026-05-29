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

// Ad baş harfi — avatar dairesi için
$_initial = static function (string $name): string {
    $n = trim($name);
    return $n === '' ? '?' : mb_strtoupper(mb_substr($n, 0, 1), 'UTF-8');
};

$_renderComment = function (array $c, int $depth = 0) use (&$_renderComment, &$_byParent, $logged_in, $_initial): void {
    $hasReplies = isset($_byParent[(int) $c['id']]);
    $depthClass = $depth > 0 ? ' is-reply depth-' . min($depth, 3) : '';
    $name = (string) ($c['user_name'] ?: $c['author_name'] ?: 'Misafir');
    ?>
    <li class="comment-item<?= $depthClass ?>" id="comment-<?= (int) $c['id'] ?>">
        <article class="comment">
            <div class="comment-avatar" aria-hidden="true"><?= esc($_initial($name)) ?></div>
            <div class="comment-main">
                <header class="comment-meta">
                    <strong class="comment-author"><?= esc($name) ?></strong>
                    <time class="comment-date" datetime="<?= esc(date('c', strtotime((string) $c['created_at']))) ?>"><?= esc(tr_date($c['created_at'], true)) ?></time>
                </header>
                <div class="comment-body"><?= nl2br(esc($c['body'])) ?></div>
                <footer class="comment-actions">
                    <button type="button" class="comment-reply-btn"
                            data-reply-to="<?= (int) $c['id'] ?>"
                            data-reply-name="<?= esc($name) ?>">
                        ↳ Yanıtla
                    </button>
                </footer>
            </div>
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
<section id="yorumlar" class="comments-section">
    <div class="comments-head">
        <h2 class="comments-title">Yorumlar</h2>
        <span class="comments-count"><?= count($comments) ?></span>
    </div>

    <?php if ($s = flash('success_comment')): ?>
        <div class="flash flash-success"><?= esc($s) ?></div>
    <?php endif; ?>
    <?php if ($e = flash('error_comment')): ?>
        <div class="flash flash-error"><?= esc($e) ?></div>
    <?php endif; ?>

    <?php if (!$comments): ?>
        <p class="comments-empty">Henüz yorum yok — ilk yorumu siz yazın.</p>
    <?php else: ?>
        <ul class="comments-list">
            <?php foreach ($_topLevel as $c): ?>
                <?php $_renderComment($c, 0); ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <div class="comment-form-wrap">
        <h3 class="comment-form-title">Yorum Yaz</h3>
        <form method="post" action="<?= esc(url('/yorum')) ?>" class="form comment-form" id="comment-form">
            <?= csrf_field() ?>
            <input type="hidden" name="post_id" value="<?= (int) $post_id ?>">
            <input type="hidden" name="parent_id" id="comment-parent-id" value="">
            <input type="hidden" name="form_ts" value="<?= time() ?>">

            <p class="comment-replying-to" id="comment-replying-to" hidden role="status" aria-live="polite">
                <span id="replying-to-text"></span>
                <button type="button" id="cancel-reply">İptal</button>
            </p>

            <div aria-hidden="true" class="comment-hp">
                <label>Website (boş bırakın)
                    <input type="text" name="website" tabindex="-1" autocomplete="off" value="">
                </label>
            </div>

            <?php if (!$logged_in): ?>
                <div class="comment-form-row">
                    <label><span>Adınız</span>
                        <input type="text" name="author_name" required minlength="2" maxlength="120" placeholder="Adınız">
                    </label>
                    <label><span>E-posta <em>(yayınlanmaz)</em></span>
                        <input type="email" name="author_email" required maxlength="190" placeholder="ornek@eposta.com">
                    </label>
                </div>
            <?php endif; ?>
            <label><span>Yorum</span>
                <textarea name="body" id="comment-body" rows="4" required minlength="3" maxlength="4000" placeholder="Düşüncelerinizi paylaşın…"></textarea>
            </label>

            <?php // F2.3 (KVKK): yorum gönderirken e-posta sahiplenme için açık rıza şart. ?>
            <label class="comment-kvkk">
                <input type="checkbox" name="kvkk_consent" value="1" required>
                <span>
                    <a href="<?= esc(url('/sozlesmeler/aydinlatma-metni')) ?>" target="_blank" rel="noopener">KVKK Aydınlatma Metni</a>'ni
                    okudum; ad-soyad, e-posta ve IP adresimin yorum yayımı amacıyla
                    işlenmesine onay veriyorum.
                </span>
            </label>

            <div class="comment-form-foot">
                <p class="comment-form-note">Yorumunuz editör onayından sonra yayınlanır.</p>
                <button class="btn btn-primary" type="submit">Gönder</button>
            </div>
        </form>
    </div>
</section>

<script nonce="<?= esc(csp_nonce()) ?>">
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
