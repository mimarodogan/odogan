<?php \App\Core\View::layout('base'); ?>
<?php /** @var array<int,array<string,mixed>> $categories */ ?>
<section class="hero">
    <h1>Kategoriler</h1>
    <p class="lead">Silo URL'lerinin temeli. <?= count($categories) ?> kategori.</p>
    <?php require dirname(__DIR__) . '/partials/flash.php'; ?>
    <div class="hero-actions">
        <a class="btn btn-primary" href="<?= esc(url('/admin/kategoriler/yeni')) ?>">+ Yeni Kategori</a>
    </div>
</section>

<?php if (empty($categories)): ?>
    <div class="empty-card">
        <p>Henüz kategori yok.</p>
        <a class="btn btn-secondary" href="<?= esc(url('/admin/kategoriler/yeni')) ?>">İlk kategoriyi ekle →</a>
    </div>
<?php else: ?>
    <table class="table">
        <caption class="visually-hidden">Kategoriler listesi — <?= count($categories) ?> kayıt</caption>
        <thead>
            <tr>
                <th scope="col">Ad</th>
                <th scope="col">Slug</th>
                <th scope="col">Üst</th>
                <th scope="col">Durum</th>
                <th scope="col">Sıra</th>
                <th scope="col"><span class="visually-hidden">İşlemler</span></th>
            </tr>
        </thead>
        <tbody>
        <?php
        // ID → name map for parent lookup
        $byId = [];
        foreach ($categories as $c) { $byId[(int) $c['id']] = $c; }
        foreach ($categories as $c):
            $parent = !empty($c['parent_id']) && isset($byId[(int) $c['parent_id']])
                ? $byId[(int) $c['parent_id']]['name'] : null;
        ?>
            <tr>
                <td>
                    <strong><?= esc($c['name']) ?></strong>
                    <?php if (!empty($c['description'])): ?>
                        <br><small class="muted"><?= esc(mb_substr($c['description'], 0, 60)) ?>…</small>
                    <?php endif; ?>
                </td>
                <td><code><?= esc($c['slug']) ?></code></td>
                <td><?= $parent ? esc($parent) : '<span class="muted">—</span>' ?></td>
                <td>
                    <?php if (!empty($c['is_active'])): ?>
                        <span class="badge badge-published">Aktif</span>
                    <?php else: ?>
                        <span class="badge badge-muted">Gizli</span>
                    <?php endif; ?>
                </td>
                <td><?= (int) $c['position'] ?></td>
                <td>
                    <a class="btn btn-link" href="<?= esc(url('/admin/kategoriler/' . $c['id'] . '/duzenle')) ?>">Düzenle</a>
                    <a class="btn btn-link" href="<?= esc(url('/' . $c['slug'])) ?>" target="_blank" rel="noopener">Gör</a>
                    <form method="post" action="<?= esc(url('/admin/kategoriler/' . $c['id'] . '/sil')) ?>" style="display:inline" onsubmit="return confirm('Bu kategoriyi silmek istediğinize emin misiniz?');">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-link btn-link-danger">Sil</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
