<?php \App\Core\View::layout('base'); ?>
<section class="hero">
    <h1>404 Logları</h1>
    <p class="lead">Bulunamayan URL'ler — yakın eşleşme önerisi ile yönlendirme kurabilirsin.</p>
    <?php require dirname(__DIR__) . '/partials/flash.php'; ?>
    <p style="margin-top:1rem">
        <a class="btn <?= $unresolved_only ? 'btn-primary' : '' ?>" href="<?= esc(url('/admin/404-loglari')) ?>">Bekleyen</a>
        <a class="btn <?= !$unresolved_only ? 'btn-primary' : '' ?>" href="<?= esc(url('/admin/404-loglari?all=1')) ?>">Tümü</a>
    </p>
</section>

<?php if (empty($logs)): ?>
    <p class="muted" style="text-align:center;padding:3rem 0">404 logu yok. Mükemmel!</p>
<?php else: ?>
<table class="table">
    <caption class="visually-hidden">404 logları — <?= count($logs) ?> kayıt</caption>
    <thead>
        <tr><th scope="col">Yol</th><th scope="col">Hit</th><th scope="col">Son görülme</th><th scope="col">Öneriler</th><th scope="col">İşlem</th></tr>
    </thead>
    <tbody>
        <?php foreach ($logs as $log): ?>
            <tr>
                <td style="font-family:var(--mono);font-size:.85rem"><?= esc($log['path']) ?></td>
                <td><?= (int) $log['hit_count'] ?></td>
                <td class="muted"><?= esc(tr_date($log['last_seen'], true)) ?></td>
                <td>
                    <?php if (!empty($log['suggestions'])): ?>
                        <ul style="margin:0;padding-left:1.25rem;font-size:.85rem">
                            <?php foreach ($log['suggestions'] as $s): ?>
                                <li>
                                    <code><?= esc($s['url']) ?></code>
                                    <span class="muted">(Δ<?= (int) $s['distance'] ?>)</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <span class="muted">yok</span>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="post" action="<?= esc(url('/admin/404-loglari/' . (int) $log['id'] . '/yonlendir')) ?>" style="display:flex;gap:.5rem;align-items:center">
                        <?= csrf_field() ?>
                        <input type="text" name="to_url" placeholder="Hedef URL" required style="width:200px;font-family:var(--mono);font-size:.85rem"
                               <?php if (!empty($log['suggestions'][0]['url'])): ?>value="<?= esc($log['suggestions'][0]['url']) ?>"<?php endif; ?>>
                        <button class="btn" type="submit">Yönlendir</button>
                    </form>
                    <form method="post" action="<?= esc(url('/admin/404-loglari/' . (int) $log['id'] . '/sil')) ?>"
                          onsubmit="return confirm('Silinsin mi?');" style="margin-top:.4rem">
                        <?= csrf_field() ?>
                        <button class="btn" type="submit">Sil</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
