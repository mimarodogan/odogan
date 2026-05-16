<?php
/**
 * Sol sidebar — yönetim menüsü (Konsol / İçerik / Editöryal / Yönetim / Hesap).
 *
 * @var string   $_curUri
 * @var \Closure $_active     Path prefix → aria-current="page" üreticisi
 * @var bool     $_isEditor
 * @var bool     $_isAdmin
 */
?>
<aside class="admin-side" id="admin-side" aria-label="Yönetim menüsü">
    <nav class="admin-nav">

        <div class="ns-group">
            <span class="ns-title">Konsol</span>
            <?php if ($_isAdmin): ?>
                <a href="<?= esc(url('/admin')) ?>"<?= ($_curUri === '/admin' || $_curUri === '/admin/') ? ' aria-current="page"' : '' ?>>
                    <span class="ns-glyph">◇</span> Yönetim Paneli
                </a>
                <a href="<?= esc(url('/panel')) ?>" class="ns-sub"<?= str_ends_with($_curUri, '/panel') ? ' aria-current="page"' : '' ?>>
                    <span class="ns-glyph">◈</span> Hesap Özeti
                </a>
            <?php else: ?>
                <a href="<?= esc(url('/panel')) ?>"<?= str_ends_with($_curUri, '/panel') ? ' aria-current="page"' : '' ?>>
                    <span class="ns-glyph">◇</span> Genel Bakış
                </a>
            <?php endif; ?>
        </div>

        <div class="ns-group">
            <span class="ns-title">İçerik</span>
            <a href="<?= esc(url('/panel/yazilar')) ?>"<?= $_active('/panel/yazilar') ?>>
                <span class="ns-glyph">▤</span> Yazılar
            </a>
            <a href="<?= esc(url('/panel/yazilar/yeni')) ?>" class="ns-sub">
                <span class="ns-glyph">+</span> Yeni Ekle
            </a>
            <a href="<?= esc(url('/panel/medya')) ?>"<?= $_active('/panel/medya') ?>>
                <span class="ns-glyph">▦</span> Medya
            </a>
        </div>

        <?php if ($_isEditor): ?>
        <div class="ns-group">
            <span class="ns-title">Editöryal</span>
            <a href="<?= esc(url('/editor/onay')) ?>"<?= $_active('/editor/onay') ?>>
                <span class="ns-glyph">⌛</span> Onay Kuyruğu
            </a>
            <a href="<?= esc(url('/editor/yorumlar')) ?>"<?= $_active('/editor/yorumlar') ?>>
                <span class="ns-glyph">❝</span> Yorumlar
            </a>
            <?php if (function_exists('feature') && feature('approval_workflow_enabled')):
                $_apCounts = \App\Models\PostApproval::pendingCounts();
                $_apPending = (int) ($_apCounts['review'] ?? 0) + (int) ($_apCounts['approved'] ?? 0);
            ?>
            <a href="<?= esc(url('/editor/onaylar')) ?>"<?= $_active('/editor/onaylar') ?>>
                <span class="ns-glyph">◐</span> Onay Süreci
                <?php if ($_apPending > 0): ?>
                    <span style="background:var(--cobalt,#1e3a8a);color:#fff;font-size:.7rem;font-weight:700;padding:.05rem .4rem;border-radius:999px;min-width:1.3rem;text-align:center;margin-left:.3rem">
                        <?= $_apPending ?>
                    </span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($_isAdmin): ?>
        <div class="ns-group">
            <span class="ns-title">Yönetim</span>
            <a href="<?= esc(url('/admin/kategoriler')) ?>"<?= $_active('/admin/kategoriler') ?>>
                <span class="ns-glyph">⌑</span> Kategoriler
            </a>
            <a href="<?= esc(url('/admin/kategoriler/yeni')) ?>" class="ns-sub">
                <span class="ns-glyph">+</span> Yeni Ekle
            </a>
            <?php if (function_exists('feature') && feature('series_enabled')): ?>
            <a href="<?= esc(url('/admin/diziler')) ?>"<?= $_active('/admin/diziler') ?>>
                <span class="ns-glyph">📚</span> Diziler
            </a>
            <a href="<?= esc(url('/admin/diziler/yeni')) ?>" class="ns-sub">
                <span class="ns-glyph">+</span> Yeni Ekle
            </a>
            <?php endif; ?>
            <a href="<?= esc(url('/admin/kullanicilar')) ?>"<?= $_active('/admin/kullanicilar') ?>>
                <span class="ns-glyph">◉</span> Yazarlar
            </a>
            <?php if (function_exists('feature') && feature('author_application')):
                $_aaCounts = \App\Controllers\Admin\AuthorApplicationController::statusCounts();
                $_pending = (int) ($_aaCounts['pending'] ?? 0);
            ?>
            <a href="<?= esc(url('/admin/yazar-basvurulari')) ?>"<?= $_active('/admin/yazar-basvurulari') ?>>
                <span class="ns-glyph">✉</span> Yazar Başvuruları
                <?php if ($_pending > 0): ?>
                    <span style="background:var(--cobalt,#1e3a8a);color:#fff;font-size:.7rem;font-weight:700;padding:.05rem .4rem;border-radius:999px;min-width:1.3rem;text-align:center;margin-left:.3rem">
                        <?= $_pending ?>
                    </span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
            <a href="<?= esc(url('/admin/linkler')) ?>"<?= $_active('/admin/linkler') ?>>
                <span class="ns-glyph">⇗</span> Bağlantılar
            </a>
            <a href="<?= esc(url('/admin/loglar')) ?>"<?= $_active('/admin/loglar') ?>>
                <span class="ns-glyph">≡</span> Loglar
            </a>
            <?php if (function_exists('feature') && feature('audit_log_enabled')): ?>
            <a href="<?= esc(url('/admin/audit')) ?>"<?= $_active('/admin/audit') ?>>
                <span class="ns-glyph">◈</span> Audit Log
            </a>
            <?php endif; ?>
            <?php if (function_exists('feature') && feature('redirect_manager_enabled')): ?>
            <a href="<?= esc(url('/admin/yonlendirmeler')) ?>"<?= $_active('/admin/yonlendirmeler') ?>>
                <span class="ns-glyph">↻</span> Yönlendirmeler
            </a>
            <?php endif; ?>
            <?php if (function_exists('feature') && feature('not_found_logger_enabled')): ?>
            <a href="<?= esc(url('/admin/404-loglari')) ?>"<?= $_active('/admin/404-loglari') ?>>
                <span class="ns-glyph">⌀</span> 404 Logları
            </a>
            <?php endif; ?>
            <?php if (function_exists('feature') && feature('glossary_enabled')): ?>
            <a href="<?= esc(url('/admin/sozluk')) ?>"<?= $_active('/admin/sozluk') ?>>
                <span class="ns-glyph">§</span> Mimari Sözlük
            </a>
            <?php endif; ?>
            <?php if (function_exists('feature') && feature('affiliate_enabled')): ?>
            <a href="<?= esc(url('/admin/affiliate')) ?>"<?= $_active('/admin/affiliate') ?>>
                <span class="ns-glyph">⇗</span> Affiliate
            </a>
            <?php endif; ?>
            <?php if (function_exists('feature') && feature('project_portfolio_enabled')):
                $_projPending = 0;
                if ($_isAdmin) {
                    try { $_projPending = \App\Models\Project::pendingApprovalCount(); }
                    catch (\Throwable) { $_projPending = 0; }
                }
            ?>
            <a href="<?= esc(url('/panel/projeler')) ?>"<?= $_active('/panel/projeler') ?>>
                <span class="ns-glyph">▣</span> Projeler
                <?php if ($_projPending > 0): ?>
                    <span style="background:var(--cobalt,#1e3a8a);color:#fff;font-size:.7rem;font-weight:700;padding:.05rem .4rem;border-radius:999px;min-width:1.3rem;text-align:center;margin-left:.3rem">
                        <?= $_projPending ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="<?= esc(url('/panel/projeler/yeni')) ?>" class="ns-sub">
                <span class="ns-glyph">+</span> Yeni Ekle
            </a>
            <?php endif; ?>
            <?php if (function_exists('feature') && feature('ab_test_enabled')): ?>
            <a href="<?= esc(url('/admin/ab-test')) ?>"<?= $_active('/admin/ab-test') ?>>
                <span class="ns-glyph">⇆</span> A/B Test
            </a>
            <?php endif; ?>
            <?php if (function_exists('feature') && feature('sponsor_slot_enabled')): ?>
            <a href="<?= esc(url('/admin/sponsor')) ?>"<?= $_active('/admin/sponsor') ?>>
                <span class="ns-glyph">◊</span> Sponsor
            </a>
            <?php endif; ?>
            <?php if (function_exists('feature') && feature('critical_css_enabled')): ?>
            <a href="<?= esc(url('/admin/critical-css')) ?>"<?= $_active('/admin/critical-css') ?>>
                <span class="ns-glyph">⚡</span> Critical CSS
            </a>
            <?php endif; ?>
            <a href="<?= esc(url('/admin/bakim')) ?>"<?= str_ends_with($_curUri, '/admin/bakim') ? ' aria-current="page"' : '' ?>>
                <span class="ns-glyph">⚙</span> Bakım
            </a>
            <a href="<?= esc(url('/admin/bakim/yedekler')) ?>" class="ns-sub"<?= str_starts_with($_curUri, '/admin/bakim/yedekler') ? ' aria-current="page"' : '' ?>>
                <span class="ns-glyph">⤓</span> Yedekler
            </a>
            <a href="<?= esc(url('/admin/bakim/migrasyonlar')) ?>" class="ns-sub"<?= str_starts_with($_curUri, '/admin/bakim/migrasyonlar') ? ' aria-current="page"' : '' ?>>
                <span class="ns-glyph">⇉</span> Migrasyonlar
            </a>
            <a href="<?= esc(url('/admin/ayarlar')) ?>"<?= $_active('/admin/ayarlar') ?>>
                <span class="ns-glyph">✦</span> Site Ayarları
            </a>
            <a href="<?= esc(url('/admin/ozellikler')) ?>"<?= $_active('/admin/ozellikler') ?>>
                <span class="ns-glyph">⚡</span> Özellikler
            </a>
            <a href="<?= esc(url('/admin/mail')) ?>"<?= $_active('/admin/mail') ?>>
                <span class="ns-glyph">✉</span> E-posta (SMTP)
            </a>
            <a href="<?= esc(url('/admin/mail-sablonlari')) ?>"<?= $_active('/admin/mail-sablonlari') ?>>
                <span class="ns-glyph">✉</span> Mail Şablonları
            </a>
            <a href="<?= esc(url('/admin/sozlesmeler')) ?>"<?= $_active('/admin/sozlesmeler') ?>>
                <span class="ns-glyph">§</span> Sözleşmeler
            </a>
            <a href="<?= esc(url('/admin/newsletter')) ?>"<?= $_active('/admin/newsletter') ?>>
                <span class="ns-glyph">◈</span> Newsletter
            </a>
        </div>
        <?php endif; ?>

        <div class="ns-group ns-group-bottom">
            <span class="ns-title">Hesap</span>
            <a href="<?= esc(url('/panel/profil')) ?>"<?= $_active('/panel/profil') ?>>
                <span class="ns-glyph">⊙</span> Profil
            </a>
            <a href="<?= esc(url('/panel/iki-fa')) ?>"<?= $_active('/panel/iki-fa') ?>>
                <span class="ns-glyph">🔒</span> 2FA Güvenlik
            </a>
        </div>

    </nav>
</aside>
