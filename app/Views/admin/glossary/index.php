<?php \App\Core\View::layout('base'); ?>
<section class="hero">
    <h1>Mimari Sözlük</h1>
    <p class="lead">
        Mimari ve mühendislik terimleri sözlüğü — public: <code>/sozluk</code>
        <?php if (!empty($pending_count)): ?>
            · <span class="badge badge-rejected" style="margin-left:.5rem"><?= (int) $pending_count ?> onay bekliyor</span>
        <?php endif; ?>
    </p>
    <?php require dirname(__DIR__, 2) . '/partials/flash.php'; ?>
    <p style="margin-top:1rem">
        <a class="btn btn-primary" href="<?= esc(url('/admin/sozluk/yeni')) ?>">+ Yeni Terim</a>
        <?php if (function_exists('feature') && feature('glossary_ai_enabled')): ?>
            <a class="btn" href="<?= esc(url('/admin/sozluk/toplu')) ?>">⚡ Toplu AI Üretim</a>
            <?php // Q6: Toplu drift denetimi ?>
            <form method="post" action="<?= esc(url('/admin/sozluk/toplu-denetle')) ?>"
                  style="display:inline"
                  onsubmit="return confirm('Tüm sözlük terimlerinin AI bağlam denetimi çalıştırılsın mı? Bu işlem 1-2 dakika sürebilir.');">
                <?= csrf_field() ?>
                <button class="btn" type="submit" title="Tüm terimleri AI ile bağlam denetiminden geçir">
                    🛡️ Tüm Terimleri Denetle
                </button>
            </form>
        <?php endif; ?>
        <a class="btn" target="_blank" href="<?= esc(url('/sozluk')) ?>">Public Sözlüğü Aç</a>
    </p>
</section>

<?php if (empty($list)): ?>
    <p class="muted" style="text-align:center;padding:3rem 0">Henüz terim yok. İlkini ekleyin.</p>
<?php else: ?>
<table class="table glossary-admin-list">
    <caption class="visually-hidden">Sözlük terimleri — <?= count($list) ?> kayıt, onay bekleyenler üstte</caption>
    <thead>
        <tr>
            <th scope="col" style="width:1.4rem" aria-label="Durum"></th>
            <th scope="col">Terim</th>
            <th scope="col">Kategori</th>
            <th scope="col">Kalite</th>
            <th scope="col">Slug</th>
            <th scope="col" style="text-align:right">Görüntülenme</th>
            <th scope="col" colspan="3">İşlem</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($list as $g):
            $isActive = ((int) ($g['is_active'] ?? 0)) === 1;
            $id = (int) $g['id'];
        ?>
            <tr class="<?= $isActive ? 'gli-active' : 'gli-pending' ?>">
                <td style="text-align:center">
                    <?php if ($isActive): ?>
                        <span class="gli-tick" title="Onaylı — public sözlükte görünür" aria-label="Aktif">✓</span>
                    <?php else: ?>
                        <span class="gli-pending-dot" title="Onay bekliyor — public sözlükte görünmez" aria-label="Pasif">●</span>
                    <?php endif; ?>
                </td>
                <td>
                    <strong><?= esc($g['term']) ?></strong>
                    <?php if (!$isActive): ?>
                        <span class="badge badge-rejected" style="margin-left:.5rem;font-size:.65rem">TASLAK</span>
                    <?php endif; ?>
                </td>
                <td><?= esc((string) ($g['category'] ?? '—')) ?></td>
                <td>
                    <?php
                    $_q = $g['quality_score'] ?? null;
                    $_d = !empty($g['drift_flag']);
                    if ($_q === null): ?>
                        <span class="badge badge-draft" title="Henüz denetlenmedi">—</span>
                    <?php elseif ($_d): ?>
                        <span class="badge badge-rejected" title="Bağlam kayması — bu terim yanlış anlamda yorumlanmış olabilir">🔴 <?= (int) $_q ?>/100</span>
                    <?php elseif ((int) $_q >= 75): ?>
                        <span class="badge badge-published" title="Bağlam doğru">🟢 <?= (int) $_q ?>/100</span>
                    <?php else: ?>
                        <span class="badge badge-pending" title="Belirsiz — manuel kontrol önerilir">🟡 <?= (int) $_q ?>/100</span>
                    <?php endif; ?>
                </td>
                <td><code><?= esc($g['slug']) ?></code></td>
                <td style="text-align:right"><?= (int) $g['view_count'] ?></td>
                <td>
                    <?php if (!$isActive): ?>
                        <form method="post" action="<?= esc(url('/admin/sozluk/' . $id . '/aktif')) ?>"
                              style="display:inline">
                            <?= csrf_field() ?>
                            <button class="btn btn-primary gli-approve" type="submit"
                                    title="Bu terimi onayla → public sözlükte yayınla">
                                ✓ Onayla
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="<?= esc(url('/admin/sozluk/' . $id . '/aktif')) ?>"
                              style="display:inline"
                              onsubmit="return confirm('Bu terim public sözlükten kaldırılsın mı?');">
                            <?= csrf_field() ?>
                            <button class="btn btn-ghost" type="submit"
                                    title="Bu terimi pasifleştir → public sözlükten kaldır">
                                Pasifleştir
                            </button>
                        </form>
                    <?php endif; ?>
                </td>
                <td>
                    <a class="btn" href="<?= esc(url('/admin/sozluk/' . $id . '/duzenle')) ?>">Düzenle</a>
                </td>
                <td>
                    <form method="post" action="<?= esc(url('/admin/sozluk/' . $id . '/sil')) ?>"
                          onsubmit="return confirm('Silinsin mi? Bu işlem geri alınamaz.');" style="display:inline">
                        <?= csrf_field() ?>
                        <button class="btn" type="submit">Sil</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<style>
/* H1: Sözlük admin listesi — aktivasyon onayı görsel sistemi */
.glossary-admin-list tr.gli-pending {
    background: rgba(176, 36, 29, .035); /* hafif kırmızı zemin — dikkat çek */
}
.glossary-admin-list tr.gli-pending:hover {
    background: rgba(176, 36, 29, .07);
}
.gli-tick {
    display: inline-block;
    width: 1.5rem;
    height: 1.5rem;
    line-height: 1.5rem;
    text-align: center;
    background: var(--ok, #2F6A3E);
    color: #fff;
    border-radius: 50%;
    font-size: .85rem;
    font-weight: 700;
}
.gli-pending-dot {
    display: inline-block;
    width: 1.5rem;
    height: 1.5rem;
    line-height: 1.5rem;
    text-align: center;
    color: var(--err, #B0241D);
    font-size: 1.4rem;
}
.gli-approve {
    background: var(--ok, #2F6A3E);
    border-color: var(--ok, #2F6A3E);
    color: #fff;
}
.gli-approve:hover {
    background: #1f4d2c;
    border-color: #1f4d2c;
}
/* Q5/Q6: Drift olanları daha belirgin yap */
.glossary-admin-list tr td .badge-rejected {
    font-weight: 600;
}
</style>
