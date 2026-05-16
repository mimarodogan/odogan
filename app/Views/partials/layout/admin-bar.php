<?php
/**
 * Üst admin bar — marka, kuyruk linki, "+ Yeni Yazı" CTA, kullanıcı kartı, çıkış.
 *
 * @var array|null $_adminUser
 * @var bool       $_isEditor
 * @var string     $_roleLabel
 */
?>
<header class="admin-bar">
    <button type="button" class="ab-burger" aria-label="Menüyü aç/kapat" aria-controls="admin-side" aria-expanded="false">
        <span></span><span></span><span></span>
    </button>
    <div class="ab-brand">
        <a href="<?= esc(url('/')) ?>" target="_blank" rel="noopener" title="Siteyi yeni sekmede aç">
            <span class="ab-mark"></span>
            <strong><?= esc((string) \App\Models\Setting::get('site_name', \App\Core\Config::get('APP_NAME', 'Otorite Yayın'))) ?></strong>
        </a>
        <a class="ab-view" href="<?= esc(url('/')) ?>" target="_blank" rel="noopener">↗ Siteyi görüntüle</a>
    </div>
    <div class="ab-right">
        <?php if ($_isEditor): ?>
        <a href="<?= esc(url('/editor/onay')) ?>" class="ab-link" title="Onay kuyruğu">⌛ Kuyruk</a>
        <?php endif; ?>
        <a href="<?= esc(url('/panel/yazilar/yeni')) ?>" class="ab-link ab-cta">+ Yeni Yazı</a>
        <span class="ab-user">
            <span class="ab-avatar"><?= esc(mb_strtoupper(mb_substr((string) ($_adminUser['name'] ?? '?'), 0, 1))) ?></span>
            <span class="ab-uname"><?= esc((string) ($_adminUser['name'] ?? 'Kullanıcı')) ?></span>
            <span class="ab-role"><?= esc($_roleLabel) ?></span>
        </span>
        <form method="post" action="<?= esc(url('/cikis')) ?>" class="ab-logout">
            <?= csrf_field() ?>
            <button type="submit" class="ab-link" title="Çıkış yap">⤤</button>
        </form>
    </div>
</header>
