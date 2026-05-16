// engagement.js — Tier 7: Clap, Bookmark, Follow Author
// AJAX endpoint: /etkilesim/clap/{id}, /etkilesim/bookmark/{id}, /etkilesim/takip/{id}
(function () {
    'use strict';

    const csrf = document.querySelector('meta[name="csrf-token"]');
    const token = csrf ? csrf.getAttribute('content') : '';

    const post = async url => {
        try {
            const r = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': token,
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            });
            try {
                return await r.json();
            } catch {
                return { ok: false };
            }
        } catch {
            return { ok: false };
        }
    };

    const clap = async btn => {
        const pid = btn.getAttribute('data-engagement-post');
        if (!pid) return;
        btn.classList.add('is-bumping');
        setTimeout(() => {
            btn.classList.remove('is-bumping');
        }, 350);

        const data = await post(`/etkilesim/clap/${pid}`);
        if (data && data.ok) {
            const counter = btn.querySelector('[data-clap-count]');
            if (counter) counter.textContent = data.total;
            if (data.my_count >= 50) {
                btn.title = 'Maksimum 50 clap';
            }
        }
    };

    const bookmark = async btn => {
        if (btn.hasAttribute('data-engagement-auth-required')) {
            location.href = `/giris?next=${encodeURIComponent(location.pathname)}`;
            return;
        }
        const pid = btn.getAttribute('data-engagement-post');
        const data = await post(`/etkilesim/bookmark/${pid}`);
        if (data && data.ok) {
            btn.classList.toggle('is-on', data.saved);
            const icon = btn.querySelector('.eng-icon');
            const label = btn.querySelector('.eng-label');
            if (icon) icon.textContent = data.saved ? '★' : '☆';
            if (label) label.textContent = data.saved ? 'kayıtlı' : 'kaydet';
            btn.title = data.saved ? 'Kayıttan çıkar' : 'Daha sonra okumak için kaydet';
        } else if (data && data.error === 'login_required') {
            location.href = `/giris?next=${encodeURIComponent(location.pathname)}`;
        }
    };

    const follow = async btn => {
        const aid = btn.getAttribute('data-engagement-author');
        const data = await post(`/etkilesim/takip/${aid}`);
        if (data && data.ok) {
            btn.classList.toggle('is-on', data.following);
            const label = btn.querySelector('.eng-label');
            if (label) label.textContent = data.following ? 'Takipte' : 'Takip et';
            btn.title = data.following ? 'Takipten çık' : 'Yazara abone ol';
        } else if (data && data.error === 'login_required') {
            location.href = `/giris?next=${encodeURIComponent(location.pathname)}`;
        }
    };

    document.addEventListener('click', e => {
        const btn = e.target.closest('[data-engagement-action]');
        if (!btn) return;
        e.preventDefault();
        const action = btn.getAttribute('data-engagement-action');
        if (action === 'clap') clap(btn);
        else if (action === 'bookmark') bookmark(btn);
        else if (action === 'follow') follow(btn);
    });
})();
