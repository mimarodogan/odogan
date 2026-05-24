// Reading progress bar — shows how far down the article the reader has scrolled.
// Fires on rAF, so it's cheap. Targets [data-toc-source] (the article body
// the ToC already understands). Skips entirely on screens without a body
// long enough to scroll.
//
// Bonus: 25/50/75/100% milestone'larında GA "scroll_depth" event'i fırlatır.
(function () {
    'use strict';

    const article = document.querySelector('[data-toc-source]');
    if (!article) return;

    const bar = document.createElement('div');
    bar.className = 'read-progress';
    bar.setAttribute('aria-hidden', 'true');
    document.body.appendChild(bar);

    let ticking = false;
    const sentMilestones = {}; // {25:true, 50:true, ...}

    const fireScrollDepth = pct => {
        const milestones = [25, 50, 75, 100];
        for (let i = 0; i < milestones.length; i++) {
            const m = milestones[i];
            if (pct >= m && !sentMilestones[m]) {
                sentMilestones[m] = true;
                if (typeof window.gtag === 'function') {
                    try {
                        window.gtag('event', 'scroll_depth', { percent: m });
                    } catch (e) {}
                }
            }
        }
    };

    const update = () => {
        const rect = article.getBoundingClientRect();
        const docTop = window.scrollY || window.pageYOffset || 0;
        const top = rect.top + docTop;
        const height = article.offsetHeight - window.innerHeight;
        if (height <= 0) {
            bar.style.width = '0%';
            return;
        }
        const pct = Math.min(100, Math.max(0, ((docTop - top) / height) * 100));
        bar.style.width = `${pct}%`;
        fireScrollDepth(pct);
        ticking = false;
    };

    const onScroll = () => {
        if (!ticking) {
            window.requestAnimationFrame(update);
            ticking = true;
        }
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll, { passive: true });
    update();
})();
