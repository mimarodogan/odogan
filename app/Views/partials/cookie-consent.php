<?php
/**
 * cookie-consent.php — F2.4 (KVKK): Granular çerez onay banner'ı + tercih modalı.
 *
 * Yapı:
 *   • Banner: 3 buton (Sadece Gerekli / Tercihler / Hepsini Kabul Et)
 *   • Modal:  Tercihler — analytics + marketing per-category toggle
 *   • Her seçim sunucuya da `POST /api/consent` ile bildirilir (consent_logs)
 *
 * KVKK + GDPR Art. 7 gereği:
 *   - "Hepsini reddet" en az "Hepsini kabul" kadar prominent (eşit boy/renk)
 *   - Kategori bazlı opt-in (analytics ile marketing'i ayrı toggle)
 *   - Politika versiyonu değişince yeniden onay (data-policy-version)
 */

$_ccEnabled = (bool) \App\Models\Setting::get('cookie_banner_enabled', true, 'privacy');
if (!$_ccEnabled) {
    return;
}

$_ccDefault = 'Site deneyiminizi geliştirmek için zorunlu çerezler kullanılır. '
            . 'İsteğe bağlı olarak <strong>analitik</strong> (Google Analytics) ve '
            . '<strong>pazarlama</strong> çerezlerini ayrı ayrı seçebilirsiniz.';
$_ccText = (string) \App\Models\Setting::get('cookie_banner_text', $_ccDefault, 'privacy');
$_ccText = strip_tags($_ccText, '<strong><em><b><i><a><br>');

// Politika sürümü — Çerez Politikası ya da Aydınlatma Metni değiştiğinde
// admin Settings'ten bu değeri yükseltirsen tüm kullanıcılardan yeniden onay alınır.
$_ccVersion = (string) \App\Models\Setting::get('cookie_policy_version', '1.0', 'privacy');
?>
<aside
    id="cookie-consent"
    class="cookie-consent"
    role="dialog"
    aria-modal="false"
    aria-label="Çerez tercihi"
    aria-live="polite"
    data-policy-version="<?= esc($_ccVersion) ?>"
    hidden
>
    <div class="cookie-consent-inner">
        <div class="cookie-consent-body">
            <h2 class="cookie-consent-title">Çerez Tercihi</h2>
            <p class="cookie-consent-text"><?= $_ccText /* sanitized above */ ?></p>
            <p class="cookie-consent-link">
                <a href="<?= esc(url('/sozlesmeler/cerez-politikasi')) ?>" rel="nofollow">Çerez Politikası</a>
                <span aria-hidden="true">·</span>
                <a href="<?= esc(url('/sozlesmeler/aydinlatma-metni')) ?>" rel="nofollow">KVKK Aydınlatma Metni</a>
            </p>
        </div>
        <div class="cookie-consent-buttons">
            <button type="button" class="btn cookie-btn cookie-btn-essential"
                    data-cookie-consent="reject_optional">
                Sadece Gerekli
            </button>
            <button type="button" class="btn cookie-btn cookie-btn-prefs"
                    data-cookie-consent="open_prefs"
                    aria-haspopup="dialog" aria-controls="cookie-prefs-modal">
                Tercihler
            </button>
            <button type="button" class="btn btn-primary cookie-btn cookie-btn-all"
                    data-cookie-consent="accept_all">
                Hepsini Kabul Et
            </button>
        </div>
    </div>
</aside>

<!-- F2.4: Tercih modalı — per-category toggle. KVKK granular onay zorunlu. -->
<div id="cookie-prefs-modal"
     class="cookie-prefs-modal"
     role="dialog"
     aria-modal="true"
     aria-labelledby="cookie-prefs-title"
     hidden>
    <div class="cookie-prefs-backdrop" data-cookie-prefs-close></div>
    <div class="cookie-prefs-panel">
        <header class="cookie-prefs-head">
            <h2 id="cookie-prefs-title" class="cookie-prefs-title">Çerez Tercihleri</h2>
            <button type="button" class="cookie-prefs-close"
                    aria-label="Tercih penceresini kapat"
                    data-cookie-prefs-close>×</button>
        </header>

        <p class="cookie-prefs-intro">
            Hangi tür çerezleri kullanmamıza izin verdiğinizi seçebilirsiniz.
            Tercihlerinizi istediğiniz zaman değiştirebilirsiniz.
        </p>

        <ul class="cookie-prefs-list">
            <li class="cookie-prefs-item is-required">
                <div class="cookie-prefs-row">
                    <div>
                        <h3 class="cookie-prefs-cat">Zorunlu Çerezler</h3>
                        <p class="cookie-prefs-desc">
                            Site oturum yönetimi, güvenlik (CSRF), tema tercihi gibi
                            temel işlevler için gerekli. Kapatılamaz.
                        </p>
                    </div>
                    <div class="cookie-prefs-toggle">
                        <span class="cookie-prefs-badge" aria-label="Her zaman aktif">Zorunlu</span>
                    </div>
                </div>
            </li>

            <li class="cookie-prefs-item">
                <div class="cookie-prefs-row">
                    <div>
                        <h3 class="cookie-prefs-cat">Analitik Çerezler</h3>
                        <p class="cookie-prefs-desc">
                            Google Analytics 4 — anonim ziyaret istatistikleri,
                            sayfa görüntüleme, yönlendirme kaynağı.
                        </p>
                    </div>
                    <label class="cookie-prefs-toggle" for="cc-analytics">
                        <input type="checkbox" id="cc-analytics"
                               data-cookie-category="analytics" value="1">
                        <span class="cookie-prefs-switch" aria-hidden="true"></span>
                        <span class="visually-hidden">Analitik çerezleri etkinleştir</span>
                    </label>
                </div>
            </li>

            <li class="cookie-prefs-item">
                <div class="cookie-prefs-row">
                    <div>
                        <h3 class="cookie-prefs-cat">Pazarlama Çerezleri</h3>
                        <p class="cookie-prefs-desc">
                            Reklam görüntülemesi, dönüşüm takibi, davranışsal
                            hedefleme. Şu an aktif kullanılmıyor — gelecekte
                            sponsor içerikte devreye girebilir.
                        </p>
                    </div>
                    <label class="cookie-prefs-toggle" for="cc-marketing">
                        <input type="checkbox" id="cc-marketing"
                               data-cookie-category="marketing" value="1">
                        <span class="cookie-prefs-switch" aria-hidden="true"></span>
                        <span class="visually-hidden">Pazarlama çerezleri etkinleştir</span>
                    </label>
                </div>
            </li>
        </ul>

        <footer class="cookie-prefs-foot">
            <button type="button" class="btn cookie-btn-reject"
                    data-cookie-consent="reject_optional">
                Sadece Gerekli
            </button>
            <button type="button" class="btn btn-primary"
                    data-cookie-consent="prefs_save">
                Tercihlerimi Kaydet
            </button>
        </footer>
    </div>
</div>
