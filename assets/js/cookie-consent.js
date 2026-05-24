// cookie-consent.js — F2.4 (KVKK): Granular consent + sunucu tarafı denetim kaydı.
//
// Davranış:
//   1) localStorage['odogan:cookie-consent'] → JSON { version, analytics, marketing, at }
//      ◦ Yoksa banner görünür hale gelir
//      ◦ Varsa apply() — gtag consent + GA conditional load
//   2) Banner butonları:
//      ◦ Sadece Gerekli → reject_optional (analytics=false, marketing=false)
//      ◦ Tercihler      → modal açar (kullanıcı per-kategori toggle)
//      ◦ Hepsini Kabul  → accept_all (analytics=true, marketing=true)
//   3) Her kararda POST /api/consent → consent_logs tablosuna kayıt
//   4) Politika sürümü değişirse (data-policy-version) saved versiyon eski → yeniden onay
//
// KVKK m.5 ve GDPR Art. 7(1) — rızanın denetlenebilir saklanması zorunlu.

(function () {
    'use strict';

    const STORAGE_KEY = 'odogan:cookie-consent';
    const CATEGORIES = ['analytics', 'marketing'];

    /** Storage'dan kararı oku. JSON parse fail → null. */
    const readStored = () => {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return null;
            // Legacy değer ('all' / 'essential') varsa migrate et
            if (raw === 'all') {
                return { version: '1.0', analytics: true, marketing: true, at: 0 };
            }
            if (raw === 'essential') {
                return { version: '1.0', analytics: false, marketing: false, at: 0 };
            }
            const obj = JSON.parse(raw);
            return obj && typeof obj === 'object' ? obj : null;
        } catch (e) {
            return null;
        }
    };

    /** Karar JSON'ını yaz. */
    const writeStored = (state) => {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch (e) {}
    };

    /** CSRF token'ı meta tag'inden veya cookie'den al. */
    const csrfToken = () => {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) return meta.getAttribute('content') || '';
        const m = document.cookie.match(/(?:^|;\s*)odogan_csrf=([^;]+)/);
        return m ? decodeURIComponent(m[1]) : '';
    };

    /** Sunucu tarafı denetim kaydı — fire-and-forget. */
    const recordToServer = (action, categories, version) => {
        try {
            const body = new FormData();
            body.append('_csrf', csrfToken());
            body.append('action', action);
            body.append('version', version);
            CATEGORIES.forEach((c) => body.append('categories[' + c + ']', categories[c] ? '1' : '0'));
            // navigator.sendBeacon iyi olur ama CSRF için fetch keepalive yeterli
            fetch('/api/consent', {
                method: 'POST',
                credentials: 'same-origin',
                body: body,
                keepalive: true,
            }).catch(() => {});
        } catch (e) {}
    };

    /** gtag Consent Mode V2 update. */
    const updateGtagConsent = (categories) => {
        if (typeof window.gtag !== 'function') return;
        try {
            window.gtag('consent', 'update', {
                ad_storage:          categories.marketing ? 'granted' : 'denied',
                analytics_storage:   categories.analytics ? 'granted' : 'denied',
                ad_user_data:        categories.marketing ? 'granted' : 'denied',
                ad_personalization:  categories.marketing ? 'granted' : 'denied',
            });
        } catch (e) {}
    };

    let gaLoaded = false;
    /** GA gtag.js scriptini dinamik yükle — sadece analytics consent verilmişse. */
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

    const dispatchConsent = (state) => {
        try {
            window.dispatchEvent(
                new CustomEvent('cookie-consent', {
                    detail: state,
                })
            );
        } catch (e) {}
    };

    /** Kullanıcı seçimi uygulanır + opsiyonel olarak persist + server kayıt. */
    const apply = (action, categories, version, options) => {
        const opts = options || {};
        const state = {
            version: version,
            analytics: !!categories.analytics,
            marketing: !!categories.marketing,
            at: Date.now(),
        };
        if (opts.persist !== false) {
            writeStored(state);
        }
        updateGtagConsent(state);
        if (state.analytics) loadGA();
        dispatchConsent(state);
        if (opts.persist !== false && opts.skipServer !== true) {
            recordToServer(action, state, version);
        }
    };

    const showBanner = (el) => {
        el.hidden = false;
        window.requestAnimationFrame(() => {
            el.classList.add('is-visible');
        });
    };

    const hideBanner = (el) => {
        el.classList.remove('is-visible');
        window.setTimeout(() => {
            el.hidden = true;
        }, 250);
    };

    /** Tercih modalını aç. */
    const openPrefsModal = (modal, currentState) => {
        if (!modal) return;
        // Mevcut state'i toggle'lara yansıt
        CATEGORIES.forEach((cat) => {
            const input = modal.querySelector('[data-cookie-category="' + cat + '"]');
            if (input) input.checked = !!(currentState && currentState[cat]);
        });
        modal.hidden = false;
        window.requestAnimationFrame(() => {
            modal.classList.add('is-visible');
        });
        // Body scroll lock
        document.documentElement.style.overflow = 'hidden';
        // Focus trap — basit, ilk butona odaklan
        const firstFocus = modal.querySelector('input[type="checkbox"], button');
        if (firstFocus) firstFocus.focus();
    };

    const closePrefsModal = (modal) => {
        if (!modal) return;
        modal.classList.remove('is-visible');
        window.setTimeout(() => {
            modal.hidden = true;
            document.documentElement.style.overflow = '';
        }, 200);
    };

    /** Modal toggle'larından çerez kategorilerini oku. */
    const readPrefsModal = (modal) => {
        const out = { analytics: false, marketing: false };
        CATEGORIES.forEach((cat) => {
            const input = modal.querySelector('[data-cookie-category="' + cat + '"]');
            if (input) out[cat] = !!input.checked;
        });
        return out;
    };

    const init = () => {
        const banner = document.getElementById('cookie-consent');
        const modal  = document.getElementById('cookie-prefs-modal');
        const version = banner ? (banner.getAttribute('data-policy-version') || '1.0') : '1.0';

        const saved = readStored();

        // Politika sürümü değiştiyse saved'ı yok say (yeniden onay alacağız).
        const validSaved = saved && saved.version === version ? saved : null;

        if (validSaved) {
            // Daha önce verilmiş karar var — sessizce uygula (server kayıt yok).
            apply('apply_saved', validSaved, version, { persist: false, skipServer: true });
            return;
        }

        // Banner DOM'da yoksa (admin banner'ı kapatmış) → GA'yı doğrudan yükle
        if (!banner) {
            window.gtag && window.gtag('consent', 'update', {
                ad_storage: 'granted', analytics_storage: 'granted',
                ad_user_data: 'granted', ad_personalization: 'granted',
            });
            loadGA();
            return;
        }

        // Banner butonları
        banner.querySelectorAll('[data-cookie-consent]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const action = btn.getAttribute('data-cookie-consent');
                if (action === 'accept_all') {
                    apply(action, { analytics: true, marketing: true }, version);
                    hideBanner(banner);
                } else if (action === 'reject_optional') {
                    apply(action, { analytics: false, marketing: false }, version);
                    hideBanner(banner);
                } else if (action === 'open_prefs') {
                    openPrefsModal(modal, validSaved || { analytics: false, marketing: false });
                }
            });
        });

        // Modal butonları (Sadece Gerekli + Tercihlerimi Kaydet)
        if (modal) {
            modal.querySelectorAll('[data-cookie-consent]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const action = btn.getAttribute('data-cookie-consent');
                    if (action === 'reject_optional') {
                        apply(action, { analytics: false, marketing: false }, version);
                    } else if (action === 'prefs_save') {
                        apply(action, readPrefsModal(modal), version);
                    }
                    closePrefsModal(modal);
                    hideBanner(banner);
                });
            });
            // Close buttons (×, backdrop)
            modal.querySelectorAll('[data-cookie-prefs-close]').forEach((btn) => {
                btn.addEventListener('click', () => closePrefsModal(modal));
            });
            // ESC kapatma
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && !modal.hidden) closePrefsModal(modal);
            });
        }

        showBanner(banner);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
