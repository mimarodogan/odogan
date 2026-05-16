// Otorite Yayin — Sosyal paylaşım enhancers
// 1) Mobil cihazda Web Share API ile native sheet aç (varsa)
// 2) "Linki kopyala" butonu — Clipboard API
// 3) Tüm tıklamalar GA event'i fırlatır (gtag varsa)

(function () {
    'use strict';

    const boxes = document.querySelectorAll('.share-buttons');
    if (!boxes.length) return;

    const gaEvent = name => {
        if (typeof window.gtag === 'function') {
            try {
                window.gtag('event', 'share_click', { share_target: name });
            } catch {}
        }
    };

    const flashCopied = el => {
        const orig = el.getAttribute('title') || '';
        el.setAttribute('title', '✓ Kopyalandı');
        el.classList.add('share-copied');
        setTimeout(() => {
            el.setAttribute('title', orig);
            el.classList.remove('share-copied');
        }, 2000);
    };

    boxes.forEach(box => {
        const url = box.getAttribute('data-share-url') || location.href;
        const title = box.getAttribute('data-share-title') || document.title;

        // Web Share API — sadece desteklenen mobil cihazlarda
        if (navigator.share && /Mobi|Android|iPhone|iPad/.test(navigator.userAgent)) {
            const nativeBtn = document.createElement('button');
            nativeBtn.type = 'button';
            nativeBtn.className = 'share-btn share-native';
            nativeBtn.setAttribute('title', 'Cihaz paylaşımı');
            nativeBtn.innerHTML =
                '<span aria-hidden="true">⤴</span><span class="visually-hidden">Cihaz paylaşımı</span>';
            nativeBtn.addEventListener('click', () => {
                navigator.share({ title, url }).catch(() => {});
                gaEvent('native');
            });
            box.appendChild(nativeBtn);
        }

        // Tüm buton tıklamaları (links + copy)
        box.querySelectorAll('[data-share]').forEach(el => {
            el.addEventListener('click', ev => {
                const target = el.getAttribute('data-share');

                if (target === 'copy') {
                    ev.preventDefault();
                    try {
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(url).then(() => {
                                flashCopied(el);
                            });
                        } else {
                            const ta = document.createElement('textarea');
                            ta.value = url;
                            document.body.appendChild(ta);
                            ta.select();
                            document.execCommand('copy');
                            document.body.removeChild(ta);
                            flashCopied(el);
                        }
                        gaEvent('copy');
                    } catch {}
                    return;
                }

                gaEvent(target);
                // Twitter/LinkedIn için popup penceresinde aç (UX)
                if (['twitter', 'linkedin', 'facebook'].includes(target)) {
                    ev.preventDefault();
                    window.open(el.href, '_blank', 'width=600,height=500,noopener,noreferrer');
                }
            });
        });
    });
})();
