// Otorite Yayin - tiny client bootstrap
(function () {
    'use strict';

    document.documentElement.dataset.jsReady = '1';

    // Auto-mark external links for security/UX
    document.querySelectorAll('a[href^="http"]').forEach(a => {
        try {
            const url = new URL(a.href);
            if (url.hostname !== location.hostname) {
                a.rel = (a.rel ? `${a.rel} ` : '') + 'noopener noreferrer';
                if (!a.target) a.target = '_blank';
            }
        } catch (e) {
            /* ignore */
        }
    });

    // ─── Mobile nav toggle ────────────────────────────────────────────
    const navBtn = document.querySelector('.nav-toggle');
    if (navBtn) {
        navBtn.addEventListener('click', () => {
            const open = document.body.classList.toggle('nav-open');
            navBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && document.body.classList.contains('nav-open')) {
                document.body.classList.remove('nav-open');
                navBtn.setAttribute('aria-expanded', 'false');
            }
        });
        // Menu içindeki linke tıklanınca otomatik kapat
        document.querySelectorAll('#primary-nav a').forEach(link => {
            link.addEventListener('click', () => {
                document.body.classList.remove('nav-open');
                navBtn.setAttribute('aria-expanded', 'false');
            });
        });
    }
})();

// Yukarı çık butonu — 600px aşağı kaydırınca görünür, tıklayınca smooth scroll.
(function () {
    'use strict';
    const btn = document.querySelector('.back-to-top');
    if (!btn) return;
    const toggle = () => { btn.hidden = window.scrollY <= 600; };
    let ticking = false;
    window.addEventListener('scroll', () => {
        if (!ticking) {
            ticking = true;
            window.requestAnimationFrame(() => { toggle(); ticking = false; });
        }
    }, { passive: true });
    toggle();
    btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
})();
