<?php \App\Core\View::layout('base'); ?>
<?php
$role = (string) ($user['role'] ?? '');
$isAdmin = $role === 'admin';
$isStaff = $isAdmin || $role === 'editor';
$isMember = $role === \App\Models\User::ROLE_MEMBER;
$canApplyAuthor = $isMember
    && function_exists('feature') && feature('author_application');
$appStatus = (string) ($user['author_application_status'] ?? 'none');
$pendingComments = 0;
$pendingPosts = 0;
$staleCount = 0;
$brokenLinks = 0;
$scheduledCount = 0;
// Defensive count helper — never let a missing migration crash the dashboard.
$dashCount = static function (string $sql): int {
    try {
        return (int) \App\Core\Database::instance()->fetchColumn($sql);
    } catch (\Throwable) {
        return 0;
    }
};
if ($isStaff) {
    $pendingComments = $dashCount("SELECT COUNT(*) FROM comments WHERE status='pending'");
    $pendingPosts = $dashCount("SELECT COUNT(*) FROM posts WHERE status='pending'");
    $scheduledCount = $dashCount("SELECT COUNT(*) FROM posts WHERE status='scheduled'");
}
if ($isAdmin) {
    try { $staleCount = \App\Controllers\Admin\MaintenanceController::staleCount(3); } catch (\Throwable) {}
    try { $brokenLinks = \App\Services\LinkChecker::brokenCount(); } catch (\Throwable) {}
}
?>
<section class="hero">
    <h1>Hoş geldin, <?= esc($user['name']) ?></h1>
    <?php
    $_dashRoleNames = ['admin' => 'Yönetici', 'editor' => 'Editör', 'author' => 'Yazar'];
    $_dashRoleLabel = $_dashRoleNames[$role] ?? ucfirst($role);
    ?>
    <p class="lead">Rolün: <strong><?= esc($_dashRoleLabel) ?></strong></p>
    <?php require dirname(__DIR__) . '/partials/flash.php'; ?>

    <?php if (empty($user['email_verified_at'])): ?>
    <div class="flash flash-error" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
        <span>📧 E-posta adresin <strong><?= esc($user['email']) ?></strong> henüz doğrulanmadı.</span>
        <form method="post" action="<?= esc(url('/dogrula/yenile')) ?>" style="margin:0">
            <?= csrf_field() ?>
            <button class="btn" type="submit">Doğrulama e-postasını yeniden gönder</button>
        </form>
    </div>
    <?php endif; ?>
</section>
<section class="grid">
    <article class="card">
        <h2>Profilim</h2>
        <p>Uzmanlık, eğitim ve sosyal bağlantılarını güncelle.</p>
        <p><a class="btn" href="<?= esc(url('/panel/profil')) ?>">Profili düzenle</a></p>
    </article>

    <?php if ($canApplyAuthor && $appStatus === 'none'): ?>
    <article class="card">
        <h2>Yazar Ol</h2>
        <p>Hesabınızı yazar yetkisine yükseltmek için 3-adımlık başvuru formunu doldurun.</p>
        <p><a class="btn btn-primary" href="<?= esc(url('/yazar-ol')) ?>">Başvur</a></p>
    </article>
    <?php elseif ($canApplyAuthor && $appStatus === 'pending'): ?>
    <article class="card">
        <h2>Yazar Başvurum</h2>
        <p>Başvurunuz inceleniyor. Onaylandığında size e-posta gönderilecek.</p>
        <p><span class="badge badge-pending">Bekleniyor</span></p>
    </article>
    <?php elseif ($canApplyAuthor && $appStatus === 'rejected'): ?>
    <article class="card">
        <h2>Yazar Başvurum</h2>
        <p>Başvurunuz bu kez kabul edilmedi. Editör notları size e-posta ile gönderildi.</p>
        <p><span class="badge badge-rejected">Reddedildi</span></p>
    </article>
    <?php endif; ?>

    <article class="card">
        <h2>İçeriklerim</h2>
        <p>Taslakları yaz, onaya gönder, yayında olanları yönet.</p>
        <p><a class="btn" href="<?= esc(url('/panel/yazilar')) ?>">İçerikleri aç</a></p>
    </article>

    <article class="card">
        <h2>Görsel Kütüphanesi</h2>
        <p>Tüm görsellerin merkezi. WebP/AVIF otomatik üretilir.</p>
        <p><a class="btn" href="<?= esc(url('/panel/medya')) ?>">Galeriyi aç</a></p>
    </article>

    <?php if ($scheduledCount > 0): ?>
    <article class="card">
        <h2>Zamanlanmış Yayınlar
            <span class="badge badge-pending"><?= (int) $scheduledCount ?></span>
        </h2>
        <p>Belirli bir tarih ve saatte otomatik yayına girecek içerikler.</p>
        <p><a class="btn" href="<?= esc(url('/panel/yazilar')) ?>">Listeyi aç</a></p>
    </article>
    <?php endif; ?>

    <?php if ($isStaff): ?>
    <article class="card">
        <h2>Editör Paneli
            <?php if ($pendingPosts > 0): ?>
                <span class="badge badge-pending"><?= $pendingPosts ?></span>
            <?php endif; ?>
        </h2>
        <p>Onay bekleyen içerikleri incele.</p>
        <p><a class="btn" href="<?= esc(url('/editor/onay')) ?>">Kuyruğa git</a></p>
    </article>

    <article class="card">
        <h2>Yorum Moderasyonu
            <?php if ($pendingComments > 0): ?>
                <span class="badge badge-pending"><?= $pendingComments ?></span>
            <?php endif; ?>
        </h2>
        <p>Onay bekleyen yorumları yönet.</p>
        <p><a class="btn" href="<?= esc(url('/editor/yorumlar')) ?>">Yorumları aç</a></p>
    </article>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
    <article class="card">
        <h2>Kategoriler</h2>
        <p>Silo URL'lerin temeli.</p>
        <p><a class="btn" href="<?= esc(url('/admin/kategoriler')) ?>">Yönet</a></p>
    </article>

    <article class="card">
        <h2>Kullanıcılar</h2>
        <p>Üyeleri görüntüle, rol değiştir, gerekirse sil.</p>
        <p><a class="btn" href="<?= esc(url('/admin/kullanicilar')) ?>">Yönet</a></p>
    </article>

    <article class="card">
        <h2>Sistem Logları</h2>
        <p>Tarih, kanal ve seviye bazlı log görüntüleyici.</p>
        <p><a class="btn" href="<?= esc(url('/admin/loglar')) ?>">Logları aç</a></p>
    </article>

    <article class="card">
        <h2>Bakım & Tazelik
            <?php if ($staleCount > 0): ?>
                <span class="badge badge-rejected"><?= $staleCount ?></span>
            <?php endif; ?>
        </h2>
        <p>Eski içerikleri tazele, log/cache temizle.</p>
        <p><a class="btn" href="<?= esc(url('/admin/bakim')) ?>">Bakıma git</a></p>
    </article>

    <article class="card">
        <h2>Kırık Link Dedektörü
            <?php if ($brokenLinks > 0): ?>
                <span class="badge badge-rejected"><?= (int) $brokenLinks ?></span>
            <?php endif; ?>
        </h2>
        <p>Yazılarındaki çalışmayan dış bağlantılar.</p>
        <p><a class="btn" href="<?= esc(url('/admin/linkler')) ?>">Linkleri kontrol et</a></p>
    </article>
    <?php endif; ?>

    <article class="card">
        <h2>Çıkış</h2>
        <form method="post" action="<?= esc(url('/cikis')) ?>">
            <?= csrf_field() ?>
            <button class="btn" type="submit">Oturumu Kapat</button>
        </form>
    </article>
</section>
