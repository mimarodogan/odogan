<?php
/**
 * Public footer — Atelier paterni, 4 kolon:
 *   1) Brand (ad + telif + kısa tagline)
 *   2) Keşfet (Yazarlar, Ara, RSS, Diziler)
 *   3) Portfolyo (Projeler, Harita, Sözlük)
 *   4) Yasal (sözleşme linkleri)
 *
 * Sponsor slotu (varsa) footer üstünde tam genişlikte render edilir.
 */
$_brandName = (string) \App\Models\Setting::get('site_name', \App\Core\Config::get('APP_NAME', 'Otorite Yayın'));
$_tagline   = (string) \App\Models\Setting::get('site_tagline', '', 'general');
$_year      = (int) date('Y');

$_legalDocs = [];
try {
    $_legalDocs = \App\Models\LegalDocument::all();
} catch (\Throwable) {}

// Hangi keşfet/portfolyo linkleri görünür?
$_hasSeries    = function_exists('feature') && feature('series_enabled');
$_hasProjects  = function_exists('feature') && feature('project_portfolio_enabled');
$_hasMap       = function_exists('feature') && feature('building_map_enabled');
$_hasGlossary  = function_exists('feature') && feature('glossary_enabled');
$_hasSponsor   = function_exists('feature') && feature('sponsor_slot_enabled');

// Portfolyo kolonu sadece ilgili özellikler aktifse görünür
$_showPortfolio = $_hasProjects || $_hasMap || $_hasGlossary;
?>

<?php if ($_hasSponsor):
    $placement = 'newsletter';
    require __DIR__ . '/../sponsor-slot.php';
endif; ?>

<footer class="site-footer">
    <div class="container">
        <div class="sf-grid<?= $_showPortfolio ? ' sf-grid-4' : '' ?>">

            <div class="sf-brand">
                <p class="sf-brand-name">◆ <?= esc($_brandName) ?></p>
                <?php if ($_tagline !== ''): ?>
                    <p class="sf-tagline"><?= esc($_tagline) ?></p>
                <?php endif; ?>
                <?php
                // F1.10 (KVKK): Kuruluş iletişim bloku — Settings → organization
                // grubundan okunur. Her sayfada görünmesi KVKK Aydınlatma Metni
                // şartına dolaylı uyum sağlar (m.10 kimlik + iletişim).
                $_orgStreet = trim((string) \App\Models\Setting::get('org_street_address', '', 'organization'));
                $_orgCity   = trim((string) \App\Models\Setting::get('org_city', '', 'organization'));
                $_orgEmail  = trim((string) \App\Models\Setting::get('org_email', '', 'organization'));
                $_orgPhone  = trim((string) \App\Models\Setting::get('org_phone', '', 'organization'));
                $_hasContactInfo = ($_orgStreet !== '' || $_orgCity !== '' || $_orgEmail !== '' || $_orgPhone !== '');
                ?>
                <?php if ($_hasContactInfo): ?>
                <address class="sf-address">
                    <?php if ($_orgStreet !== '' || $_orgCity !== ''): ?>
                        <span class="sf-address-line">
                            <?= esc(trim($_orgStreet . ($_orgStreet && $_orgCity ? ', ' : '') . $_orgCity)) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($_orgEmail !== ''): ?>
                        <a class="sf-address-link" href="mailto:<?= esc($_orgEmail) ?>"><?= esc($_orgEmail) ?></a>
                    <?php endif; ?>
                    <?php if ($_orgPhone !== ''): ?>
                        <a class="sf-address-link" href="tel:<?= esc(preg_replace('/[^0-9+]/', '', $_orgPhone)) ?>"><?= esc($_orgPhone) ?></a>
                    <?php endif; ?>
                </address>
                <?php endif; ?>
                <?php
                // G7: Sosyal medya ikonları — Settings → social grubundan okunur.
                $_socialKeys = [
                    'twitter'   => 'social_twitter',
                    'linkedin'  => 'social_linkedin',
                    'instagram' => 'social_instagram',
                    'facebook'  => 'social_facebook',
                    'youtube'   => 'social_youtube',
                ];
                $_socialLinks = [];
                foreach ($_socialKeys as $platform => $key) {
                    $url = trim((string) \App\Models\Setting::get($key, '', 'social'));
                    if ($url !== '') {
                        $_socialLinks[$platform] = $url;
                    }
                }
                ?>
                <?php if (!empty($_socialLinks)): ?>
                <ul class="sf-social" aria-label="Sosyal medya">
                    <?php foreach ($_socialLinks as $platform => $url): ?>
                        <li>
                            <a href="<?= esc($url) ?>" rel="noopener me" target="_blank"
                               aria-label="<?= esc(social_icon_label($platform)) ?>"
                               title="<?= esc(social_icon_label($platform)) ?>">
                                <?= social_icon_svg($platform) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <p class="sf-copyright">© <?= $_year ?> · Tüm haklar saklıdır.</p>
            </div>

            <nav class="sf-nav" aria-label="Site">
                <details class="sf-acc"><summary class="sf-nav-title">Site</summary>
                <ul>
                    <li><a href="<?= esc(url('/hakkimda')) ?>" title="Hakkımda — yazar manifestosu">Hakkımda</a></li>
                    <li><a href="<?= esc(url('/iletisim')) ?>" title="İletişim formu">İletişim</a></li>
                    <li><a href="<?= esc(url('/yazarlar')) ?>" title="Tüm yazarlar">Yazarlar</a></li>
                    <?php if ($_hasGlossary): ?>
                        <li><a href="<?= esc(url('/sozluk')) ?>" title="Mimari terimler sözlüğü">Sözlük</a></li>
                    <?php endif; ?>
                    <li><a href="<?= esc(url('/ara')) ?>" title="Site içinde arama">Ara</a></li>
                    <?php if ($_hasSeries): ?>
                        <li><a href="<?= esc(url('/diziler')) ?>" title="Yazı dizileri / seriler">Diziler</a></li>
                    <?php endif; ?>
                    <li><a href="<?= esc(url('/rss')) ?>" title="RSS beslemesi (feed reader için)">RSS</a></li>
                </ul>
                </details>
            </nav>

            <?php if ($_showPortfolio): ?>
            <nav class="sf-nav" aria-label="Portfolyo">
                <details class="sf-acc"><summary class="sf-nav-title">Portfolyo</summary>
                <ul>
                    <?php if ($_hasProjects): ?>
                        <li><a href="<?= esc(url('/projeler')) ?>" title="Mimari proje portfolyosu">Projeler</a></li>
                    <?php endif; ?>
                    <?php if ($_hasMap): ?>
                        <li><a href="<?= esc(url('/harita')) ?>" title="Projelerin harita üzerinde dağılımı">Yapı Haritası</a></li>
                    <?php endif; ?>
                    <?php if ($_hasGlossary): ?>
                        <li><a href="<?= esc(url('/sozluk')) ?>" title="Mimari terimler sözlüğü">Mimari Sözlük</a></li>
                    <?php endif; ?>
                </ul>
                </details>
            </nav>
            <?php endif; ?>

            <?php if (!empty($_legalDocs)): ?>
            <nav class="sf-nav" aria-label="Sözleşmeler">
                <details class="sf-acc"><summary class="sf-nav-title">Yasal</summary>
                <ul>
                    <?php foreach ($_legalDocs as $d): if (!$d['is_active']) continue; ?>
                        <li>
                            <a href="<?= esc(url('/sozlesmeler/' . $d['slug'])) ?>"
                               title="<?= esc($d['title']) ?> — yasal belge">
                                <?= esc($d['title']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                </details>
            </nav>
            <?php endif; ?>

        </div>
    </div>
</footer>
