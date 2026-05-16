// Lightweight Table of Contents.
// Reads h2/h3 inside [data-toc-source], injects anchors, and renders a
// nav into [data-toc-target]. Uses IntersectionObserver to highlight the
// current section. No deps. Skips work entirely if either element is missing.
(function () {
    'use strict';

    const src = document.querySelector('[data-toc-source]');
    const dst = document.querySelector('[data-toc-target]');
    if (!src || !dst) return;

    const headings = src.querySelectorAll('h2, h3');
    if (!headings.length) {
        // Hiç başlık yoksa TOC kutusunu tamamen gizle
        const tocBox = dst.closest('.toc');
        if (tocBox) tocBox.style.display = 'none';
        else dst.style.display = 'none';
        return;
    }

    const seen = Object.create(null);
    const items = [];

    headings.forEach(h => {
        if (!h.id) {
            let slug = (h.textContent || '')
                .toLowerCase()
                .replace(/[^a-z0-9À-ɏ]+/gi, '-')
                .replace(/^-+|-+$/g, '');
            if (!slug) slug = 'baslik';
            let base = slug,
                n = 2;
            while (seen[slug]) {
                slug = `${base}-${n++}`;
            }
            seen[slug] = 1;
            h.id = slug;
        }
        items.push({ id: h.id, text: h.textContent || '', level: h.tagName === 'H3' ? 3 : 2 });
    });

    const ul = document.createElement('ul');
    ul.className = 'toc-list';
    items.forEach(it => {
        const li = document.createElement('li');
        li.className = `toc-item toc-l${it.level}`;
        const a = document.createElement('a');
        a.href = `#${it.id}`;
        a.textContent = it.text;
        a.dataset.tocLink = it.id;
        li.appendChild(a);
        ul.appendChild(li);
    });
    dst.appendChild(ul);

    if ('IntersectionObserver' in window) {
        let current = null;
        const io = new IntersectionObserver(
            entries => {
                entries.forEach(e => {
                    if (e.isIntersecting) {
                        if (current) current.classList.remove('toc-active');
                        const link = dst.querySelector(`[data-toc-link="${e.target.id}"]`);
                        if (link) {
                            link.classList.add('toc-active');
                            current = link;
                        }
                    }
                });
            },
            { rootMargin: '-30% 0px -60% 0px', threshold: 0 }
        );
        headings.forEach(h => {
            io.observe(h);
        });
    }
})();
