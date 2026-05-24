// prefetch-hover.js — Tier 8 Performance
// Internal link'lere hover olunca/touch start'ında, hedef sayfayı önyükle.
(function () {
    'use strict';
    if (!('connection' in navigator) || (navigator.connection && navigator.connection.saveData)) {
        // Save Data açıksa veya bağlantı yavaşsa hiç prefetch yapma
        if (
            navigator.connection &&
            (navigator.connection.effectiveType === 'slow-2g' ||
                navigator.connection.effectiveType === '2g' ||
                navigator.connection.saveData)
        ) {
            return;
        }
    }
    const prefetched = {};

    const prefetch = url => {
        if (prefetched[url]) return;
        prefetched[url] = true;
        const link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = url;
        link.as = 'document';
        document.head.appendChild(link);
    };

    const eligibleHref = a => {
        if (!a.href) return null;
        try {
            const u = new URL(a.href);
            if (u.hostname !== location.hostname) return null;
            if (u.pathname.indexOf('/panel') === 0) return null;
            if (u.pathname.indexOf('/admin') === 0) return null;
            if (u.pathname.indexOf('/editor') === 0) return null;
            if (u.href === location.href) return null;
            return u.href;
        } catch (e) {
            return null;
        }
    };

    document.addEventListener('mouseover', e => {
        const a = e.target.closest('a[href]');
        if (!a) return;
        const url = eligibleHref(a);
        if (url) prefetch(url);
    });
    document.addEventListener(
        'touchstart',
        e => {
            const a = e.target.closest('a[href]');
            if (!a) return;
            const url = eligibleHref(a);
            if (url) prefetch(url);
        },
        { passive: true }
    );
})();
