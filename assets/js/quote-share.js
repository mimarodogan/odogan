// quote-share.js — Tier 8: yazıdan metin seç → tweet popup
(function () {
    'use strict';
    const body = document.querySelector('.post-body');
    if (!body) return;

    let popup = null;
    const siteUrl = location.href;
    const siteTitle = document.title;

    const getSelectionText = () => {
        const sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) return '';
        const text = sel.toString().trim();
        if (text.length < 12 || text.length > 280) return '';
        // Seçim post-body içinde mi?
        const range = sel.getRangeAt(0);
        let common = range.commonAncestorContainer;
        if (common.nodeType !== Node.ELEMENT_NODE) common = common.parentNode;
        if (!body.contains(common)) return '';
        return text;
    };

    const buildPopup = () => {
        const div = document.createElement('div');
        div.className = 'quote-popup';
        div.innerHTML =
            `<a href="#" data-quote-share="twitter" title="X/Twitter'da paylaş">𝕏</a>` +
            `<a href="#" data-quote-share="whatsapp" title="WhatsApp'ta paylaş">✆</a>` +
            `<a href="#" data-quote-share="copy" title="Linki kopyala">⎘</a>`;
        document.body.appendChild(div);
        return div;
    };

    const position = (p, range) => {
        const rect = range.getBoundingClientRect();
        const top = rect.top + window.scrollY - 50;
        const left = rect.left + rect.width / 2 - 80 + window.scrollX;
        p.style.top = `${top}px`;
        p.style.left = `${Math.max(10, left)}px`;
        p.style.display = 'flex';
    };

    document.addEventListener('mouseup', () => {
        setTimeout(() => {
            const text = getSelectionText();
            if (!text) {
                if (popup) popup.style.display = 'none';
                return;
            }
            if (!popup) popup = buildPopup();
            const sel = window.getSelection();
            position(popup, sel.getRangeAt(0));
            popup.setAttribute('data-quote-text', text);
        }, 10);
    });

    document.addEventListener('mousedown', e => {
        if (popup && popup.style.display === 'flex' && !popup.contains(e.target)) {
            popup.style.display = 'none';
        }
    });

    document.addEventListener('click', e => {
        const link = e.target.closest('[data-quote-share]');
        if (!link || !popup) return;
        e.preventDefault();
        const action = link.getAttribute('data-quote-share');
        const text = popup.getAttribute('data-quote-text') || '';
        const quote = `"${text}" — ${siteTitle} ${siteUrl}`;
        if (action === 'twitter') {
            window.open(
                `https://twitter.com/intent/tweet?text=${encodeURIComponent(quote)}`,
                '_blank',
                'noopener'
            );
        } else if (action === 'whatsapp') {
            window.open(`https://wa.me/?text=${encodeURIComponent(quote)}`, '_blank', 'noopener');
        } else if (action === 'copy') {
            try {
                navigator.clipboard.writeText(quote);
                link.textContent = '✓';
                setTimeout(() => {
                    link.textContent = '⎘';
                }, 1500);
            } catch {}
        }
        popup.style.display = 'none';
    });
})();
