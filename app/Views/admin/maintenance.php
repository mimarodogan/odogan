<?php \App\Core\View::layout('base'); ?>
<section class="hero">
    <h1>Bakım & Tazelik Kontrolü</h1>
    <p class="lead muted">Eski içerikleri tazele, log/önbelleği yönet.</p>
    <?php require dirname(__DIR__) . '/partials/flash.php'; ?>
</section>

<div class="grid">
    <article class="card">
        <h2>📊 Cache</h2>
        <p class="muted">Driver: <code><?= esc((string) ($cache_stats['driver'] ?? '?')) ?></code></p>
        <?php if (!empty($cache_stats['keyspace'])): ?>
            <pre class="log-ctx"><?= esc(print_r($cache_stats['keyspace'], true)) ?></pre>
        <?php endif; ?>
        <form method="post" action="<?= esc(url('/admin/bakim/cache-temizle')) ?>"
              onsubmit="return confirm('Tüm önbellek silinsin mi?');">
            <?= csrf_field() ?>
            <button class="btn" type="submit">Önbelleği Temizle</button>
        </form>
    </article>

    <article class="card">
        <h2>🗂️ Log Yönetimi</h2>
        <p>Toplam: <strong><?= (int) ($log_stats['total'] ?? 0) ?></strong> satır
            <?php if (!empty($log_stats['oldest_date'])): ?>
                · en eski: <?= esc((string) $log_stats['oldest_date']) ?>
            <?php endif; ?>
        </p>
        <p class="muted">Son 7 gün:</p>
        <ul class="rank-list">
            <?php foreach (($log_stats['last_7d_by_level'] ?? []) as $r): ?>
                <li><span class="log-badge"><?= esc(strtoupper((string) $r['level'])) ?></span>
                    <?= (int) $r['n'] ?></li>
            <?php endforeach; ?>
            <?php if (!($log_stats['last_7d_by_level'] ?? [])): ?>
                <li class="muted">Veri yok</li>
            <?php endif; ?>
        </ul>
        <form method="post" action="<?= esc(url('/admin/bakim/log-temizle')) ?>"
              onsubmit="return confirm('Bu yaştan eski TÜM logları silmek istediğinize emin misiniz?');">
            <?= csrf_field() ?>
            <label><span>Şu kadar günden eski logları sil</span>
                <input type="number" name="older_than_days" value="30" min="1" max="365">
            </label>
            <button class="btn" type="submit">Eskileri Sil</button>
        </form>
    </article>
</div>

<section class="block">
    <h2 class="block-title"><span>🌿</span> Tazelik Kontrolü</h2>
    <form method="get" action="<?= esc(url('/admin/bakim')) ?>" class="form-inline">
        <label class="muted">
            <span>Şu kadar aydır güncellenmemiş:</span>
            <input type="number" name="months" min="1" max="36" value="<?= (int) $months ?>">
        </label>
        <button class="btn" type="submit">Listele</button>
    </form>

    <?php if (!$stale_posts): ?>
        <p class="muted" style="margin-top:1rem">🎉 Hiçbir içerik bayatlamamış (son <?= (int) $months ?> ay).</p>
    <?php else: ?>
        <p class="muted" style="margin-top:1rem">
            <?= count($stale_posts) ?> içerik <?= (int) $months ?> aydır güncellenmemiş.
            Yeniden gözden geçirip "Tazele" butonuyla tarihini güncelleyebilirsiniz
            (içeriği ayrıca düzenlemek için <em>panel/yazilar</em> sayfasına gidin).
        </p>
        <table class="table">
            <caption class="visually-hidden">Bayat içerikler listesi — <?= count($stale_posts) ?> kayıt</caption>
            <thead>
                <tr><th scope="col">Başlık</th><th scope="col">Kategori</th><th scope="col">Yazar</th><th scope="col">Yaş</th><th scope="col"><span class="visually-hidden">İşlemler</span></th></tr>
            </thead>
            <tbody>
            <?php foreach ($stale_posts as $p): ?>
                <tr>
                    <td>
                        <a href="<?= esc(url('/' . $p['category_slug'] . '/' . $p['slug'])) ?>" target="_blank">
                            <?= esc($p['title']) ?>
                        </a>
                        <br><small class="muted">son güncelleme: <?= esc(tr_date($p['updated_at'])) ?></small>
                    </td>
                    <td><?= esc((string) ($p['category_name'] ?? '—')) ?></td>
                    <td><?= esc((string) ($p['author_name'] ?? '—')) ?></td>
                    <td class="muted nowrap"><?= (int) $p['days_old'] ?> gün</td>
                    <td>
                        <a class="btn" href="<?= esc(url('/panel/yazilar/' . $p['id'] . '/duzenle')) ?>">Düzenle</a>
                        <form method="post" action="<?= esc(url('/admin/bakim/tazele/' . $p['id'])) ?>" style="display:inline">
                            <?= csrf_field() ?>
                            <button class="btn btn-primary" type="submit">Tazele</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
