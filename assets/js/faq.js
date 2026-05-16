// FAQ row builder + lightweight markdown preview.
// Stays under 10KB and uses no dependencies.
(function () {
    'use strict';

    const list = document.getElementById('faq-list');
    const addBtn = document.getElementById('faq-add');

    const nextIndex = () => (list ? list.querySelectorAll('.faq-row').length : 0);

    const buildRow = (index, q, a) => {
        const row = document.createElement('div');
        row.className = 'faq-row';
        row.innerHTML =
            `<input type="text" name="faq[${index}][q]" placeholder="Soru" maxlength="220">` +
            `<textarea name="faq[${index}][a]" placeholder="Cevap (markdown)" rows="2" maxlength="4000"></textarea>` +
            `<button type="button" class="btn faq-remove">Sil</button>`;
        if (q) row.querySelector('input').value = q;
        if (a) row.querySelector('textarea').value = a;
        return row;
    };

    if (addBtn && list) {
        addBtn.addEventListener('click', () => {
            list.appendChild(buildRow(nextIndex()));
        });
        list.addEventListener('click', ev => {
            if (ev.target && ev.target.classList.contains('faq-remove')) {
                ev.target.closest('.faq-row')?.remove();
            }
        });
    }

    // Tiny markdown preview (safe enough for editor — server still re-sanitizes
    // on render). Supports headings, bold/italic, code, lists, links.
    const body = document.getElementById('md-body');
    const preview = document.getElementById('md-preview');
    if (body && preview) {
        const update = () => {
            preview.innerHTML = renderMarkdown(body.value);
        };
        body.addEventListener('input', update);
        update();
    }

    const escapeHtml = s =>
        String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');

    const renderMarkdown = text => {
        if (!text) return '';
        const lines = escapeHtml(text).split(/\r?\n/);
        const out = [];
        let inUl = false;
        let inOl = false;
        let inCode = false;
        for (let i = 0; i < lines.length; i++) {
            const l = lines[i];
            if (/^```/.test(l)) {
                if (inCode) {
                    out.push('</code></pre>');
                    inCode = false;
                } else {
                    out.push('<pre><code>');
                    inCode = true;
                }
                continue;
            }
            if (inCode) {
                out.push(l);
                out.push('\n');
                continue;
            }
            const h = l.match(/^(#{1,6})\s+(.*)$/);
            if (h) {
                out.push(`<h${h[1].length}>${inline(h[2])}</h${h[1].length}>`);
                continue;
            }
            if (/^\s*[-*]\s+/.test(l)) {
                if (!inUl) {
                    out.push('<ul>');
                    inUl = true;
                }
                out.push(`<li>${inline(l.replace(/^\s*[-*]\s+/, ''))}</li>`);
                continue;
            } else if (inUl) {
                out.push('</ul>');
                inUl = false;
            }
            if (/^\s*\d+\.\s+/.test(l)) {
                if (!inOl) {
                    out.push('<ol>');
                    inOl = true;
                }
                out.push(`<li>${inline(l.replace(/^\s*\d+\.\s+/, ''))}</li>`);
                continue;
            } else if (inOl) {
                out.push('</ol>');
                inOl = false;
            }
            if (l.trim() === '') {
                out.push('');
                continue;
            }
            out.push(`<p>${inline(l)}</p>`);
        }
        if (inCode) out.push('</code></pre>');
        if (inUl) out.push('</ul>');
        if (inOl) out.push('</ol>');
        return out.join('\n');
    };

    const inline = s =>
        s
            .replace(/`([^`]+)`/g, '<code>$1</code>')
            .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
            .replace(/\*([^*]+)\*/g, '<em>$1</em>')
            .replace(
                /\[([^\]]+)\]\((https?:\/\/[^)]+)\)/g,
                '<a href="$2" rel="noopener" target="_blank">$1</a>'
            );
})();
