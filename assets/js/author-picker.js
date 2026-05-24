// Co-author picker — autocomplete arama + chip ekleme.
// Sidebar'daki .pe-coauthors container'ında çalışır.

(function () {
    'use strict';

    const box = document.querySelector('[data-coauthor-search-url]');
    if (!box) return;

    const url = box.getAttribute('data-coauthor-search-url');
    const list = box.querySelector('[data-coauthor-list]');
    const input = box.querySelector('[data-coauthor-input]');
    const results = box.querySelector('[data-coauthor-results]');
    if (!list || !input || !results) return;

    let timer = null;
    let lastQuery = '';

    const selectedIds = () =>
        Array.from(list.querySelectorAll('input[name="co_authors[]"]')).map(el =>
            parseInt(el.value, 10)
        );

    const escapeHtml = s =>
        String(s).replace(
            /[&<>"']/g,
            c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]
        );

    const addChip = user => {
        const ids = selectedIds();
        if (ids.indexOf(user.id) !== -1) return;
        const chip = document.createElement('span');
        chip.className = 'pe-coauthor-chip';
        chip.setAttribute('data-coauthor-chip', '');
        chip.innerHTML =
            `<input type="hidden" name="co_authors[]" value="${user.id}">` +
            escapeHtml(user.name) +
            ' <button type="button" class="pe-coauthor-remove" aria-label="Kaldır">×</button>';
        list.appendChild(chip);
        input.value = '';
        results.setAttribute('hidden', '');
    };

    const search = async q => {
        const selected = selectedIds();
        try {
            const r = await fetch(`${url}?q=${encodeURIComponent(q)}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            const j = await r.json();
            if (!j.ok || !j.results || j.results.length === 0) {
                results.innerHTML =
                    '<p class="muted" style="padding:.5rem .75rem;font-size:.82rem">Sonuç yok.</p>';
                results.removeAttribute('hidden');
                return;
            }
            const html = j.results
                .filter(u => selected.indexOf(u.id) === -1)
                .map(
                    u =>
                        `<button type="button" class="pe-coauthor-result" data-uid="${u.id}">` +
                        `<strong>${escapeHtml(u.name)}</strong>` +
                        ` <small>${escapeHtml(u.role)}</small>` +
                        '</button>'
                )
                .join('');
            if (!html) {
                results.innerHTML =
                    '<p class="muted" style="padding:.5rem .75rem;font-size:.82rem">Eşleşen başka kullanıcı yok.</p>';
            } else {
                results.innerHTML = html;
            }
            results.removeAttribute('hidden');
            results._data = j.results;
        } catch {
            // Sessiz hata — ağ sorunlarında dropdown kapalı kalır
        }
    };

    input.addEventListener('input', () => {
        const q = input.value.trim();
        if (q === lastQuery) return;
        lastQuery = q;
        clearTimeout(timer);
        if (q.length < 2) {
            results.setAttribute('hidden', '');
            return;
        }
        timer = setTimeout(() => {
            search(q);
        }, 250);
    });

    results.addEventListener('click', e => {
        const btn = e.target.closest('.pe-coauthor-result');
        if (!btn) return;
        const uid = parseInt(btn.dataset.uid, 10);
        const data = results._data || [];
        const user = data.find(u => u.id === uid);
        if (user) addChip(user);
    });

    list.addEventListener('click', e => {
        const btn = e.target.closest('.pe-coauthor-remove');
        if (!btn) return;
        const chip = btn.closest('[data-coauthor-chip]');
        if (chip) chip.remove();
    });

    document.addEventListener('click', e => {
        if (!box.contains(e.target)) {
            results.setAttribute('hidden', '');
        }
    });
})();
