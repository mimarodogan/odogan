<?php \App\Core\View::layout('base'); ?>
<?php
/** @var array<int,array<string,mixed>> $items */
/** @var bool $is_admin */
/** @var bool $is_admin_or_editor */

$pendingItems = array_values(array_filter($items, fn($p) => ($p['approval_stage'] ?? 'none') === 'review'));
$activeItems  = array_values(array_filter($items, fn($p) => ($p['approval_stage'] ?? 'none') !== 'review'));

$statusLabels = [
    'draft'     => ['Taslak',  'badge-draft'],
    'published' => ['Yayında', 'badge-published'],
    'archived'  => ['Arşiv',   'badge-archived'],
];
$stageLabels = [
    'none'     => null,
    'review'   => ['Onayda',   'badge-pending'],
    'approved' => null,
    'rejected' => ['Reddedildi','badge-rejected'],
];
?>
<section class="hero">
    <h1>Projeler</h1>
    <p class="lead">
        <?php if ($is_admin): ?>
            <?= count($items) ?> proje · portfolyo + onay yönetimi.
        <?php elseif ($is_admin_or_editor): ?>
            Tüm projeler — editör görüntüleme.
        <?php else: ?>
            Yarattığınız projeler. Yeni eklediklerin admin onayından sonra yayına çıkar.
        <?php endif; ?>
    </p>
    <?php require dirname(__DIR__, 2) . '/partials/flash.php'; ?>
    <div class="hero-actions">
        <a class="btn btn-primary" href="<?= esc(url('/panel/projeler/yeni')) ?>">+ Yeni Proje</a>
    </div>
</section>

<?php if ($is_admin && !empty($pendingItems)): ?>
<section class="approval-stage">
    <h2 class="stage-title">
        Onay Bekleyen Projeler
        <span class="badge"><?= count($pendingItems) ?></span>
    </h2>
    <ul class="approval-list">
        <?php foreach ($pendingItems as $p): ?>
            <li class="approval-row">
                <div class="approval-meta">
                    <strong class="approval-title"><?= esc($p['name']) ?></strong>
                    <p class="muted">
                        <?= esc($p['location'] ?? '—') ?>
                        <?= $p['year_completed'] ? ' · ' . (int) $p['year_completed'] : '' ?>
                        <?php if (!empty($p['submitted_at'])): ?>
                            · gönderildi: <?= esc($p['submitted_at']) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="approval-actions">
                    <a class="btn btn-secondary btn-sm" href="<?= esc(url('/panel/projeler/' . $p['id'] . '/duzenle')) ?>">İncele</a>
                    <form method="post" action="<?= esc(url('/admin/projeler/' . $p['id'] . '/onayla')) ?>" style="display:inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-primary btn-sm">Yayına Al</button>
                    </form>
                    <form method="post" action="<?= esc(url('/admin/projeler/' . $p['id'] . '/reddet')) ?>" style="display:inline"
                          onsubmit="return confirm('Proje yazara geri gönderilecek. Devam?');">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-link">Reddet</button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<?php if (empty($activeItems) && empty($pendingItems)): ?>
    <div class="empty-card">
        <p>
            <?php if ($is_admin_or_editor): ?>
                Henüz proje yok.
            <?php else: ?>
                Henüz proje eklemediniz. İlk projenizi ekleyip admin onayına gönderebilirsiniz.
            <?php endif; ?>
        </p>
        <a class="btn btn-secondary" href="<?= esc(url('/panel/projeler/yeni')) ?>">İlk projeyi ekle →</a>
    </div>
<?php elseif (!empty($activeItems)): ?>
    <table class="table">
        <caption class="visually-hidden">Projeler listesi — <?= count($activeItems) ?> kayıt</caption>
        <thead>
            <tr>
                <th scope="col">Ad</th>
                <th scope="col">Lokasyon</th>
                <th scope="col">Yıl</th>
                <th scope="col">Durum</th>
                <?php if ($is_admin_or_editor): ?>
                    <th scope="col">Yazar</th>
                <?php endif; ?>
                <th scope="col"><span class="visually-hidden">İşlemler</span></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($activeItems as $p):
                $status = $p['status'] ?? 'draft';
                [$sLabel, $sBadge] = $statusLabels[$status] ?? [$status, 'badge-draft'];
                $stage = $p['approval_stage'] ?? 'none';
                $stageInfo = $stageLabels[$stage] ?? null;
            ?>
                <tr>
                    <td>
                        <strong><?= esc($p['name']) ?></strong>
                        <?php if (!empty($p['featured'])): ?>
                            <span class="badge badge-accent">Öne çıkan</span>
                        <?php endif; ?>
                        <br><small class="muted"><?= esc($p['slug']) ?></small>
                    </td>
                    <td><?= esc($p['location'] ?? '—') ?></td>
                    <td><?= $p['year_completed'] ? (int) $p['year_completed'] : '—' ?></td>
                    <td>
                        <span class="badge <?= esc($sBadge) ?>"><?= esc($sLabel) ?></span>
                        <?php if ($stageInfo): ?>
                            <span class="badge <?= esc($stageInfo[1]) ?>"><?= esc($stageInfo[0]) ?></span>
                        <?php endif; ?>
                    </td>
                    <?php if ($is_admin_or_editor): ?>
                        <td><small class="muted">#<?= (int) ($p['user_id'] ?? 0) ?></small></td>
                    <?php endif; ?>
                    <td>
                        <a class="btn btn-link" href="<?= esc(url('/panel/projeler/' . $p['id'] . '/duzenle')) ?>">Düzenle</a>
                        <?php if ($status === 'published'): ?>
                            <a class="btn btn-link" href="<?= esc(url('/proje/' . $p['slug'])) ?>" target="_blank" rel="noopener">Gör</a>
                        <?php endif; ?>
                        <?php if ($is_admin && $status !== 'published'): ?>
                            <form method="post" action="<?= esc(url('/admin/projeler/' . $p['id'] . '/onayla')) ?>" style="display:inline" onsubmit="return confirm('Proje yayına alınacak. Devam?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-link" style="color:var(--cobalt);font-weight:700">Yayına Al</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($is_admin): ?>
                            <form method="post" action="<?= esc(url('/admin/projeler/' . $p['id'] . '/sil')) ?>" style="display:inline" onsubmit="return confirm('Bu projeyi silmek istediğinize emin misiniz?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-link btn-link-danger">Sil</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
