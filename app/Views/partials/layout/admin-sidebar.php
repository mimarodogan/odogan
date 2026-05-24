<?php
/**
 * Sol sidebar — yönetim menüsü (accordion + canlı arama).
 *
 * Her grup <details class="ns-group" data-group="<key>">. Varsayılan-açık
 * gruplar: Konsol / İçerik / Hesap. Aktif sayfayı içeren grup, sonuna
 * gömülü script tarafından zorla açılır; diğer kararlar localStorage'da
 * hatırlanır. "/" tuşu menüde arama input'unu odaklar.
 *
 * @var string   $_curUri
 * @var \Closure $_active     Path prefix → aria-current="page" üreticisi
 * @var bool     $_isEditor
 * @var bool     $_isAdmin
 */

// Varsayılan açık gruplar (küçük + en sık erişilen).
$_defaultOpen = ['konsol', 'icerik', 'hesap'];
$_open = static function (string $key) use ($_defaultOpen): string {
    return in_array($key, $_defaultOpen, true) ? ' open' : '';
};

// Bekleyen sayım yardımcıları
$_apPending = 0;
if ($_isEditor && function_exists('feature') && feature('approval_workflow_enabled')) {
    $_apCounts = \App\Models\PostApproval::pendingCounts();
    $_apPending = (int) ($_apCounts['review'] ?? 0) + (int) ($_apCounts['approved'] ?? 0);
}
$_aaPending = 0;
if ($_isAdmin && function_exists('feature') && feature('author_application')) {
    $_aaCounts = \App\Controllers\Admin\AuthorApplicationController::statusCounts();
    $_aaPending = (int) ($_aaCounts['pending'] ?? 0);
}
$_projPending = 0;
if ($_isAdmin && function_exists('feature') && feature('project_portfolio_enabled')) {
    try { $_projPending = \App\Models\Project::pendingApprovalCount(); }
    catch (\Throwable) { $_projPending = 0; }
}

// "Projeler & Vitrin" grubu en az bir özellik açıksa görünsün
$_hasShowcase = (function_exists('feature') && (feature('project_portfolio_enabled')
    || feature('sponsor_slot_enabled')
    || feature('affiliate_enabled')));
?>
<aside class="admin-side" id="admin-side" aria-label="Yönetim menüsü">
    <nav class="admin-nav">

        <div class="ns-search">
            <input type="search" id="admin-side-search"
                   placeholder="Menüde ara…"
                   aria-label="Menüde ara"
                   autocomplete="off"
                   spellcheck="false">
            <span class="ns-search-hint" aria-hidden="true">"<kbd>/</kbd>" tuşu ile odakla</span>
        </div>

        <!-- 1) Konsol -->
        <details class="ns-group" data-group="konsol"<?= $_open('konsol') ?>>
            <summary class="ns-title">Konsol</summary>
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
        </details>

        <!-- 2) İçerik -->
        <details class="ns-group" data-group="icerik"<?= $_open('icerik') ?>>
            <summary class="ns-title">İçerik</summary>
            <a href="<?= esc(url('/panel/yazilar')) ?>"<?= $_active('/panel/yazilar') ?>>
                <span class="ns-glyph">▤</span> Yazılar
            </a>
            <a href="<?= esc(url('/panel/yazilar/yeni')) ?>" class="ns-sub">
                <span class="ns-glyph">+</span> Yeni Ekle
            </a>
            <a href="<?= esc(url('/panel/medya')) ?>"<?= $_active('/panel/medya') ?>>
                <span class="ns-glyph">▦</span> Medya
            </a>
        </details>

        <?php if ($_isEditor): ?>
        <!-- 3) Editöryal -->
        <details class="ns-group" data-group="editoryal"<?= $_open('editoryal') ?>>
            <summary class="ns-title">Editöryal</summary>
            <a href="<?= esc(url('/editor/onay')) ?>"<?= $_active('/editor/onay') ?>>
                <span class="ns-glyph">⌛</span> Onay Kuyruğu
            </a>
            <a href="<?= esc(url('/editor/yorumlar')) ?>"<?= $_active('/editor/yorumlar') ?>>
                <span class="ns-glyph">❝</span> Yorumlar
            </a>
            <?php if (function_exists('feature') && feature('approval_workflow_enabled')): ?>
            <a href="<?= esc(url('/editor/onaylar')) ?>"<?= $_active('/editor/onaylar') ?>>
                <span class="ns-glyph">◐</span> Onay Süreci
                <?php if ($_apPending > 0): ?><span class="ns-badge"><?= $_apPending ?></span><?php endif; ?>
            </a>
            <?php endif; ?>
        </details>
        <?php endif; ?>

        <?php if ($_isAdmin): ?>
        <!-- 4) Taksonomi -->
        <details class="ns-group" data-group="taksonomi"<?= $_open('taksonomi') ?>>
            <summary class="ns-title">Taksonomi</summary>
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
            <?php if (function_exists('feature') && feature('glossary_enabled')): ?>
            <a href="<?= esc(url('/admin/sozluk')) ?>"<?= $_active('/admin/sozluk') ?>>
                <span class="ns-glyph">§</span> Mimari Sözlük
            </a>
            <?php endif; ?>
        </details>

        <!-- 5) Yazar & Başvuru -->
        <details class="ns-group" data-group="yazar"<?= $_open('yazar') ?>>
            <summary class="ns-title">Yazar &amp; Başvuru</summary>
            <a href="<?= esc(url('/admin/kullanicilar')) ?>"<?= $_active('/admin/kullanicilar') ?>>
                <span class="ns-glyph">◉</span> Yazarlar
            </a>
            <?php if (function_exists('feature') && feature('author_application')): ?>
            <a href="<?= esc(url('/admin/yazar-basvurulari')) ?>"<?= $_active('/admin/yazar-basvurulari') ?>>
                <span class="ns-glyph">✉</span> Yazar Başvuruları
                <?php if ($_aaPending > 0): ?><span class="ns-badge"><?= $_aaPending ?></span><?php endif; ?>
            </a>
            <?php endif; ?>
        </details>

        <!-- 6) SEO & Trafik -->
        <details class="ns-group" data-group="seo"<?= $_open('seo') ?>>
            <summary class="ns-title">SEO &amp; Trafik</summary>
            <a href="<?= esc(url('/admin/linkler')) ?>"<?= $_active('/admin/linkler') ?>>
                <span class="ns-glyph">⇗</span> Bağlantılar
            </a>
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
            <?php if (function_exists('feature') && feature('ab_test_enabled')): ?>
            <a href="<?= esc(url('/admin/ab-test')) ?>"<?= $_active('/admin/ab-test') ?>>
                <span class="ns-glyph">⇆</span> A/B Test
            </a>
            <?php endif; ?>
            <?php if (function_exists('feature') && feature('critical_css_enabled')): ?>
            <a href="<?= esc(url('/admin/critical-css')) ?>"<?= $_active('/admin/critical-css') ?>>
                <span class="ns-glyph">⚡</span> Critical CSS
            </a>
            <?php endif; ?>
        </details>

        <?php if ($_hasShowcase): ?>
        <!-- 7) Projeler & Vitrin -->
        <details class="ns-group" data-group="projeler"<?= $_open('projeler') ?>>
            <summary class="ns-title">Projeler &amp; Vitrin</summary>
            <?php if (function_exists('feature') && feature('project_portfolio_enabled')): ?>
            <a href="<?= esc(url('/panel/projeler')) ?>"<?= $_active('/panel/projeler') ?>>
                <span class="ns-glyph">▣</span> Projeler
                <?php if ($_projPending > 0): ?><span class="ns-badge"><?= $_projPending ?></span><?php endif; ?>
            </a>
            <a href="<?= esc(url('/panel/projeler/yeni')) ?>" class="ns-sub">
                <span class="ns-glyph">+</span> Yeni Ekle
            </a>
            <?php endif; ?>
            <?php if (function_exists('feature') && feature('sponsor_slot_enabled')): ?>
            <a href="<?= esc(url('/admin/sponsor')) ?>"<?= $_active('/admin/sponsor') ?>>
                <span class="ns-glyph">◊</span> Sponsor
            </a>
            <?php endif; ?>
            <?php if (function_exists('feature') && feature('affiliate_enabled')): ?>
            <a href="<?= esc(url('/admin/affiliate')) ?>"<?= $_active('/admin/affiliate') ?>>
                <span class="ns-glyph">⇗</span> Affiliate
            </a>
            <?php endif; ?>
        </details>
        <?php endif; ?>

        <!-- 8) Sistem -->
        <details class="ns-group" data-group="sistem"<?= $_open('sistem') ?>>
            <summary class="ns-title">Sistem</summary>
            <a href="<?= esc(url('/admin/ayarlar')) ?>"<?= $_active('/admin/ayarlar') ?>>
                <span class="ns-glyph">✦</span> Site Ayarları
            </a>
            <a href="<?= esc(url('/admin/ozellikler')) ?>"<?= $_active('/admin/ozellikler') ?>>
                <span class="ns-glyph">⚡</span> Özellikler
            </a>
            <a href="<?= esc(url('/admin/loglar')) ?>"<?= $_active('/admin/loglar') ?>>
                <span class="ns-glyph">≡</span> Loglar
            </a>
            <?php if (function_exists('feature') && feature('audit_log_enabled')): ?>
            <a href="<?= esc(url('/admin/audit')) ?>"<?= $_active('/admin/audit') ?>>
                <span class="ns-glyph">◈</span> Audit Log
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
        </details>

        <!-- 9) İletişim -->
        <details class="ns-group" data-group="iletisim"<?= $_open('iletisim') ?>>
            <summary class="ns-title">İletişim</summary>
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
        </details>
        <?php endif; ?>

        <!-- 10) Hesap (her zaman alt) -->
        <details class="ns-group ns-group-bottom" data-group="hesap"<?= $_open('hesap') ?>>
            <summary class="ns-title">Hesap</summary>
            <a href="<?= esc(url('/panel/profil')) ?>"<?= $_active('/panel/profil') ?>>
                <span class="ns-glyph">⊙</span> Profil
            </a>
            <a href="<?= esc(url('/panel/iki-fa')) ?>"<?= $_active('/panel/iki-fa') ?>>
                <span class="ns-glyph">🔒</span> 2FA Güvenlik
            </a>
        </details>

    </nav>
</aside>

<script>
// Sidebar accordion durumu + canlı arama. Inline — asset cache derdi yok.
(function () {
    'use strict';
    var KEY = 'odogan.adminSidebar.groups';
    var state = {};
    try { state = JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) {}

    var groups = Array.prototype.slice.call(
        document.querySelectorAll('.admin-nav details.ns-group[data-group]')
    );
    groups.forEach(function (d) {
        var key = d.getAttribute('data-group');
        var hasActive = !!d.querySelector('a[aria-current="page"]');
        if (hasActive) {
            d.open = true; // aktif sayfayı içeren grup her zaman açık
        } else if (Object.prototype.hasOwnProperty.call(state, key)) {
            d.open = !!state[key]; // localStorage restore
        }
        d.addEventListener('toggle', function () {
            if (hasActive) return; // aktif grubu hep açık tut, kaydetme
            state[key] = d.open;
            try { localStorage.setItem(KEY, JSON.stringify(state)); } catch (e) {}
        });
    });

    // Canlı arama — öğeleri ve grupları filtrele
    var searchInput = document.getElementById('admin-side-search');
    if (searchInput) {
        var norm = function (s) { return (s || '').toLocaleLowerCase('tr-TR'); };
        var savedOpen = null;
        var apply = function () {
            var q = norm(searchInput.value.trim());
            if (q !== '' && savedOpen === null) {
                savedOpen = groups.map(function (d) { return d.open; });
            }
            if (q === '') {
                groups.forEach(function (d, i) {
                    d.classList.remove('ns-filter-empty');
                    d.querySelectorAll('a').forEach(function (a) {
                        a.classList.remove('ns-filter-hidden');
                    });
                    if (savedOpen) d.open = savedOpen[i];
                });
                savedOpen = null;
                return;
            }
            groups.forEach(function (d) {
                var any = false;
                d.querySelectorAll('a').forEach(function (a) {
                    var match = norm(a.textContent).indexOf(q) !== -1;
                    a.classList.toggle('ns-filter-hidden', !match);
                    if (match) any = true;
                });
                d.classList.toggle('ns-filter-empty', !any);
                if (any) d.open = true;
            });
        };
        searchInput.addEventListener('input', apply);
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                searchInput.value = '';
                apply();
                searchInput.blur();
            }
        });
        // "/" kısayolu — yazma alanı dışında ise input'u odakla
        document.addEventListener('keydown', function (e) {
            if (e.key !== '/') return;
            var t = document.activeElement;
            if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable)) return;
            e.preventDefault();
            searchInput.focus();
            searchInput.select();
        });
    }
})();
</script>
