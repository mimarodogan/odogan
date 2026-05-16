<?php \App\Core\View::layout('base'); ?>
<section class="hero">
    <h1>Sürüm Geçmişi</h1>
    <p class="lead muted"><?= esc($post['title']) ?></p>
    <?php require dirname(dirname(dirname(__FILE__))) . '/partials/flash.php'; ?>
    <p><a class="btn" href="<?= esc(url('/panel/yazilar/' . $post['id'] . '/duzenle')) ?>">← Düzenleme ekranına dön</a></p>
</section>

<?php if (!$revisions): ?>
    <p class="muted">Henüz kayıtlı sürüm yok. İçeriği bir kez güncellediğinde, önceki haliyle bir snapshot oluşur.</p>
<?php else: ?>
<table class="table">
    <caption class="visually-hidden">İçerik sürümleri — <?= count($revisions) ?> kayıt</caption>
    <thead>
        <tr><th scope="col">Tarih</th><th scope="col">Başlık</th><th scope="col">Düzenleyen</th><th scope="col">Format</th><th scope="col">Not</th><th scope="col">Aksiyon</th></tr>
    </thead>
    <tbody>
    <?php foreach ($revisions as $r): ?>
        <tr>
            <td class="muted nowrap"><?= esc(tr_date($r['created_at'], true)) ?></td>
            <td><?= esc($r['title']) ?></td>
            <td><?= esc((string) ($r['user_name'] ?? '—')) ?></td>
            <td><code><?= esc((string) $r['body_format']) ?></code></td>
            <td class="muted"><?= esc((string) ($r['note'] ?? '')) ?></td>
            <td>
                <a class="btn" href="<?= esc(url('/panel/yazilar/' . $post['id'] . '/surumler/' . $r['id'])) ?>">İncele</a>
                <form method="post" action="<?= esc(url('/panel/yazilar/' . $post['id'] . '/surumler/' . $r['id'] . '/geri-yukle')) ?>"
                      onsubmit="return confirm('Bu sürüme geri dönüldüğünde mevcut hali ayrı bir snapshot olarak saklanır. Devam edilsin mi?');"
                      style="display:inline">
                    <?= csrf_field() ?>
                    <button class="btn btn-primary" type="submit">Geri Yükle</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
