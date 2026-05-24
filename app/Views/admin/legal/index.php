<?php \App\Core\View::layout('base'); ?>
<section class="hero">
    <h1>Sözleşmeler</h1>
    <p class="lead">Üyelik, yazar, gizlilik ve kullanım koşulları metinleri. Düzenlemek için "Düzenle" butonuna tıklayın.</p>
    <?php require dirname(__DIR__, 2) . '/partials/flash.php'; ?>
</section>

<?php if (empty($list)): ?>
    <p class="muted" style="text-align:center;padding:3rem 0">Henüz sözleşme yok. Migration 026'yı uygulayın.</p>
<?php else: ?>
<table class="table">
    <caption class="visually-hidden">Yasal sözleşmeler listesi — <?= count($list) ?> kayıt</caption>
    <thead>
        <tr><th scope="col">Sözleşme</th><th scope="col">Slug</th><th scope="col">Sürüm</th><th scope="col">Aktif</th><th scope="col">Güncellendi</th><th scope="col">İşlem</th></tr>
    </thead>
    <tbody>
        <?php foreach ($list as $d): ?>
            <tr>
                <td><strong><?= esc($d['title']) ?></strong></td>
                <td><code>/sozlesmeler/<?= esc($d['slug']) ?></code></td>
                <td>v<?= (int) $d['version'] ?></td>
                <td>
                    <?php if ($d['is_active']): ?>
                        <span class="badge badge-published">Aktif</span>
                    <?php else: ?>
                        <span class="badge badge-rejected">Pasif</span>
                    <?php endif; ?>
                </td>
                <td class="muted"><?= esc(tr_date($d['updated_at'], true)) ?></td>
                <td>
                    <a class="btn" href="<?= esc(url('/admin/sozlesmeler/' . (int) $d['id'] . '/duzenle')) ?>">Düzenle</a>
                    <?php if ($d['is_active']): ?>
                        <a class="btn" target="_blank" href="<?= esc(url('/sozlesmeler/' . $d['slug'])) ?>">Önizleme</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
