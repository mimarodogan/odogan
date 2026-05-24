// save-post.js — LocalStorage tabanlı "Bu yazıyı kaydet" sistemi.
// Tier 5 feature 2.4. Misafir veya üye fark etmez — tamamen client-side.
//
// Veri yapısı:
//   localStorage['odogan:saved'] = JSON array:
//   [{id, title, url, cover, excerpt, saved_at}]
//
// Davranış:
//   * Share-buttons partial'daki [data-save-post] butonu işaretler/kaldırır
//   * Header'da [data-saved-count] var ise live badge gösterir
//   * /kaydedilenler sayfasında [data-saved-list] varsa kart liste render eder
(function () {
    'use strict';

    const KEY = 'odogan:saved';
    const MAX = 200;

    const read = () => {
        try {
            const raw = localStorage.getItem(KEY);
            if (!raw) return [];
            const arr = JSON.parse(raw);
            return Array.isArray(arr) ? arr : [];
        } catch (e) {
            return [];
        }
    };

    const write = arr => {
        try {
            localStorage.setItem(KEY, JSON.stringify(arr.slice(0, MAX)));
        } catch (e) {}
    };

    const isSaved = id => read().some(i => Number(i.id) === Number(id));

    const toggle = item => {
        const arr = read();
        const idx = arr.findIndex(i => Number(i.id) === Number(item.id));
        if (idx >= 0) {
            arr.splice(idx, 1);
            write(arr);
            return false; // saved=false
        }
        arr.unshift({
            id: Number(item.id),
            title: String(item.title || '').slice(0, 220),
            url: String(item.url || ''),
            cover: String(item.cover || ''),
            excerpt: String(item.excerpt || '').slice(0, 280),
            saved_at: new Date().toISOString(),
        });
        write(arr);
        return true; // saved=true
    };

    const updateBtn = btn => {
        const id = btn.getAttribute('data-save-post');
        if (!id) return;
        const saved = isSaved(id);
        btn.classList.toggle('is-saved', saved);
        btn.setAttribute('title', saved ? 'Kaydedildi — kaldırmak için tıkla' : 'Bu yazıyı kaydet');
        const ic = btn.querySelector('.save-icon');
        if (ic) ic.textContent = saved ? '♥' : '♡';
    };

    const updateAllBtns = () => {
        const btns = document.querySelectorAll('[data-save-post]');
        for (let i = 0; i < btns.length; i++) updateBtn(btns[i]);
    };

    const updateCounter = () => {
        const els = document.querySelectorAll('[data-saved-count]');
        const n = read().length;
        for (let i = 0; i < els.length; i++) {
            els[i].textContent = n > 0 ? String(n) : '';
            els[i].toggleAttribute('hidden', n === 0);
        }
    };

    const attachToggle = () => {
        document.addEventListener('click', e => {
            const btn = e.target.closest('[data-save-post]');
            if (!btn) return;
            e.preventDefault();
            const item = {
                id: btn.getAttribute('data-save-post'),
                title: btn.getAttribute('data-save-title') || '',
                url: btn.getAttribute('data-save-url') || '',
                cover: btn.getAttribute('data-save-cover') || '',
                excerpt: btn.getAttribute('data-save-excerpt') || '',
            };
            toggle(item);
            updateBtn(btn);
            updateCounter();
            // Cross-tab senkron için event
            window.dispatchEvent(new CustomEvent('odogan:saved:change'));
        });
    };

    // /kaydedilenler sayfasında liste render — data-saved-list container varsa
    const renderList = () => {
        const host = document.querySelector('[data-saved-list]');
        if (!host) return;
        const arr = read();
        if (!arr.length) {
            host.innerHTML =
                '<p class="muted" style="text-align:center;padding:3rem 0">' +
                'Henüz kaydedilmiş yazı yok. Bir yazı sayfasındaki ♡ butonuna tıklayarak kaydedebilirsin.</p>';
            host.setAttribute('aria-busy', 'false');
            return;
        }
        let html = '<div class="mag-grid">';
        for (let i = 0; i < arr.length; i++) {
            const it = arr[i];
            const title = (it.title || '').replace(/&/g, '&amp;').replace(/</g, '&lt;');
            const url = (it.url || '').replace(/"/g, '&quot;');
            const cover = (it.cover || '').replace(/"/g, '&quot;');
            const excerpt = (it.excerpt || '').replace(/&/g, '&amp;').replace(/</g, '&lt;');
            html += '<article class="mag-card mag-card-saved">';
            if (cover) {
                html += `<a class="mag-cover" href="${url}"><img src="${cover}" alt="${title}" loading="lazy" decoding="async"></a>`;
            } else {
                html += `<a class="mag-cover mag-cover-empty" href="${url}"></a>`;
            }
            html += `<h3><a href="${url}">${title}</a></h3>`;
            if (excerpt) html += `<p>${excerpt}</p>`;
            html += `<p class="mag-meta"><button type="button" class="saved-remove" data-saved-remove="${Number(it.id)}">Kaldır</button></p>`;
            html += '</article>';
        }
        html += '</div>';
        host.innerHTML = html;
        host.setAttribute('aria-busy', 'false');
    };

    const attachRemove = () => {
        document.addEventListener('click', e => {
            const btn = e.target.closest('[data-saved-remove]');
            if (!btn) return;
            const id = btn.getAttribute('data-saved-remove');
            let arr = read();
            arr = arr.filter(i => Number(i.id) !== Number(id));
            write(arr);
            renderList();
            updateAllBtns();
            updateCounter();
        });
    };

    // Cross-tab sync
    window.addEventListener('storage', e => {
        if (e.key === KEY) {
            updateAllBtns();
            updateCounter();
            renderList();
        }
    });

    const init = () => {
        attachToggle();
        attachRemove();
        updateAllBtns();
        updateCounter();
        renderList();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
