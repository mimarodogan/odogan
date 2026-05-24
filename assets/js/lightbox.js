// Otorite Yayin — Görsel lightbox + zoom/pan (Tier 5)
// Yazı body'sindeki <img>'lere tıklanınca tam ekran overlay aç.
// ESC veya backdrop click ile kapat.
// Sadece [data-toc-source] içindeki img'leri hedefler (yazı gövdesi).
//
// Tier 5 zoom-pan eklendi:
//   * Mouse wheel → scale 1.0-4.0
//   * Mouse drag → pan
//   * Touch pinch → zoom (2 parmak mesafe delta)
//   * Touch drag → pan
//   * Çift tıklama → reset
//   * Reset butonu (zoom > 1.0 olunca görünür)
// Feature flag: lightbox_zoom_enabled (varsayılan off → sadece açıp kapatır).
//
// A11y: img'ler <button> ile sarmalanır (anti-pattern role=button kaldırıldı).
// Overlay role=dialog + aria-modal=true + focus trap + return-focus.

(function () {
    'use strict';

    const source = document.querySelector('[data-toc-source]');
    if (!source) return;

    const images = source.querySelectorAll('img');
    if (!images.length) return;

    // Feature flag — server tarafı body data-attr ile basar (varsayılan kapalı)
    const zoomEnabled = document.body && document.body.dataset.lightboxZoom === '1';

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

    // Overlay element
    const overlay = document.createElement('div');
    overlay.className = 'lightbox';
    overlay.setAttribute('hidden', 'hidden');
    overlay.setAttribute('aria-hidden', 'true');
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', 'Görsel önizleme');
    overlay.innerHTML =
        '<button type="button" class="lightbox-close" aria-label="Kapat" title="Kapat">×</button>' +
        (zoomEnabled
            ? '<button type="button" class="lightbox-reset" aria-label="Yakınlaştırmayı sıfırla" title="Sıfırla" hidden>↺</button>'
            : '') +
        (zoomEnabled
            ? '<span class="lightbox-zoom-indicator" aria-hidden="true" hidden></span>'
            : '') +
        '<figure class="lightbox-figure">' +
        '<img alt="" class="lightbox-image">' +
        '<figcaption class="lightbox-caption"></figcaption>' +
        '</figure>';
    document.body.appendChild(overlay);

    const imgEl = overlay.querySelector('.lightbox-image');
    const capEl = overlay.querySelector('.lightbox-caption');
    const closeBtn = overlay.querySelector('.lightbox-close');
    const resetBtn = overlay.querySelector('.lightbox-reset');
    const zoomInd = overlay.querySelector('.lightbox-zoom-indicator');

    let releaseTrap = null;
    let lastFocused = null;

    // ─── Zoom/Pan State ──────────────────────────────────────────────────
    const SCALE_MIN = 1.0;
    const SCALE_MAX = 4.0;
    const SCALE_STEP = 0.18;
    const state = { scale: 1, tx: 0, ty: 0 };

    const applyTransform = () => {
        imgEl.style.transform = `translate(${state.tx}px,${state.ty}px) scale(${state.scale})`;
        const zoomed = state.scale > 1.001;
        imgEl.classList.toggle('is-zoomed', zoomed);
        if (resetBtn) resetBtn.toggleAttribute('hidden', !zoomed);
        if (zoomInd) {
            zoomInd.textContent = `${(state.scale * 100).toFixed(0)}%`;
            zoomInd.toggleAttribute('hidden', !zoomed);
        }
    };

    const resetTransform = () => {
        state.scale = 1;
        state.tx = 0;
        state.ty = 0;
        applyTransform();
    };

    const clampScale = s => Math.max(SCALE_MIN, Math.min(SCALE_MAX, s));

    // ─── Açma / Kapama ──────────────────────────────────────────────────
    const open = (src, alt, trigger) => {
        lastFocused = trigger || document.activeElement;
        imgEl.src = src;
        imgEl.alt = alt || '';
        capEl.textContent = alt || '';
        capEl.style.display = alt ? 'block' : 'none';
        overlay.removeAttribute('hidden');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        if (zoomEnabled) resetTransform();
        // Focus trap + initial focus
        releaseTrap = createFocusTrap(overlay);
        setTimeout(() => closeBtn.focus(), 20);
    };

    const close = () => {
        overlay.setAttribute('hidden', 'hidden');
        overlay.setAttribute('aria-hidden', 'true');
        imgEl.src = '';
        document.body.style.overflow = '';
        if (zoomEnabled) resetTransform();
        if (releaseTrap) {
            releaseTrap();
            releaseTrap = null;
        }
        // Return focus to opener
        if (lastFocused && typeof lastFocused.focus === 'function') {
            try {
                lastFocused.focus();
            } catch (_e) {
                /* ignore */
            }
        }
        lastFocused = null;
    };

    // Resimlere tıklama — <img>'i <button> ile sarmalayarak (orijinal alt korunur)
    images.forEach(img => {
        if (img.closest('a')) return; // link içindeyse atla
        if (img.closest('button.lightbox-trigger')) return; // zaten sarılmış
        const altText = img.getAttribute('alt') || '';
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'lightbox-trigger';
        btn.setAttribute('aria-label', altText ? `Görseli büyüt: ${altText}` : 'Görseli büyüt');
        // <button> kendi cursor'unu tanımlar; img.style.cursor gerek yok
        // Parent içinde img yerine btn yerleştir, img'i btn içine al
        const parent = img.parentNode;
        if (!parent) return;
        parent.insertBefore(btn, img);
        btn.appendChild(img);
        btn.addEventListener('click', () => {
            const src = img.currentSrc || img.src;
            open(src, img.getAttribute('alt') || '', btn);
        });
    });

    // Kapama: ESC, butona, backdrop click
    closeBtn.addEventListener('click', close);
    overlay.addEventListener('click', e => {
        if (e.target === overlay) close();
    });
    document.addEventListener('keydown', e => {
        if (overlay.hasAttribute('hidden')) return;
        if (e.key === 'Escape') close();
        if (zoomEnabled) {
            if (e.key === '+' || e.key === '=') {
                e.preventDefault();
                state.scale = clampScale(state.scale + SCALE_STEP);
                applyTransform();
            } else if (e.key === '-' || e.key === '_') {
                e.preventDefault();
                state.scale = clampScale(state.scale - SCALE_STEP);
                if (state.scale === 1) {
                    state.tx = 0;
                    state.ty = 0;
                }
                applyTransform();
            } else if (e.key === '0') {
                e.preventDefault();
                resetTransform();
            }
        }
    });

    // ─── Zoom & Pan ──────────────────────────────────────────────────────
    if (zoomEnabled) {
        if (resetBtn)
            resetBtn.addEventListener('click', e => {
                e.stopPropagation();
                resetTransform();
            });

        // Çift tıklama → toggle zoom 1↔2
        imgEl.addEventListener('dblclick', e => {
            e.preventDefault();
            if (state.scale > 1.001) {
                resetTransform();
            } else {
                state.scale = 2;
                applyTransform();
            }
        });

        // Wheel zoom — Ctrl+wheel veya plain wheel
        imgEl.addEventListener(
            'wheel',
            e => {
                e.preventDefault();
                const { left, top, width, height } = imgEl.getBoundingClientRect();
                const cx = e.clientX - left - width / 2;
                const cy = e.clientY - top - height / 2;
                const prev = state.scale;
                const delta = e.deltaY < 0 ? SCALE_STEP : -SCALE_STEP;
                state.scale = clampScale(prev + delta);
                // Mouse pointer altındaki noktayı sabitlemek için tx/ty düzeltme
                if (state.scale !== prev) {
                    const ratio = state.scale / prev;
                    state.tx = (state.tx - cx) * ratio + cx;
                    state.ty = (state.ty - cy) * ratio + cy;
                }
                if (state.scale === 1) {
                    state.tx = 0;
                    state.ty = 0;
                }
                applyTransform();
            },
            { passive: false }
        );

        // Mouse drag pan
        let dragging = false;
        let dragStart = { x: 0, y: 0, tx: 0, ty: 0 };
        imgEl.addEventListener('mousedown', e => {
            if (state.scale <= 1.001) return;
            dragging = true;
            dragStart = { x: e.clientX, y: e.clientY, tx: state.tx, ty: state.ty };
            imgEl.style.cursor = 'grabbing';
            e.preventDefault();
        });
        window.addEventListener('mousemove', e => {
            if (!dragging) return;
            state.tx = dragStart.tx + (e.clientX - dragStart.x);
            state.ty = dragStart.ty + (e.clientY - dragStart.y);
            applyTransform();
        });
        window.addEventListener('mouseup', () => {
            if (dragging) {
                dragging = false;
                imgEl.style.cursor = '';
            }
        });

        // Touch pinch + drag
        const touchState = {
            pinch: false,
            startDist: 0,
            startScale: 1,
            midX: 0,
            midY: 0,
            pan: false,
            startX: 0,
            startY: 0,
            startTx: 0,
            startTy: 0,
        };

        const dist = (a, b) => {
            const dx = a.clientX - b.clientX;
            const dy = a.clientY - b.clientY;
            return Math.sqrt(dx * dx + dy * dy);
        };

        imgEl.addEventListener(
            'touchstart',
            e => {
                if (e.touches.length === 2) {
                    touchState.pinch = true;
                    touchState.pan = false;
                    touchState.startDist = dist(e.touches[0], e.touches[1]);
                    touchState.startScale = state.scale;
                    touchState.midX = (e.touches[0].clientX + e.touches[1].clientX) / 2;
                    touchState.midY = (e.touches[0].clientY + e.touches[1].clientY) / 2;
                } else if (e.touches.length === 1 && state.scale > 1.001) {
                    touchState.pan = true;
                    touchState.pinch = false;
                    touchState.startX = e.touches[0].clientX;
                    touchState.startY = e.touches[0].clientY;
                    touchState.startTx = state.tx;
                    touchState.startTy = state.ty;
                }
            },
            { passive: true }
        );

        imgEl.addEventListener(
            'touchmove',
            e => {
                if (touchState.pinch && e.touches.length === 2) {
                    e.preventDefault();
                    const newDist = dist(e.touches[0], e.touches[1]);
                    state.scale = clampScale(
                        touchState.startScale * (newDist / touchState.startDist)
                    );
                    applyTransform();
                } else if (touchState.pan && e.touches.length === 1) {
                    e.preventDefault();
                    state.tx = touchState.startTx + (e.touches[0].clientX - touchState.startX);
                    state.ty = touchState.startTy + (e.touches[0].clientY - touchState.startY);
                    applyTransform();
                }
            },
            { passive: false }
        );

        imgEl.addEventListener('touchend', () => {
            touchState.pinch = false;
            touchState.pan = false;
            if (state.scale === 1) {
                state.tx = 0;
                state.ty = 0;
                applyTransform();
            }
        });
    }
})();
