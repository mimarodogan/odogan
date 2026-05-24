<?php \App\Core\View::layout('base'); ?>
<?php
$_statusLabels = [
    'pending'  => ['Bekliyor', 'badge-pending'],
    'approved' => ['Onaylandı', 'badge-published'],
    'rejected' => ['Reddedildi', 'badge-rejected'],
];
?>
<section class="hero">
    <h1>Yazar Başvuruları</h1>
    <p class="lead">/yazar-ol formuyla gelen başvuruları inceleyin, onaylayın veya reddedin.</p>
    <?php require dirname(__DIR__, 2) . '/partials/flash.php'; ?>

    <nav class="filter-tabs" style="margin-top:1rem;display:flex;gap:.5rem;flex-wrap:wrap">
        <?php foreach (['pending','approved','rejected','all'] as $k):
            $label = ['pending'=>'Bekleyen','approved'=>'Onaylanan','rejected'=>'Reddedilen','all'=>'Tümü'][$k];
            $n = $counts[$k] ?? null;
            $active = $status === $k;
        ?>
            <a href="<?= esc(url('/admin/yazar-basvurulari?status=' . $k)) ?>"
               class="btn <?= $active ? 'btn-primary' : '' ?>">
                <?= $label ?>
                <?php if ($k !== 'all' && isset($counts[$k])): ?>
                    <span style="opacity:.7">(<?= (int) $counts[$k] ?>)</span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
</section>

<?php if (empty($list)): ?>
    <p class="muted" style="text-align:center;padding:3rem 0">Bu kategoride başvuru yok.</p>
<?php else: ?>
<table class="table">
    <caption class="visually-hidden">Yazar başvuruları listesi — <?= count($list) ?> kayıt</caption>
    <thead>
        <tr><th scope="col">Ad</th><th scope="col">E-posta</th><th scope="col">Durum</th><th scope="col">Başvuru</th><th scope="col">İşlem</th></tr>
    </thead>
    <tbody>
        <?php foreach ($list as $u):
            $st = $_statusLabels[$u['app_status']] ?? [$u['app_status'], 'badge-draft'];
        ?>
            <tr>
                <td><strong><?= esc($u['name']) ?></strong></td>
                <td><?= esc($u['email']) ?></td>
                <td><span class="badge <?= esc($st[1]) ?>"><?= esc($st[0]) ?></span></td>
                <td class="muted"><?= !empty($u['app_at']) ? esc(tr_date($u['app_at'], true)) : '—' ?></td>
                <td>
                    <a class="btn" href="<?= esc(url('/admin/yazar-basvurulari/' . (int) $u['id'])) ?>">İncele</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
