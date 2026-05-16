<?php \App\Core\View::layout('base'); ?>
<section class="hero">
    <h1>Onay Kuyruğu</h1>
    <?php require dirname(__DIR__) . '/partials/flash.php'; ?>
</section>

<h2>Bekleyen <span class="muted">(<?= count($pending) ?>)</span></h2>
<?php if (!$pending): ?>
    <p class="muted">Onay bekleyen içerik yok.</p>
<?php else: ?>
<table class="table">
    <caption class="visually-hidden">Onay bekleyen yazılar — <?= count($pending) ?> kayıt</caption>
    <thead><tr><th scope="col">Başlık</th><th scope="col">Yazar</th><th scope="col">Kategori</th><th scope="col">Gönderim</th><th scope="col"><span class="visually-hidden">İşlemler</span></th></tr></thead>
    <tbody>
    <?php foreach ($pending as $p): ?>
        <tr>
            <td><strong><?= esc($p['title']) ?></strong></td>
            <td><?= esc((string) $p['author_name']) ?></td>
            <td><?= esc((string) ($p['category_name'] ?? '—')) ?></td>
            <td class="muted"><?= esc(tr_date($p['updated_at'], true)) ?></td>
            <td><a class="btn btn-primary" href="<?= esc(url('/editor/onay/' . $p['id'])) ?>">İncele</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<h2 style="margin-top:2rem">Son Yayınlananlar</h2>
<?php if (!$recent): ?>
    <p class="muted">Henüz yayında içerik yok.</p>
<?php else: ?>
<ul class="timeline">
    <?php foreach ($recent as $p): ?>
        <li>
            <strong><?= esc($p['title']) ?></strong>
            <span class="muted">— <?= esc((string) $p['author_name']) ?> · <?= esc(tr_date($p['published_at'], true)) ?></span>
        </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>
