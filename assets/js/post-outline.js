// Outline panel — editör yan tarafında live H2/H3 listesi.
// MutationObserver ile .wy-editor değişimini dinler, sidebar'a render eder.

(function () {
    'use strict';

    const editor = document.querySelector('.wy-editor');
    const sidebar = document.querySelector('[data-outline-target]');
    if (!editor || !sidebar) return;

    const slug = s =>
        String(s || '')
            .toLowerCase()
            .replace(/[çğıöşü]/g, c => ({ ç: 'c', ğ: 'g', ı: 'i', ö: 'o', ş: 's', ü: 'u' })[c] || c)
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/(^-|-$)/g, '');

    const escapeHtml = s =>
        String(s).replace(
            /[&<>"']/g,
            c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]
        );

    const render = () => {
        const headings = editor.querySelectorAll('h2, h3, h4');
        if (!headings.length) {
            sidebar.innerHTML =
                '<p class="muted" style="font-size:.82rem">Başlık eklendiğinde içindekiler burada görünür (H2, H3, H4).</p>';
            return;
        }
        let html = '<ul class="outline-list">';
        headings.forEach(h => {
            const level = h.tagName.toLowerCase();
            const text = (h.textContent || '').trim();
            if (text === '') return;
            // Editor'a ID inject et — sonra anchor için
            if (!h.id) h.id = `h-${slug(text)}`;
            html += `<li class="outline-${level}"><a href="#${h.id}">${escapeHtml(text)}</a></li>`;
        });
        html += '</ul>';
        sidebar.innerHTML = html;
    };

    // Click handler — editör'deki başlığa scroll
    sidebar.addEventListener('click', e => {
        const a = e.target.closest('a[href^="#"]');
        if (!a) return;
        e.preventDefault();
        const id = a.getAttribute('href').slice(1);
        const target = editor.querySelector(`#${CSS.escape(id)}`);
        if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    // Initial + debounced live update
    let timer = null;
    const observer = new MutationObserver(() => {
        clearTimeout(timer);
        timer = setTimeout(render, 500);
    });
    observer.observe(editor, { childList: true, subtree: true, characterData: true });

    render();
})();
