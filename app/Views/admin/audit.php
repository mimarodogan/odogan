<?php \App\Core\View::layout('base'); ?>
<section class="hero">
    <h1>Audit Log</h1>
    <p class="lead">Tüm hassas admin işlemlerinin denetim kaydı. Toplam: <strong><?= (int) $total ?></strong> kayıt.</p>
    <?php require dirname(__DIR__) . '/partials/flash.php'; ?>

    <form method="get" action="<?= esc(url('/admin/audit')) ?>" style="margin-top:1rem;display:flex;gap:.5rem;align-items:center">
        <label class="muted" for="audit-action-filter" style="font-size:.85rem">Aksiyon filtresi:</label>
        <input type="text" id="audit-action-filter" name="action" value="<?= esc((string) $filter_action) ?>" placeholder="örn: redirect.created" style="width:240px;font-family:var(--mono);font-size:.85rem">
        <button class="btn" type="submit">Filtrele</button>
        <?php if ($filter_action): ?>
            <a class="btn" href="<?= esc(url('/admin/audit')) ?>">Temizle</a>
        <?php endif; ?>
    </form>
</section>

<?php if (empty($logs)): ?>
    <p class="muted" style="text-align:center;padding:3rem 0">Kayıt yok.</p>
<?php else: ?>
<table class="table">
    <caption class="visually-hidden">Denetim kayıtları listesi — <?= count($logs) ?> kayıt</caption>
    <thead>
        <tr><th scope="col">Tarih</th><th scope="col">Aktör</th><th scope="col">Aksiyon</th><th scope="col">Hedef</th><th scope="col">Özet</th><th scope="col">IP</th></tr>
    </thead>
    <tbody>
        <?php foreach ($logs as $l): ?>
            <tr>
                <td class="muted"><?= esc(tr_date($l['created_at'], true)) ?></td>
                <td><strong><?= esc((string) ($l['actor_name'] ?? '—')) ?></strong></td>
                <td><code style="font-family:var(--mono);font-size:.82rem"><?= esc($l['action']) ?></code></td>
                <td>
                    <?php if ($l['target_type']): ?>
                        <code style="font-family:var(--mono);font-size:.78rem"><?= esc($l['target_type']) ?>:<?= (int) $l['target_id'] ?></code>
                    <?php else: ?>
                        <span class="muted">—</span>
                    <?php endif; ?>
                </td>
                <td><?= esc((string) ($l['summary'] ?? '')) ?></td>
                <td class="muted" style="font-family:var(--mono);font-size:.78rem"><?= esc((string) ($l['ip_address'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
