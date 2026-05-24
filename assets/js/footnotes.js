// Public footnote enhancers
// - [^N] markerları sup link olarak render edilmiş; tıklayınca smooth scroll
// - Hover'da tooltip ile dipnot metni önizleme
// - Kaynaklar bölümünde ↩ tıklayınca yazıdaki ref'e geri dön + highlight

(function () {
    'use strict';

    const refs = document.querySelectorAll('.footnote-ref a, .fn-back');
    if (!refs.length) return;

    const list = document.getElementById('footnotes');
    const items = list ? Array.from(list.querySelectorAll('li[id^="fn-"]')) : [];

    // Tooltip kutusu (singleton)
    let tip = null;
    const ensureTip = () => {
        if (tip) return tip;
        tip = document.createElement('div');
        tip.className = 'footnote-tooltip';
        tip.setAttribute('role', 'tooltip');
        tip.setAttribute('hidden', '');
        document.body.appendChild(tip);
        return tip;
    };

    const smoothJump = targetId => {
        const el = document.getElementById(targetId);
        if (!el) return;
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.classList.add('fn-highlight');
        setTimeout(() => {
            el.classList.remove('fn-highlight');
        }, 1800);
    };

    // Hover tooltip — sadece sup link'lerde
    document.querySelectorAll('.footnote-ref a').forEach(a => {
        const n = parseInt((a.textContent || '').trim(), 10);
        if (!n) return;
        const li = items.find(it => it.id === `fn-${n}`);
        if (!li) return;
        const textEl = li.querySelector('.fn-text');
        const snippet = textEl ? textEl.textContent.trim() : '';

        a.addEventListener('mouseenter', () => {
            const t = ensureTip();
            t.textContent = snippet;
            t.removeAttribute('hidden');
            const rect = a.getBoundingClientRect();
            const top = window.scrollY + rect.bottom + 8;
            const left = window.scrollX + rect.left - 12;
            t.style.top = `${top}px`;
            t.style.left = `${left}px`;
        });
        a.addEventListener('mouseleave', () => {
            if (tip) tip.setAttribute('hidden', '');
        });
        a.addEventListener('click', e => {
            e.preventDefault();
            smoothJump(`fn-${n}`);
            history.replaceState(null, '', `#fn-${n}`);
        });
    });

    // "↩" — kaynaktan geri dön
    document.querySelectorAll('.fn-back').forEach(a => {
        a.addEventListener('click', e => {
            e.preventDefault();
            const href = (a.getAttribute('href') || '').replace('#', '');
            if (href) {
                smoothJump(href);
                history.replaceState(null, '', `#${href}`);
            }
        });
    });
})();
