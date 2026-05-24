<?php \App\Core\View::layout('base'); ?>
<section class="hero">
    <h1>Diziler</h1>
    <p class="lead">Sıralı, bölüm-bölüm uzun yazılar. Bir yazı düzenleme ekranında seri seçilerek atfedilir.</p>
    <?php require dirname(__DIR__, 2) . '/partials/flash.php'; ?>
    <p style="margin-top:1rem">
        <a class="btn btn-primary" href="<?= esc(url('/admin/diziler/yeni')) ?>">+ Yeni Dizi</a>
    </p>
</section>

<?php if (empty($list)): ?>
    <p class="muted" style="padding:2rem 0;text-align:center">Henüz dizi eklenmemiş. Yukarıdan ilk diziyi oluşturabilirsin.</p>
<?php else: ?>
<table class="table">
    <caption class="visually-hidden">Diziler listesi — <?= count($list) ?> kayıt</caption>
    <thead>
        <tr><th scope="col">Ad</th><th scope="col">Slug</th><th scope="col" style="text-align:right">Bölüm</th><th scope="col">Güncellendi</th><th scope="col" colspan="2">Aksiyon</th></tr>
    </thead>
    <tbody>
        <?php foreach ($list as $s): ?>
            <tr>
                <td><strong><?= esc($s['name']) ?></strong></td>
                <td><code><?= esc($s['slug']) ?></code></td>
                <td style="text-align:right"><?= (int) $s['post_count'] ?></td>
                <td><?= esc(date('d/m/Y H:i', strtotime((string) $s['updated_at']))) ?></td>
                <td>
                    <a class="btn" href="<?= esc(url('/admin/diziler/' . (int) $s['id'] . '/duzenle')) ?>">Düzenle</a>
                </td>
                <td>
                    <form method="post" action="<?= esc(url('/admin/diziler/' . (int) $s['id'] . '/sil')) ?>"
                          onsubmit="return confirm('Bu diziyi silmek istediğinize emin misiniz? Yazılar etkilenmez (series_id NULL olur).');">
                        <?= csrf_field() ?>
                        <button class="btn" type="submit">Sil</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<p style="margin-top:1.5rem">
    <a href="<?= esc(url('/admin/diziler')) ?>">← Diziler</a>
</p>
