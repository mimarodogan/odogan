<?php
/**
 * cookie-consent-init.php — GA4 Consent Mode V2 default state.
 *
 * Bu blok GA4 <script> etiketinden HEMEN ÖNCE basılmalıdır (head-meta.php
 * sonunda google_analytics_id script blok'unun üst tarafı). gtag('consent',
 * 'default', …) çağrısının `gtag('config', …)` çağrısından önce çalışması
 * Consent Mode V2 spec'in gereğidir — aksi halde varsayılan 'granted' kabul
 * edilir ve ad/analytics çerezleri kullanıcı onayı alınmadan yazılır.
 *
 * Koşullar:
 *  • cookie_banner_enabled = true  (kullanıcı banner'ı aktive etmiş)
 *  • gdpr_consent_required = true  (AB ziyaretçi default reddedilsin)
 *  • google_analytics_id boş değil (zaten gtag yüklü olacaksa anlamlı)
 *
 * Bu üç koşul sağlanmıyorsa hiçbir çıktı verilmez — eski davranış korunur.
 *
 * Frontend cookie-consent.js, kullanıcı seçimini gtag('consent','update',…)
 * ile yansıtır. Bu partial yalnızca DEFAULT state'i koyar.
 */

$_ccEnabled   = (bool)   \App\Models\Setting::get('cookie_banner_enabled', true, 'privacy');
$_gdprRequired = (bool)  \App\Models\Setting::get('gdpr_consent_required', true, 'privacy');
$_gaIdInit    = (string) \App\Models\Setting::get('google_analytics_id', '', 'analytics');

if ($_ccEnabled && $_gdprRequired && $_gaIdInit !== ''): ?>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent', 'default', {
    'ad_storage':'denied',
    'analytics_storage':'denied',
    'ad_user_data':'denied',
    'ad_personalization':'denied',
    'wait_for_update': 500
});
</script>
<?php endif; ?>
