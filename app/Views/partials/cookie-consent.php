<?php
/**
 * cookie-consent.php — KVKK / GDPR uyumlu çerez onay banner'ı.
 *
 * • Atelier estetiği — bone bg, cobalt accent, serif başlık, mono buton.
 * • Yalnızca privacy.cookie_banner_enabled = true ise render edilir.
 * • İlk yüklemede gizli (display:none). assets/js/cookie-consent.js
 *   localStorage'da consent yoksa görünür kılar; varsa hiç dokunmaz.
 * • role="dialog" + aria-modal="false" — sayfayı bloklamayan bildirim
 *   diyaloğu (KVKK rehberi: kullanıcı içerikle etkileşebilmeli).
 * • Metin admin panel "Privacy" group → cookie_banner_text ayarından gelir;
 *   sadece <strong> ve <a> tag'leri korunur (kvkk politikası linki için).
 *
 * Beklenen scope: base.php sonunda require edilir; ek değişken yok.
 */

$_ccEnabled = (bool) \App\Models\Setting::get('cookie_banner_enabled', true, 'privacy');
if (!$_ccEnabled) {
    return;
}

$_ccDefault = 'Sitemiz, deneyiminizi geliştirmek ve trafik analizi yapabilmek '
            . 'için çerez kullanır. <strong>Sadece gerekli</strong> seçeneğiyle '
            . 'yalnızca oturum çerezleri çalışır; <strong>kabul</strong> '
            . 'ederseniz analitik çerezler de etkinleşir.';
$_ccText = (string) \App\Models\Setting::get('cookie_banner_text', $_ccDefault, 'privacy');
// Admin tarafından girilen HTML — yalnızca güvenli inline tag'leri bırak.
$_ccText = strip_tags($_ccText, '<strong><em><b><i><a><br>');
?>
<aside
    id="cookie-consent"
    class="cookie-consent"
    role="dialog"
    aria-modal="false"
    aria-label="Çerez tercihi"
    aria-live="polite"
    hidden
>
    <div class="cookie-consent-inner">
        <div class="cookie-consent-body">
            <h2 class="cookie-consent-title">Çerez Tercihi</h2>
            <p class="cookie-consent-text"><?= $_ccText /* sanitized above */ ?></p>
            <p class="cookie-consent-link">
                <a href="<?= esc(url('/sozlesmeler/gizlilik-politikasi')) ?>" rel="nofollow">
                    Gizlilik politikamızı okuyun
                </a>
            </p>
        </div>
        <div class="cookie-consent-buttons">
            <button
                type="button"
                class="btn cookie-btn cookie-btn-essential"
                data-cookie-consent="essential"
            >
                Sadece Gerekli
            </button>
            <button
                type="button"
                class="btn btn-primary cookie-btn cookie-btn-all"
                data-cookie-consent="all"
            >
                Kabul Et
            </button>
        </div>
    </div>
</aside>
