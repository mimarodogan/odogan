<?php \App\Core\View::layout('base'); ?>
<section class="hero">
    <h1>Yorum Moderasyonu</h1>
    <p class="lead muted">Onay bekleyen yorumları onayla, reddet veya spam olarak işaretle.</p>
    <?php require dirname(__DIR__) . '/partials/flash.php'; ?>
</section>

<?php if (!$pending): ?>
    <p class="muted">Onay bekleyen yorum yok 🎉</p>
<?php else: ?>
<table class="table">
    <caption class="visually-hidden">Onay bekleyen yorumlar — <?= count($pending) ?> kayıt</caption>
    <thead>
        <tr><th scope="col">Tarih</th><th scope="col">Yazı</th><th scope="col">Yazan</th><th scope="col">Yorum</th><th scope="col">Aksiyon</th></tr>
    </thead>
    <tbody>
    <?php foreach ($pending as $c):
        $postUrl = url('/' . ($c['category_slug'] ?? '') . '/' . $c['post_slug']);
        $author = $c['user_name'] ?: $c['author_name'] ?: 'Misafir';
    ?>
        <tr>
            <td class="muted nowrap"><?= esc(tr_date($c['created_at'], true)) ?></td>
            <td><a href="<?= esc($postUrl) ?>" target="_blank"><?= esc($c['post_title']) ?></a></td>
            <td>
                <?= esc($author) ?>
                <?php if (!empty($c['author_email'])): ?>
                    <br><small class="muted"><?= esc($c['author_email']) ?></small>
                <?php endif; ?>
                <br><small class="muted">IP: <?= esc((string) $c['ip_address']) ?></small>
            </td>
            <td><?= nl2br(esc(mb_substr((string) $c['body'], 0, 400))) ?></td>
            <td class="comment-actions">
                <form method="post" action="<?= esc(url('/editor/yorumlar/' . $c['id'] . '/onayla')) ?>" style="display:inline">
                    <?= csrf_field() ?>
                    <button class="btn btn-primary" type="submit">Onayla</button>
                </form>
                <form method="post" action="<?= esc(url('/editor/yorumlar/' . $c['id'] . '/reddet')) ?>" style="display:inline">
                    <?= csrf_field() ?>
                    <button class="btn" type="submit">Reddet</button>
                </form>
                <form method="post" action="<?= esc(url('/editor/yorumlar/' . $c['id'] . '/spam')) ?>" style="display:inline">
                    <?= csrf_field() ?>
                    <button class="btn" type="submit">Spam</button>
                </form>
                <form method="post" action="<?= esc(url('/editor/yorumlar/' . $c['id'] . '/sil')) ?>"
                      onsubmit="return confirm('Yorum kalıcı silinsin mi?');" style="display:inline">
                    <?= csrf_field() ?>
                    <button class="btn" type="submit">Sil</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
