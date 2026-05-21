// cookie-consent.js — KVKK / GDPR çerez onay yönetimi.
//
// Davranış:
//   1) Sayfa yüklendiğinde localStorage['odogan:cookie-consent'] kontrol edilir.
//      • Yoksa banner görünür hale getirilir.
//      • 'all' veya 'essential' varsa banner gizli kalır, mevcut karar
//        gtag('consent', 'update', …) ile yeniden uygulanır.
//   2) Banner içindeki [data-cookie-consent="all"] butonu basıldığında:
//      → localStorage'a 'all' yazılır
//      → gtag analytics_storage='granted'
//      → window 'cookie-consent' CustomEvent (detail.granted = true)
//   3) "essential" butonunda aynı akış 'essential' ve granted=false ile.
//
// GA4 Consent Mode V2 — partials/layout/cookie-consent-init.php tarafından
// gtag('consent','default',{…'denied'}) zaten head'de set edilmiş olmalı;
// bu dosya yalnızca update yapar. denied bir GA4 sayfa görüntülemeyi
// engellemez (cookieless ping) — sadece çerez bazlı tracking'i durdurur.

(function () {
    'use strict';

    const STORAGE_KEY = 'odogan:cookie-consent';
    const VALID = ['all', 'essential'];

    /** localStorage'dan kararı oku. Bozuk değer → null. */
    const read = () => {
        try {
            const v = localStorage.getItem(STORAGE_KEY);
            return VALID.indexOf(v) >= 0 ? v : null;
        } catch (e) {
            return null;
        }
    };

    /** Kararı kalıcı yaz. Storage erişimi yoksa sessizce geç. */
    const write = value => {
        try {
            localStorage.setItem(STORAGE_KEY, value);
        } catch (e) {}
    };

    /** gtag çağrısı varsa Consent Mode V2 update. Yoksa no-op. */
    const updateGtagConsent = granted => {
        if (typeof window.gtag !== 'function') return;
        const state = granted ? 'granted' : 'denied';
        try {
            window.gtag('consent', 'update', {
                ad_storage: state,
                analytics_storage: state,
                ad_user_data: state,
                ad_personalization: state,
            });
        } catch (e) {}
    };

    /**
     * GA gtag.js scriptini dinamik yükle — consent-gated (tek sefer).
     * head-meta.php artık gtag.js'i basmaz; yalnızca onay verilince burada
     * window.__gaId üzerinden eklenir → ilk yüklemede GA yükü olmaz (perf + KVKK).
     */
    let gaLoaded = false;
    const loadGA = () => {
        if (gaLoaded) return;
        const id = window.__gaId;
        if (!id) return;
        gaLoaded = true;
        const s = document.createElement('script');
        s.async = true;
        s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
        document.head.appendChild(s);
        if (typeof window.gtag === 'function') {
            window.gtag('config', id);
        }
    };

    /** CustomEvent fırlat — uygulamadaki diğer modüller dinleyebilsin. */
    const dispatchConsent = (value, granted) => {
        try {
            window.dispatchEvent(
                new CustomEvent('cookie-consent', {
                    detail: { value: value, granted: granted },
                })
            );
        } catch (e) {}
    };

    /** Banner'ı animasyonla göster. */
    const showBanner = el => {
        el.hidden = false;
        // Bir frame bekleyip is-visible ekle → CSS transition tetiklensin.
        window.requestAnimationFrame(() => {
            el.classList.add('is-visible');
        });
    };

    /** Banner'ı kapat — önce sınıfı çıkar, sonra hidden=true. */
    const hideBanner = el => {
        el.classList.remove('is-visible');
        window.setTimeout(() => {
            el.hidden = true;
        }, 250);
    };

    /** Kullanıcı seçimini uygula — kalıcı, gtag, event. */
    const apply = (value, options) => {
        const granted = value === 'all';
        if (!options || options.persist !== false) {
            write(value);
        }
        updateGtagConsent(granted);
        if (granted) loadGA();   // onay 'all' → GA scriptini şimdi yükle
        dispatchConsent(value, granted);
    };

    const init = () => {
        const banner = document.getElementById('cookie-consent');

        // Daha önce verilmiş bir karar varsa: banner'a dokunma; sadece
        // gtag default 'denied' state'ini saved consent'e göre update et.
        const saved = read();
        if (saved) {
            apply(saved, { persist: false });
            return;
        }

        // Banner DOM'da yoksa (çerez onayı gerekmiyor — banner kapalı) → GA'yı
        // doğrudan yükle (consent zorunluluğu yoksa engelleme de yok).
        if (!banner) {
            loadGA();
            return;
        }

        // Banner var ama karar yok → kullanıcıya sor.
        const buttons = banner.querySelectorAll('[data-cookie-consent]');
        buttons.forEach(btn => {
            btn.addEventListener(
                'click',
                () => {
                    const choice = btn.getAttribute('data-cookie-consent');
                    if (VALID.indexOf(choice) < 0) return;
                    apply(choice);
                    hideBanner(banner);
                },
                { once: true }
            );
        });

        showBanner(banner);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
