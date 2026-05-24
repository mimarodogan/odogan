// quick-edit.js — Hızlı düzenle modal'ı (Tier 5 feature 4.3).
// posts/index.php satırlarındaki [data-quick-edit] buton tıklayınca modal açılır,
// kaydedince AJAX POST → JSON → satır in-place güncellenir.
// A11y: focus trap + return-focus to opener button.
(function () {
    'use strict';

    const modal = document.getElementById('quick-edit-modal');
    if (!modal) return;

    const form = modal.querySelector('[data-qe-form]');
    const statusEl = modal.querySelector('[data-qe-status]');
    const csrf = modal.getAttribute('data-csrf') || '';
    let currentUrl = '';
    let currentRow = null;
    let releaseTrap = null;
    let lastFocused = null;

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

    const open = btn => {
        lastFocused = btn || document.activeElement;
        currentUrl = btn.getAttribute('data-quick-url') || '';
        const id = btn.getAttribute('data-quick-edit');
        currentRow = document.querySelector(`tr[data-post-row="${id}"]`);
        form.title.value = btn.getAttribute('data-quick-title') || '';
        form.slug.value = btn.getAttribute('data-quick-slug') || '';
        form.status.value = btn.getAttribute('data-quick-status') || 'draft';
        if (form.featured) {
            form.featured.checked = btn.getAttribute('data-quick-featured') === '1';
        }
        if (statusEl) {
            statusEl.textContent = '';
            statusEl.className = 'qe-status muted';
        }
        modal.removeAttribute('hidden');
        releaseTrap = createFocusTrap(modal);
        setTimeout(() => {
            form.title.focus();
        }, 50);
    };

    const close = () => {
        modal.setAttribute('hidden', '');
        if (releaseTrap) {
            releaseTrap();
            releaseTrap = null;
        }
        if (lastFocused && typeof lastFocused.focus === 'function') {
            try {
                lastFocused.focus();
            } catch (_e) {
                /* ignore */
            }
        }
        lastFocused = null;
    };

    const setStatus = (msg, type) => {
        if (!statusEl) return;
        statusEl.textContent = msg;
        statusEl.className = `qe-status ${type === 'ok' ? 'is-ok' : 'is-err'}`;
    };

    const patchRow = post => {
        if (!currentRow) return;
        const titleEl = currentRow.querySelector('[data-row-title]');
        const statusEl2 = currentRow.querySelector('[data-row-status]');
        if (titleEl) titleEl.textContent = post.title;
        if (statusEl2) {
            statusEl2.textContent = post.status.charAt(0).toUpperCase() + post.status.slice(1);
            statusEl2.className = `badge badge-${post.status}`;
        }
        // Featured chip update — eğer varsa
        const existing = currentRow.querySelector('[title="Editörün Seçimi"]');
        if (post.featured === 1 && !existing && titleEl) {
            const chip = document.createElement('span');
            chip.className = 'badge';
            chip.title = 'Editörün Seçimi';
            chip.style.cssText =
                'background:var(--cobalt,#1e3a8a);color:#fff;font-size:.7rem;padding:.1rem .35rem;border-radius:3px;margin-left:.25rem';
            chip.textContent = '⭐';
            titleEl.parentNode.insertBefore(chip, titleEl.nextSibling);
        } else if (post.featured === 0 && existing) {
            existing.remove();
        }
    };

    document.addEventListener('click', e => {
        const btn = e.target.closest('[data-quick-edit]');
        if (btn) {
            e.preventDefault();
            open(btn);
            return;
        }
        if (e.target.matches('[data-qe-close]') || e.target.closest('[data-qe-close]')) {
            e.preventDefault();
            close();
        }
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && !modal.hasAttribute('hidden')) close();
    });

    form.addEventListener('submit', async e => {
        e.preventDefault();
        if (!currentUrl) {
            setStatus('URL yok.', 'err');
            return;
        }
        setStatus('Kaydediliyor…', 'ok');

        const fd = new FormData(form);
        fd.append('_token', csrf);
        // Eğer featured checkbox işaretli değilse hidden input "0" kalır — bu OK
        try {
            const r = await fetch(currentUrl, {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: fd,
                credentials: 'same-origin',
            });
            const data = await r.json();
            if (data.ok) {
                setStatus('Kaydedildi ✓', 'ok');
                if (data.post) patchRow(data.post);
                setTimeout(close, 600);
            } else {
                setStatus(`Hata: ${data.error || 'bilinmeyen'}`, 'err');
            }
        } catch {
            setStatus('Bağlantı hatası.', 'err');
        }
    });
})();
