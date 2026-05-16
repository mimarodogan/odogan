<?php \App\Core\View::layout('base'); ?>
<?php
$_bulkEnabled  = function_exists('feature') && feature('bulk_actions_enabled');
$_quickEnabled = function_exists('feature') && feature('quick_edit_enabled');
$_categories = $categories ?? \App\Models\Category::all(true);
$_authUser = \App\Services\AuthService::user();
$_isAdmin = ($_authUser['role'] ?? '') === \App\Models\User::ROLE_ADMIN;
?>
<section class="hero">
    <h1>İçeriklerim</h1>
    <p class="lead">Taslakları yazıp onaya gönderin; editör onayından sonra silo URL'inde yayınlanır.</p>
    <?php require dirname(dirname(__DIR__)) . '/partials/flash.php'; ?>
    <p><a class="btn btn-primary" href="<?= esc(url('/panel/yazilar/yeni')) ?>">+ Yeni İçerik</a></p>
</section>

<?php if (!$posts): ?>
    <p class="muted">Henüz içerik yok. <a href="<?= esc(url('/panel/yazilar/yeni')) ?>">İlk içeriğini oluştur</a>.</p>
<?php else: ?>

<?php if ($_bulkEnabled): ?>
<form id="bulk-form" method="post" action="<?= esc(url('/panel/yazilar/toplu')) ?>" class="bulk-toolbar"
      onsubmit="return bulkConfirm(this)">
    <?= csrf_field() ?>
    <span class="bulk-count" data-bulk-count>0 yazı seçili</span>
    <label class="visually-hidden" for="bulk-action">Toplu işlem</label>
    <select id="bulk-action" name="bulk_action" required>
        <option value="">— İşlem seç —</option>
        <option value="draft">Taslağa Çek</option>
        <option value="publish">Yayınla</option>
        <option value="archive">Arşivle</option>
        <option value="change_category">Kategori Değiştir…</option>
        <option value="add_tag">Etiket Ekle…</option>
        <?php if ($_isAdmin): ?>
            <option value="delete">Sil (geri alınamaz)</option>
        <?php endif; ?>
    </select>
    <select name="new_category_id" data-bulk-cat hidden>
        <option value="">— Yeni kategori —</option>
        <?php foreach ($_categories as $c): ?>
            <option value="<?= (int) $c['id'] ?>"><?= esc($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="text" name="new_tag" data-bulk-tag placeholder="Etiket adı" hidden>
    <button class="btn" type="submit">Uygula</button>
</form>
<?php endif; ?>

<table class="table table-posts">
    <caption class="visually-hidden">Yazılarım listesi — <?= count($posts) ?> kayıt</caption>
    <thead>
        <tr>
            <?php if ($_bulkEnabled): ?>
                <th scope="col" class="col-check"><input type="checkbox" data-bulk-all aria-label="Tümünü seç" form="bulk-form"></th>
            <?php endif; ?>
            <th scope="col">Başlık</th>
            <th scope="col">Kategori</th>
            <th scope="col">Durum</th>
            <th scope="col">Güncelleme</th>
            <th scope="col"><span class="visually-hidden">İşlemler</span></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($posts as $p): ?>
        <tr data-post-row="<?= (int) $p['id'] ?>">
            <?php if ($_bulkEnabled): ?>
                <td class="col-check">
                    <input type="checkbox" name="ids[]" value="<?= (int) $p['id'] ?>"
                           data-bulk-item form="bulk-form" aria-label="Seç: <?= esc($p['title']) ?>">
                </td>
            <?php endif; ?>
            <td><strong data-row-title><?= esc($p['title']) ?></strong>
                <?php if (!empty($p['featured'])): ?>
                    <span class="badge" title="Editörün Seçimi" style="background:var(--cobalt,#1e3a8a);color:#fff;font-size:.7rem;padding:.1rem .35rem;border-radius:3px">⭐</span>
                <?php endif; ?>
            </td>
            <td><?= esc((string) ($p['category_name'] ?? '—')) ?></td>
            <td>
                <span class="badge badge-<?= esc($p['status']) ?>" data-row-status><?= esc(ucfirst((string) $p['status'])) ?></span>
                <?php if ($p['status'] === 'scheduled' && !empty($p['published_at'])): ?>
                    <br><small class="muted">📅 <?= esc(tr_date($p['published_at'], true)) ?></small>
                <?php endif; ?>
            </td>
            <td class="muted"><?= esc(tr_date($p['updated_at'], true)) ?></td>
            <td>
                <a class="btn" href="<?= esc(url('/panel/yazilar/' . $p['id'] . '/duzenle')) ?>">Düzenle</a>
                <?php if ($_quickEnabled): ?>
                    <button type="button" class="btn btn-quick"
                            data-quick-edit="<?= (int) $p['id'] ?>"
                            data-quick-title="<?= esc($p['title']) ?>"
                            data-quick-slug="<?= esc($p['slug']) ?>"
                            data-quick-status="<?= esc($p['status']) ?>"
                            data-quick-featured="<?= (int) ($p['featured'] ?? 0) ?>"
                            data-quick-url="<?= esc(url('/panel/yazilar/' . $p['id'] . '/hizli-guncelle')) ?>"
                            title="Hızlı düzenle">⚡</button>
                <?php endif; ?>
                <?php if ($p['status'] === 'published' && !empty($p['category_slug'])): ?>
                    <a class="btn" target="_blank"
                       href="<?= esc(url('/' . $p['category_slug'] . '/' . $p['slug'])) ?>">Görüntüle</a>
                <?php endif; ?>
                <?php if ($_isAdmin): ?>
                <form method="post" action="<?= esc(url('/panel/yazilar/' . $p['id'] . '/sil')) ?>"
                      onsubmit="return confirm('Bu yazı KALICI olarak silinecek. Geri alınamaz. Devam?');" style="display:inline">
                    <?= csrf_field() ?>
                    <button class="btn">Sil</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if ($_quickEnabled): ?>
<?php require dirname(dirname(__DIR__)) . '/partials/quick-edit-modal.php'; ?>
<?php endif; ?>

<?php if ($_bulkEnabled): ?>
<script src="<?= esc(asset('js/bulk-actions.js')) ?>" defer></script>
<?php endif; ?>
<?php if ($_quickEnabled): ?>
<script src="<?= esc(asset('js/quick-edit.js')) ?>" defer></script>
<?php endif; ?>
