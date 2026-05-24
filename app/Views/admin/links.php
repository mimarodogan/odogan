<?php \App\Core\View::layout('base'); ?>
<section class="hero">
    <h1>Kırık Link Dedektörü</h1>
    <p class="lead muted">Yazılarındaki harici linkler — şu an çalışmayanlar.</p>
    <?php require dirname(__DIR__) . '/partials/flash.php'; ?>
    <p>
        <form method="post" action="<?= esc(url('/admin/linkler/tara')) ?>" style="display:inline">
            <?= csrf_field() ?>
            <button class="btn btn-primary" type="submit">🔎 Şimdi Tara</button>
        </form>
    </p>
</section>

<?php if (!$broken): ?>
    <p class="muted">🎉 Hiç kırık link yok. Bir taramadan sonra tekrar bakın.</p>
<?php else: ?>
<table class="table">
    <caption class="visually-hidden">Kırık linkler listesi — <?= count($broken) ?> kayıt</caption>
    <thead>
        <tr><th scope="col">Yazı</th><th scope="col">URL</th><th scope="col">Durum</th><th scope="col">Hata</th><th scope="col">Son Kontrol</th></tr>
    </thead>
    <tbody>
    <?php foreach ($broken as $b): $postUrl = url('/' . $b['category_slug'] . '/' . $b['post_slug']); ?>
        <tr>
            <td><a href="<?= esc($postUrl) ?>" target="_blank"><?= esc($b['post_title']) ?></a></td>
            <td>
                <a href="<?= esc($b['url']) ?>" target="_blank" rel="noopener" style="word-break:break-all">
                    <?= esc($b['url']) ?>
                </a>
            </td>
            <td><span class="log-badge"><?= (int) $b['status_code'] ?: '—' ?></span></td>
            <td class="muted"><?= esc((string) ($b['error'] ?? '')) ?></td>
            <td class="muted nowrap"><?= esc(substr((string) $b['last_checked_at'], 0, 16)) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
