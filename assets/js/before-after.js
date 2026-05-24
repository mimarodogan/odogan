/* Before/After Slider (Tier 9) — drag handle to compare two images.
 * Mouse + touch, keyboard (←/→), ARIA-compatible. */
(function () {
    'use strict';

    const init = () => {
        document.querySelectorAll('.before-after').forEach(setupOne);
    };

    const setupOne = fig => {
        if (fig.dataset.baReady === '1') return;
        fig.dataset.baReady = '1';

        const stage = fig.querySelector('.before-after-stage');
        const clip = fig.querySelector('.ba-clip');
        const handle = fig.querySelector('.ba-handle');
        if (!stage || !clip || !handle) return;

        let dragging = false;

        const setCut = pct => {
            pct = Math.max(0, Math.min(100, pct));
            clip.style.setProperty('--ba-cut', `${pct}%`);
            handle.style.left = `${pct}%`;
            handle.setAttribute('aria-valuenow', String(Math.round(pct)));
        };

        const onMove = clientX => {
            const { left, width } = stage.getBoundingClientRect();
            const pct = ((clientX - left) / width) * 100;
            setCut(pct);
        };

        handle.setAttribute('role', 'slider');
        handle.setAttribute('aria-label', 'Öncesi/Sonrası karşılaştırması');
        handle.setAttribute('aria-valuemin', '0');
        handle.setAttribute('aria-valuemax', '100');
        handle.setAttribute('aria-valuenow', '50');
        handle.setAttribute('tabindex', '0');

        handle.addEventListener('mousedown', e => {
            dragging = true;
            e.preventDefault();
        });
        document.addEventListener('mouseup', () => {
            dragging = false;
        });
        document.addEventListener('mousemove', e => {
            if (dragging) onMove(e.clientX);
        });

        stage.addEventListener('click', e => {
            if (e.target.closest('.ba-handle')) return;
            onMove(e.clientX);
        });

        handle.addEventListener(
            'touchstart',
            () => {
                dragging = true;
            },
            { passive: true }
        );
        document.addEventListener('touchend', () => {
            dragging = false;
        });
        document.addEventListener(
            'touchmove',
            e => {
                if (!dragging || !e.touches[0]) return;
                onMove(e.touches[0].clientX);
            },
            { passive: true }
        );

        handle.addEventListener('keydown', e => {
            const cur = parseFloat(handle.getAttribute('aria-valuenow') || '50');
            if (e.key === 'ArrowLeft') {
                setCut(cur - 5);
                e.preventDefault();
            } else if (e.key === 'ArrowRight') {
                setCut(cur + 5);
                e.preventDefault();
            } else if (e.key === 'Home') {
                setCut(0);
                e.preventDefault();
            } else if (e.key === 'End') {
                setCut(100);
                e.preventDefault();
            }
        });
    };

    if (document.readyState !== 'loading') init();
    else document.addEventListener('DOMContentLoaded', init);
})();
