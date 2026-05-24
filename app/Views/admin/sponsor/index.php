<?php \App\Core\View::layout('base'); ?>
<?php /** @var array<int,array<string,mixed>> $items */ ?>
<section class="hero">
    <h1>Sponsor Slotları</h1>
    <p class="lead">Bülten, sidebar veya yazı altı banner — tıklama sayacı + tarih aralığı + ağırlık tabanlı rotasyon.</p>
    <?php require dirname(__DIR__, 2) . '/partials/flash.php'; ?>
    <div class="hero-actions">
        <a class="btn btn-primary" href="<?= esc(url('/admin/sponsor/yeni')) ?>">+ Yeni Sponsor</a>
    </div>
</section>

<?php if (empty($items)): ?>
    <div class="empty-card">
        <p>Henüz sponsor slotu yok.</p>
        <a class="btn btn-secondary" href="<?= esc(url('/admin/sponsor/yeni')) ?>">İlk sponsoru ekle →</a>
    </div>
<?php else: ?>
    <table class="table">
        <caption class="visually-hidden">Sponsor slotları — <?= count($items) ?> kayıt</caption>
        <thead>
            <tr>
                <th scope="col">Ad</th>
                <th scope="col">Yerleşim</th>
                <th scope="col">Hedef</th>
                <th scope="col">Görünüm</th>
                <th scope="col">Tık</th>
                <th scope="col">CTR</th>
                <th scope="col">Tarih</th>
                <th scope="col"><span class="visually-hidden">İşlemler</span></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $s):
                $ctr = $s['view_count'] > 0 ? round(($s['click_count'] / $s['view_count']) * 100, 2) : 0;
            ?>
                <tr>
                    <td>
                        <strong><?= esc($s['name']) ?></strong>
                        <?php if (!$s['active']): ?>
                            <span class="badge badge-muted">pasif</span>
                        <?php endif; ?>
                        <?php if (!empty($s['tagline'])): ?>
                            <br><small class="muted"><?= esc($s['tagline']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><code><?= esc($s['placement']) ?></code></td>
                    <td>
                        <a href="<?= esc($s['target_url']) ?>" target="_blank" rel="noopener nofollow">
                            <?= esc(mb_substr($s['target_url'], 0, 32)) ?>…
                        </a>
                    </td>
                    <td><?= (int) $s['view_count'] ?></td>
                    <td><?= (int) $s['click_count'] ?></td>
                    <td>%<?= number_format($ctr, 2) ?></td>
                    <td><small class="muted">
                        <?= esc($s['starts_at'] ?? '—') ?><br>
                        <?= esc($s['ends_at'] ?? '—') ?>
                    </small></td>
                    <td>
                        <a class="btn btn-link" href="<?= esc(url('/admin/sponsor/' . $s['id'] . '/duzenle')) ?>">Düzenle</a>
                        <form method="post" action="<?= esc(url('/admin/sponsor/' . $s['id'] . '/sil')) ?>" style="display:inline" onsubmit="return confirm('Sponsor slot silinsin mi?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-link btn-link-danger">Sil</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
