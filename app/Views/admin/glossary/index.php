<?php \App\Core\View::layout('base'); ?>
<section class="hero">
    <h1>Mimari Sözlük</h1>
    <p class="lead">Mimari ve mühendislik terimleri sözlüğü — public: <code>/sozluk</code></p>
    <?php require dirname(__DIR__, 2) . '/partials/flash.php'; ?>
    <p style="margin-top:1rem">
        <a class="btn btn-primary" href="<?= esc(url('/admin/sozluk/yeni')) ?>">+ Yeni Terim</a>
        <?php if (function_exists('feature') && feature('glossary_ai_enabled')): ?>
            <a class="btn" href="<?= esc(url('/admin/sozluk/toplu')) ?>">⚡ Toplu AI Üretim</a>
        <?php endif; ?>
        <a class="btn" target="_blank" href="<?= esc(url('/sozluk')) ?>">Public Sözlüğü Aç</a>
    </p>
</section>

<?php if (empty($list)): ?>
    <p class="muted" style="text-align:center;padding:3rem 0">Henüz terim yok. İlkini ekleyin.</p>
<?php else: ?>
<table class="table">
    <caption class="visually-hidden">Sözlük terimleri — <?= count($list) ?> kayıt</caption>
    <thead>
        <tr><th scope="col">Terim</th><th scope="col">Kategori</th><th scope="col">Slug</th><th scope="col">Görüntülenme</th><th scope="col">Aktif</th><th scope="col" colspan="2">İşlem</th></tr>
    </thead>
    <tbody>
        <?php foreach ($list as $g): ?>
            <tr>
                <td><strong><?= esc($g['term']) ?></strong></td>
                <td><?= esc((string) ($g['category'] ?? '—')) ?></td>
                <td><code><?= esc($g['slug']) ?></code></td>
                <td><?= (int) $g['view_count'] ?></td>
                <td>
                    <?php if ($g['is_active']): ?>
                        <span class="badge badge-published">Aktif</span>
                    <?php else: ?>
                        <span class="badge badge-rejected">Pasif</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a class="btn" href="<?= esc(url('/admin/sozluk/' . (int) $g['id'] . '/duzenle')) ?>">Düzenle</a>
                </td>
                <td>
                    <form method="post" action="<?= esc(url('/admin/sozluk/' . (int) $g['id'] . '/sil')) ?>"
                          onsubmit="return confirm('Silinsin mi?');" style="display:inline">
                        <?= csrf_field() ?>
                        <button class="btn" type="submit">Sil</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
