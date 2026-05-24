// Admin sidebar mobile drawer — hamburger toggle + backdrop + ESC + link-close.
// Class-based state (body.admin-drawer-open). Defer ile yüklenir, DOM hazır.
//
// A11y: mobil drawer modal davranışı (role=dialog + aria-modal=true) — yalnızca
// dar viewport'ta (< 900px). Desktop'ta sidebar her zaman görünür.
// Focus trap mobile-only; açılışta ilk link odaklanır, kapanışta hamburger'a döner.

(function () {
    'use strict';

    const burger = document.querySelector('.ab-burger');
    const aside = document.getElementById('admin-side');
    const bd = document.querySelector('.admin-backdrop');
    if (!burger || !aside) return;

    let releaseTrap = null;
    let lastFocused = null;

    const isMobile = () => window.innerWidth < 900;
    const isOpen = () => document.body.classList.contains('admin-drawer-open');

    // ─── Focus trap helper ──────────────────────────────────────────────
    const createFocusTrap = modalEl => {
        const selector =
            'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"]), input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])';
        const handler = e => {
            if (e.key !== 'Tab') return;
            const focusable = Array.from(modalEl.querySelectorAll(selector)).filter(
                el => !el.hasAttribute('hidden') && el.offsetParent !== null
            );
            if (!focusable.length) {
                e.preventDefault();
                return;
            }
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        };
        modalEl.addEventListener('keydown', handler);
        return () => modalEl.removeEventListener('keydown', handler);
    };

    const applyMobileDialogAttrs = () => {
        if (isMobile()) {
            aside.setAttribute('role', 'dialog');
            aside.setAttribute('aria-modal', 'true');
        } else {
            aside.removeAttribute('role');
            aside.removeAttribute('aria-modal');
        }
    };

    const open = () => {
        lastFocused = document.activeElement;
        document.body.classList.add('admin-drawer-open');
        burger.setAttribute('aria-expanded', 'true');
        aside.setAttribute('aria-hidden', 'false');
        applyMobileDialogAttrs();
        if (isMobile()) {
            releaseTrap = createFocusTrap(aside);
            // Açılışta ilk link/buton odaklanır
            setTimeout(() => {
                const first = aside.querySelector(
                    'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'
                );
                if (first) first.focus();
            }, 30);
        }
    };

    const close = () => {
        const wasOpen = isOpen();
        document.body.classList.remove('admin-drawer-open');
        burger.setAttribute('aria-expanded', 'false');
        aside.setAttribute('aria-hidden', 'true');
        if (releaseTrap) {
            releaseTrap();
            releaseTrap = null;
        }
        if (wasOpen && lastFocused && typeof lastFocused.focus === 'function') {
            try {
                lastFocused.focus();
            } catch (_e) {
                /* ignore */
            }
        } else if (wasOpen) {
            // Fallback — kapanışta hamburger'a focus dön
            try {
                burger.focus();
            } catch (_e) {
                /* ignore */
            }
        }
        lastFocused = null;
    };

    // Init kapalı state
    close();

    // Hamburger toggle
    burger.addEventListener('click', ev => {
        ev.preventDefault();
        ev.stopPropagation();
        if (isOpen()) {
            close();
        } else {
            open();
        }
    });

    // Backdrop click → close
    if (bd) {
        bd.addEventListener('click', ev => {
            ev.preventDefault();
            close();
        });
    }

    // ESC → close
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && isOpen()) close();
    });

    // Mobil: sidebar link tıklayınca drawer kapanır (sayfa zaten yönleniyor)
    aside.addEventListener('click', e => {
        if (e.target.closest('a') && window.innerWidth < 900) {
            close();
        }
    });

    // Window resize: desktop'a geçince drawer state'i sıfırla
    let lastWidth = window.innerWidth;
    window.addEventListener('resize', () => {
        const w = window.innerWidth;
        if (lastWidth < 900 && w >= 900 && isOpen()) {
            close();
        }
        // Dialog attribute'larını viewport değişimine göre güncelle
        if (lastWidth < 900 !== w < 900) {
            applyMobileDialogAttrs();
        }
        lastWidth = w;
    });
})();
