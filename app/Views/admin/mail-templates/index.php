<?php \App\Core\View::layout('base'); ?>
<section class="hero">
    <h1>Mail Şablonları</h1>
    <p class="lead">Sistem tarafından gönderilen tüm mailler — admin'de düzenle.</p>
    <?php require dirname(__DIR__, 2) . '/partials/flash.php'; ?>
</section>

<?php if (empty($list)): ?>
    <p class="muted" style="text-align:center;padding:3rem 0">Henüz şablon yok. Migration 027'yi uygulayın.</p>
<?php else: ?>
<table class="table">
    <caption class="visually-hidden">E-posta şablonları — <?= count($list) ?> kayıt</caption>
    <thead>
        <tr><th scope="col">Şablon</th><th scope="col">Anahtar</th><th scope="col">Konu</th><th scope="col">Aktif</th><th scope="col">İşlem</th></tr>
    </thead>
    <tbody>
        <?php foreach ($list as $t): ?>
            <tr>
                <td><strong><?= esc($t['label']) ?></strong></td>
                <td><code><?= esc($t['key_name']) ?></code></td>
                <td><?= esc($t['subject']) ?></td>
                <td>
                    <?php if ($t['is_active']): ?>
                        <span class="badge badge-published">Aktif</span>
                    <?php else: ?>
                        <span class="badge badge-rejected">Pasif</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a class="btn" href="<?= esc(url('/admin/mail-sablonlari/' . (int) $t['id'] . '/duzenle')) ?>">Düzenle</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
