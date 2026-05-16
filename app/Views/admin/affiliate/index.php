<?php \App\Core\View::layout('base'); ?>
<section class="hero">
    <h1>Affiliate Linkleri</h1>
    <p class="lead">Yazılarda <code>/git/{code}</code> formatlı linkler — tıklamayı sayar, hedefe yönlendirir.</p>
    <?php require dirname(__DIR__, 2) . '/partials/flash.php'; ?>
</section>

<form method="post" action="<?= esc(url('/admin/affiliate')) ?>" class="form form-wide" style="margin-bottom:3rem">
    <?= csrf_field() ?>
    <fieldset>
        <legend>Yeni Affiliate Link</legend>
        <div style="display:grid;grid-template-columns:1fr 2fr 2fr 1fr;gap:1rem;align-items:end">
            <label>
                <span>Code</span>
                <input type="text" name="code" required maxlength="40" pattern="[a-z0-9-]+" placeholder="ab-mimarlik">
            </label>
            <label>
                <span>Etiket</span>
                <input type="text" name="label" required maxlength="160" placeholder="ABC Mimarlık">
            </label>
            <label>
                <span>Hedef URL</span>
                <input type="url" name="to_url" required maxlength="500" placeholder="https://...">
            </label>
            <label>
                <span>Partner</span>
                <input type="text" name="partner" maxlength="120">
            </label>
        </div>
        <button class="btn btn-primary" type="submit" style="margin-top:1rem">Ekle</button>
    </fieldset>
</form>

<?php if (empty($list)): ?>
    <p class="muted" style="text-align:center;padding:2rem 0">Henüz affiliate link yok.</p>
<?php else: ?>
<table class="table">
    <caption class="visually-hidden">Affiliate linkler — <?= count($list) ?> kayıt</caption>
    <thead>
        <tr><th scope="col">Code</th><th scope="col">Etiket</th><th scope="col">Hedef</th><th scope="col">Partner</th><th scope="col">Tıklama</th><th scope="col">Public URL</th><th scope="col">İşlem</th></tr>
    </thead>
    <tbody>
        <?php foreach ($list as $a): ?>
            <tr>
                <td><code><?= esc($a['code']) ?></code></td>
                <td><?= esc($a['label']) ?></td>
                <td style="font-family:var(--mono);font-size:.8rem"><?= esc(mb_substr($a['to_url'], 0, 60)) ?>…</td>
                <td><?= esc((string) ($a['partner'] ?? '—')) ?></td>
                <td><?= (int) $a['click_count'] ?></td>
                <td><code style="font-family:var(--mono);font-size:.8rem"><?= esc(url('/git/' . $a['code'])) ?></code></td>
                <td>
                    <form method="post" action="<?= esc(url('/admin/affiliate/' . (int) $a['id'] . '/sil')) ?>"
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
