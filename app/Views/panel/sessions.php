<?php \App\Core\View::layout('base'); ?>
<section class="hero">
    <h1>Aktif Oturumlar</h1>
    <p class="lead">Hesabına giriş yapılmış tüm cihazlar. Tanımadığını gördüğünde uzakta çıkış yapabilirsin.</p>
    <?php require dirname(__DIR__) . '/partials/flash.php'; ?>
</section>

<?php if (empty($sessions)): ?>
    <p class="muted" style="text-align:center;padding:3rem 0">Aktif oturum kaydı yok.</p>
<?php else: ?>
<table class="table">
    <caption class="visually-hidden">Aktif oturumlar listesi — <?= count($sessions) ?> kayıt</caption>
    <thead>
        <tr><th scope="col">Cihaz</th><th scope="col">IP</th><th scope="col">Son aktivite</th><th scope="col">İşlem</th></tr>
    </thead>
    <tbody>
        <?php foreach ($sessions as $s): ?>
            <tr>
                <td>
                    <strong><?= esc((string) ($s['device_kind'] ?? 'Bilinmeyen')) ?></strong>
                    <?php if (!empty($s['is_current'])): ?>
                        <span class="badge badge-published">Bu cihaz</span>
                    <?php endif; ?>
                    <br><small class="muted" style="font-family:var(--mono);font-size:.7rem"><?= esc(mb_substr((string) ($s['user_agent'] ?? ''), 0, 80)) ?></small>
                </td>
                <td class="muted" style="font-family:var(--mono);font-size:.85rem"><?= esc((string) ($s['ip_address'] ?? '—')) ?></td>
                <td class="muted"><?= esc(tr_date($s['last_seen_at'], true)) ?></td>
                <td>
                    <?php if (empty($s['is_current'])): ?>
                        <form method="post" action="<?= esc(url('/panel/oturumlar/' . (int) $s['id'] . '/sil')) ?>"
                              onsubmit="return confirm('Bu oturumu sonlandır?');">
                            <?= csrf_field() ?>
                            <button class="btn" type="submit">Sonlandır</button>
                        </form>
                    <?php else: ?>
                        <span class="muted" style="font-size:.85rem">Mevcut oturum</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
