// analytics-events.js — Tier 8: read-depth, time-on-page, outbound click
(function () {
    'use strict';
    const body = document.querySelector('[data-toc-source]');
    if (!body) return;

    const postIdMeta = document.querySelector('meta[name="post-id"]');
    if (!postIdMeta) return;
    const pid = parseInt(postIdMeta.getAttribute('content') || '0', 10);
    if (pid <= 0) return;

    const csrf = document.querySelector('meta[name="csrf-token"]');
    const token = csrf ? csrf.getAttribute('content') : '';

    const send = (type, valueInt, valueStr) => {
        if (!navigator.sendBeacon && !window.fetch) return;
        const data = new FormData();
        data.append('type', type);
        data.append('post_id', pid);
        if (typeof valueInt === 'number') data.append('value_int', valueInt);
        if (valueStr) data.append('value_str', valueStr);
        data.append('_csrf', token);
        if (navigator.sendBeacon) {
            try {
                navigator.sendBeacon('/analytics/event', data);
                return;
            } catch (e) {}
        }
        fetch('/analytics/event', {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': token },
            keepalive: true,
        }).catch(() => {});
    };

    // ─── Read Depth ────────────────────────────────────────────────
    const sentMilestones = {};
    const checkDepth = () => {
        const rect = body.getBoundingClientRect();
        const winH = window.innerHeight;
        const bodyTop = rect.top + window.scrollY;
        const bodyHeight = body.offsetHeight;
        const scrolled = window.scrollY + winH - bodyTop;
        const pct = Math.max(0, Math.min(100, Math.round((scrolled / bodyHeight) * 100)));
        [25, 50, 75, 100].forEach(m => {
            if (pct >= m && !sentMilestones[m]) {
                sentMilestones[m] = true;
                send('read_depth', m);
            }
        });
    };
    let depthTimer = null;
    window.addEventListener('scroll', () => {
        clearTimeout(depthTimer);
        depthTimer = setTimeout(checkDepth, 250);
    });
    checkDepth();

    // ─── Time on page ─────────────────────────────────────────────
    const startedAt = Date.now();
    let activeMs = 0;
    let lastTick = startedAt;
    let isActive = !document.hidden;

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            activeMs += Date.now() - lastTick;
            isActive = false;
        } else {
            lastTick = Date.now();
            isActive = true;
        }
    });
    window.addEventListener('beforeunload', () => {
        if (isActive) activeMs += Date.now() - lastTick;
        const seconds = Math.round(activeMs / 1000);
        if (seconds >= 3 && seconds <= 86400) {
            send('time_on_page', seconds);
        }
    });

    // ─── Outbound click tracking ───────────────────────────────────
    document.addEventListener('click', e => {
        const a = e.target.closest('a[href]');
        if (!a) return;
        try {
            const u = new URL(a.href);
            if (u.hostname !== location.hostname) {
                send('outbound_click', null, u.href);
            }
        } catch (err) {}
    });
})();
