<?php \App\Core\View::layout('base'); ?>
<section class="hero">
    <h1>URL Yönlendirmeleri</h1>
    <p class="lead">Eski → yeni URL eşleştirme. 404 öncesi router check eder.</p>
    <?php require dirname(__DIR__, 2) . '/partials/flash.php'; ?>
</section>

<form method="post" action="<?= esc(url('/admin/yonlendirmeler')) ?>" class="form form-wide" style="margin-bottom:3rem">
    <?= csrf_field() ?>
    <fieldset>
        <legend>Yeni Yönlendirme</legend>
        <div style="display:grid;grid-template-columns:2fr 2fr 1fr 1fr;gap:1rem;align-items:end">
            <label>
                <span>Kaynak Yol</span>
                <input type="text" name="from_path" required maxlength="500" placeholder="/eski-yazi-slug">
            </label>
            <label>
                <span>Hedef URL</span>
                <input type="text" name="to_url" required maxlength="500" placeholder="/yeni-kategori/yeni-slug">
            </label>
            <label>
                <span>Kod</span>
                <select name="code">
                    <option value="301">301 Kalıcı</option>
                    <option value="302">302 Geçici</option>
                    <option value="307">307 Geçici (method preserve)</option>
                    <option value="308">308 Kalıcı (method preserve)</option>
                </select>
            </label>
            <button class="btn btn-primary" type="submit">Ekle</button>
        </div>
        <label style="margin-top:1rem">
            <span>Not (opsiyonel)</span>
            <input type="text" name="note" maxlength="255" placeholder="Bu yönlendirmenin sebebi…">
        </label>
    </fieldset>
</form>

<?php if (empty($list)): ?>
    <p class="muted" style="text-align:center;padding:2rem 0">Henüz yönlendirme yok.</p>
<?php else: ?>
<table class="table">
    <caption class="visually-hidden">URL yönlendirmeleri — <?= count($list) ?> kayıt</caption>
    <thead>
        <tr><th scope="col">Kaynak</th><th scope="col">Hedef</th><th scope="col">Kod</th><th scope="col">Hit</th><th scope="col">Aktif</th><th scope="col" colspan="2">İşlem</th></tr>
    </thead>
    <tbody>
        <?php foreach ($list as $r): $fid = 'r-u-' . (int) $r['id']; ?>
            <form id="<?= esc($fid) ?>" method="post" action="<?= esc(url('/admin/yonlendirmeler/' . (int) $r['id'])) ?>" hidden>
                <?= csrf_field() ?>
            </form>
            <tr>
                <td><input form="<?= esc($fid) ?>" type="text" name="from_path" value="<?= esc($r['from_path']) ?>" maxlength="500" style="font-family:var(--mono);font-size:.85rem"></td>
                <td><input form="<?= esc($fid) ?>" type="text" name="to_url" value="<?= esc($r['to_url']) ?>" maxlength="500" style="font-family:var(--mono);font-size:.85rem"></td>
                <td>
                    <select form="<?= esc($fid) ?>" name="code">
                        <?php foreach ([301,302,307,308] as $c): ?>
                            <option value="<?= $c ?>" <?= (int) $r['code'] === $c ? 'selected' : '' ?>><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><?= (int) $r['hit_count'] ?></td>
                <td>
                    <select form="<?= esc($fid) ?>" name="is_active">
                        <option value="1" <?= $r['is_active'] ? 'selected' : '' ?>>Evet</option>
                        <option value="0" <?= !$r['is_active'] ? 'selected' : '' ?>>Hayır</option>
                    </select>
                </td>
                <td>
                    <button class="btn" form="<?= esc($fid) ?>" type="submit">Güncelle</button>
                </td>
                <td>
                    <form method="post" action="<?= esc(url('/admin/yonlendirmeler/' . (int) $r['id'] . '/sil')) ?>"
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
