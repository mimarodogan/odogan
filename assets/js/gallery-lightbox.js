/* ════════════════════════════════════════════════════════════════════
 * Gallery Lightbox — Proje galerisi için tam fotoğraf galerisi.
 *
 * Tetikleyici: <a class="js-gallery" data-gallery="proje-galerisi"
 *                 data-caption="Açıklama" href="https://.../big.jpg">
 *                 <img src="thumb.jpg"></a>
 *
 * Özellikler:
 *   - Overlay opens on click; ESC veya backdrop click ile kapat
 *   - Prev/Next butonları + ← → ok tuşları
 *   - Counter (3 / 12)
 *   - Touch swipe (sol/sağ → prev/next)
 *   - Thumbnail strip alt kısmında (10+ görsel varsa)
 *   - Atelier estetiği: cobalt accent, serif caption, monospace counter
 *
 * A11y: role=dialog + aria-modal=true + focus trap + return-focus.
 * ════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    const items = [];
    let currentIndex = 0;
    let overlay = null;
    let releaseTrap = null;
    let lastFocused = null;

    const escAttr = s => String(s).replace(/"/g, '&quot;').replace(/</g, '&lt;');

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

    const show = index => {
        if (!items.length) return;
        // Wrap-around (sona gelince başa, başa gelince sona)
        if (index < 0) index = items.length - 1;
        if (index >= items.length) index = 0;
        currentIndex = index;

        const { url, caption } = items[index];
        const img = overlay.querySelector('.gl-image');
        const cap = overlay.querySelector('.gl-caption');
        const counter = overlay.querySelector('.gl-counter');

        // Preload: yeni image yüklenirken eski görseli hala göster
        const tmp = new Image();
        tmp.onload = () => {
            img.src = url;
            img.alt = caption;
        };
        tmp.src = url;
        // Anlık feedback için src'yi yine de set et (cache'liyse aynı an gözükür)
        img.src = url;
        img.alt = caption;
        cap.textContent = caption;
        counter.textContent = `${index + 1} / ${items.length}`;

        // Strip aktif thumbnail
        const thumbs = overlay.querySelectorAll('.gl-thumb');
        thumbs.forEach((t, i) => {
            t.classList.toggle('is-active', i === index);
            t.setAttribute('aria-selected', i === index ? 'true' : 'false');
            if (i === index)
                t.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
        });

        // Prev/Next disabled state — sadece tek görsel varsa gizle
        const prevBtn = overlay.querySelector('.gl-prev');
        const nextBtn = overlay.querySelector('.gl-next');
        const single = items.length < 2;
        prevBtn.hidden = single;
        nextBtn.hidden = single;
    };

    const prev = () => show(currentIndex - 1);
    const next = () => show(currentIndex + 1);

    const open = (index, trigger) => {
        lastFocused = trigger || document.activeElement;
        if (!overlay) buildOverlay();
        show(index);
        overlay.removeAttribute('hidden');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('gallery-lightbox-open');
        document.body.style.overflow = 'hidden';
        releaseTrap = createFocusTrap(overlay);
        setTimeout(() => {
            const closeBtn = overlay.querySelector('.gl-close');
            if (closeBtn) closeBtn.focus();
        }, 20);
    };

    const close = () => {
        if (!overlay) return;
        overlay.setAttribute('hidden', 'hidden');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('gallery-lightbox-open');
        document.body.style.overflow = '';
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

    const renderStrip = () => {
        if (!overlay) return;
        const strip = overlay.querySelector('.gl-strip');
        if (items.length < 2) {
            strip.style.display = 'none';
            return;
        }
        strip.innerHTML = '';
        items.forEach((it, i) => {
            const t = document.createElement('button');
            t.type = 'button';
            t.className = 'gl-thumb';
            t.setAttribute('role', 'tab');
            t.setAttribute('aria-label', `Görsel ${i + 1}`);
            t.setAttribute('aria-selected', i === currentIndex ? 'true' : 'false');
            t.innerHTML = `<img src="${escAttr(it.thumb || it.url)}" alt="">`;
            t.addEventListener('click', () => show(i));
            strip.appendChild(t);
        });
    };

    const buildOverlay = () => {
        overlay = document.createElement('div');
        overlay.className = 'gallery-lightbox';
        overlay.setAttribute('hidden', 'hidden');
        overlay.setAttribute('aria-hidden', 'true');
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Galeri');
        overlay.innerHTML =
            '<button type="button" class="gl-close" aria-label="Kapat (Esc)" title="Kapat">×</button>' +
            '<button type="button" class="gl-prev" aria-label="Önceki (←)" title="Önceki">‹</button>' +
            '<button type="button" class="gl-next" aria-label="Sonraki (→)" title="Sonraki">›</button>' +
            '<div class="gl-stage">' +
            '<figure class="gl-figure">' +
            '<img class="gl-image" alt="">' +
            '<figcaption class="gl-caption"></figcaption>' +
            '</figure>' +
            '</div>' +
            '<div class="gl-meta">' +
            '<span class="gl-counter"></span>' +
            '</div>' +
            '<div class="gl-strip" role="tablist"></div>';
        document.body.appendChild(overlay);

        overlay.querySelector('.gl-close').addEventListener('click', close);
        overlay.querySelector('.gl-prev').addEventListener('click', prev);
        overlay.querySelector('.gl-next').addEventListener('click', next);

        // Backdrop click (stage dışına tıklayınca kapat)
        overlay.addEventListener('click', ev => {
            if (ev.target === overlay) close();
        });

        // Touch swipe
        let touchStartX = 0;
        let touchStartY = 0;
        overlay.addEventListener(
            'touchstart',
            ev => {
                if (!ev.touches[0]) return;
                touchStartX = ev.touches[0].clientX;
                touchStartY = ev.touches[0].clientY;
            },
            { passive: true }
        );
        overlay.addEventListener(
            'touchend',
            ev => {
                if (!ev.changedTouches[0]) return;
                const { clientX, clientY } = ev.changedTouches[0];
                const dx = clientX - touchStartX;
                const dy = clientY - touchStartY;
                // Sadece yatay swipe (dikey scroll'u koru)
                if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) {
                    if (dx > 0) prev();
                    else next();
                }
            },
            { passive: true }
        );

        renderStrip();
    };

    const bindKeyboard = () => {
        document.addEventListener('keydown', ev => {
            if (!overlay || overlay.hasAttribute('hidden')) return;
            if (ev.key === 'Escape') {
                close();
                ev.preventDefault();
            } else if (ev.key === 'ArrowLeft') {
                prev();
                ev.preventDefault();
            } else if (ev.key === 'ArrowRight') {
                next();
                ev.preventDefault();
            } else if (ev.key === 'Home') {
                show(0);
                ev.preventDefault();
            } else if (ev.key === 'End') {
                show(items.length - 1);
                ev.preventDefault();
            }
        });
    };

    const bindFullscreenButtons = () => {
        document.querySelectorAll('[data-gallery-open]').forEach(btn => {
            btn.addEventListener('click', ev => {
                ev.preventDefault();
                if (items.length) open(0, btn);
            });
        });
    };

    const init = () => {
        const triggers = document.querySelectorAll('.js-gallery');
        if (!triggers.length) return;

        triggers.forEach((a, i) => {
            const imgEl = a.querySelector('img');
            const item = {
                url: a.getAttribute('href') || a.getAttribute('data-full') || '',
                thumb: (imgEl || {}).src || '',
                caption: a.getAttribute('data-caption') || (imgEl || {}).alt || '',
                index: i,
            };
            items.push(item);
            a.addEventListener('click', ev => {
                ev.preventDefault();
                open(i, a);
            });
        });

        buildOverlay();
        bindKeyboard();
        bindFullscreenButtons();
    };

    if (document.readyState !== 'loading') init();
    else document.addEventListener('DOMContentLoaded', init);
})();
