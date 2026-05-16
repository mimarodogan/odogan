<?php
/**
 * @var array $subscribers
 * @var array $stats
 * @var array $brevo_status
 */
\App\Core\View::layout('base');
?>
<section class="hero">
    <h1>Newsletter Aboneleri</h1>
    <p class="lead muted">
        Toplam: <strong><?= (int) $stats['total'] ?></strong> ·
        Onaylı: <strong><?= (int) $stats['confirmed'] ?></strong> ·
        Onay bekleyen: <strong><?= (int) $stats['pending'] ?></strong>
    </p>
    <?php require dirname(__DIR__, 1) . '/../partials/flash.php'; ?>
</section>

<div class="grid">
    <article class="card">
        <h2>📡 Brevo Bağlantısı</h2>
        <?php if ($brevo_status['ok'] ?? false): ?>
            <p class="flash flash-success">✓ Brevo bağlantısı çalışıyor.</p>
        <?php else: ?>
            <p class="flash flash-error">
                ✗ Brevo bağlantısı sorunlu: <?= esc((string) ($brevo_status['error'] ?? '?')) ?>
            </p>
        <?php endif; ?>
        <p>
            <a class="btn" href="<?= esc(url('/admin/newsletter/ayarlar')) ?>" title="API key + list ID yapılandır">
                Brevo Ayarları
            </a>
            <a class="btn" href="<?= esc(url('/admin/newsletter/csv')) ?>" title="Abone listesini CSV indir">
                CSV Dışa Aktar
            </a>
        </p>
    </article>
</div>

<section style="margin-top:2rem">
    <h2>Aboneler (son 500)</h2>
    <?php if (!$subscribers): ?>
        <p class="muted">Henüz abone yok.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <caption class="visually-hidden">Bülten aboneleri — <?= count($subscribers) ?> kayıt</caption>
                <thead>
                    <tr>
                        <th scope="col">E-posta</th>
                        <th scope="col">İsim</th>
                        <th scope="col">Durum</th>
                        <th scope="col">Kayıt Tarihi</th>
                        <th scope="col">Brevo ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscribers as $s): ?>
                        <tr>
                            <td><code><?= esc((string) $s['email']) ?></code></td>
                            <td><?= esc((string) ($s['name'] ?? '')) ?></td>
                            <td>
                                <?php if (!empty($s['confirmed_at'])): ?>
                                    <span class="badge badge-published">Onaylı</span>
                                <?php else: ?>
                                    <span class="badge badge-pending">Onay bekliyor</span>
                                <?php endif; ?>
                            </td>
                            <td><?= esc(date('d/m/Y H:i', strtotime((string) $s['created_at']))) ?></td>
                            <td><code><?= esc((string) ($s['brevo_contact_id'] ?? '—')) ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
