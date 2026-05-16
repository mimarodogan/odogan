// reactions.js — Tier 8 Emoji Reactions
(function () {
    'use strict';
    const bar = document.querySelector('.reactions-bar');
    if (!bar) return;
    const postId = bar.getAttribute('data-reaction-post');
    const csrf = document.querySelector('meta[name="csrf-token"]');
    const token = csrf ? csrf.getAttribute('content') : '';

    bar.addEventListener('click', async e => {
        const btn = e.target.closest('.reaction-btn');
        if (!btn) return;
        const key = btn.getAttribute('data-reaction');
        try {
            const r = await fetch(`/etkilesim/reaksiyon/${postId}/${key}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': token,
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            });
            const data = await r.json().catch(() => ({ ok: false }));
            if (!data.ok) return;
            // Counts güncelle
            Object.keys(data.counts).forEach(k => {
                const el = bar.querySelector(`[data-reaction-count="${k}"]`);
                if (el) el.textContent = data.counts[k] || 0;
            });
            // Mine'a göre is-on classları + aria-pressed sync
            bar.querySelectorAll('.reaction-btn').forEach(b => {
                const k = b.getAttribute('data-reaction');
                const isMine = data.mine.indexOf(k) >= 0;
                b.classList.toggle('is-on', isMine);
                b.setAttribute('aria-pressed', isMine ? 'true' : 'false');
            });
            // Hafif pop animation
            btn.classList.add('is-bumping');
            setTimeout(() => {
                btn.classList.remove('is-bumping');
            }, 350);
        } catch (_err) {
            /* ignore network errors */
        }
    });
})();
