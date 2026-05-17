// SEO skoru + Okunabilirlik — yazı yazarken debounced live analiz.
// Sidebar'daki .pe-analyze containerını günceller.

(function () {
    'use strict';

    const container = document.querySelector('[data-analyze-container]');
    if (!container) return;

    const url = container.getAttribute('data-analyze-url');
    const csrf = container.getAttribute('data-analyze-csrf');
    if (!url || !csrf) return;

    const titleEl = document.querySelector('input[name="title"]');
    const slugEl = document.querySelector('input[name="slug"]');
    const bodyEl = document.getElementById('rich-body'); // hidden textarea
    const richEditor = document.querySelector('.wy-editor'); // contentEditable div
    const excerptEl = document.querySelector('textarea[name="excerpt"]');
    const metaTitleEl = document.querySelector('input[name="meta_title"]');
    const metaDescEl = document.querySelector('textarea[name="meta_description"]');

    if (!bodyEl && !richEditor) return;

    // Body içeriği: önce live contentEditable div, yoksa textarea
    const getBody = () => {
        if (richEditor && richEditor.innerHTML) return richEditor.innerHTML;
        if (bodyEl && bodyEl.value) return bodyEl.value;
        return '';
    };

    const seoBox = container.querySelector('[data-seo-box]');
    const readBox = container.querySelector('[data-read-box]');

    let lastFp = '';
    let pending = null;
    let timer = null;
    const DELAY = 1500;

    const fingerprint = () =>
        `${titleEl ? titleEl.value : ''}|${slugEl ? slugEl.value : ''}|${getBody().length}|${excerptEl ? excerptEl.value.length : '0'}|${metaTitleEl ? metaTitleEl.value : ''}|${metaDescEl ? metaDescEl.value : ''}`;

    const payload = () => {
        const fd = new FormData();
        fd.append('_csrf', csrf);
        fd.append('title', titleEl ? titleEl.value : '');
        fd.append('slug', slugEl ? slugEl.value : '');
        fd.append('body', getBody());
        fd.append('body_format', 'html');
        fd.append('excerpt', excerptEl ? excerptEl.value : '');
        fd.append('meta_title', metaTitleEl ? metaTitleEl.value : '');
        fd.append('meta_description', metaDescEl ? metaDescEl.value : '');
        return fd;
    };

    const escapeHtml = s =>
        String(s).replace(
            /[&<>"']/g,
            c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]
        );

    const renderSeoItem = ({ ok, name, score, max, tip }) => {
        const icon = ok ? '✓' : '!';
        const cls = ok ? 'fn-ok' : 'fn-warn';
        return `<li class="${cls}"><span class="mark">${icon}</span><span class="lbl">${escapeHtml(name)}</span><span class="pts">${score}/${max}</span><div class="tip">${escapeHtml(tip)}</div></li>`;
    };

    const renderSeo = seo => {
        if (!seoBox || !seo) return;
        const pct = seo.max > 0 ? Math.round((seo.score / seo.max) * 100) : 0;
        const ringColor = pct >= 80 ? '#2F6A3E' : pct >= 50 ? '#8C6A12' : '#B0241D';
        const parts = (seo.parts || []).map(renderSeoItem).join('');
        seoBox.innerHTML =
            `<div class="score-ring" style="--ring-pct:${pct};--ring-col:${ringColor}">` +
            `<div class="score-val">${seo.score}</div>` +
            `<div class="score-max">/ ${seo.max}</div>` +
            `</div><ul class="score-parts">${parts}</ul>`;
    };

    const renderReadability = r => {
        if (!readBox || !r) return;
        const { score, category, words, sentences, avg_sentence_words, tip } = r;
        const col = score >= 70 ? '#2F6A3E' : score >= 50 ? '#8C6A12' : '#B0241D';
        readBox.innerHTML =
            `<div class="read-score" style="color:${col}">` +
            `<strong>${score}</strong><span class="cat">${escapeHtml(category)}</span></div>` +
            `<dl class="read-stats">` +
            `<dt>Kelime</dt><dd>${words}</dd>` +
            `<dt>Cümle</dt><dd>${sentences}</dd>` +
            `<dt>Ort. kelime/cümle</dt><dd>${avg_sentence_words}</dd>` +
            `</dl>` +
            `<p class="muted read-tip">${escapeHtml(tip)}</p>`;
    };

    const showEmpty = msg => {
        const html = `<p class="muted" style="font-size:.85rem">${escapeHtml(msg)}</p>`;
        if (seoBox) seoBox.innerHTML = html;
        if (readBox) readBox.innerHTML = html;
    };

    const tick = async () => {
        // Eğer body henüz çok kısaysa (anlamsız), nazikçe boş göster
        if (getBody().trim().length < 50 && (!titleEl || titleEl.value.trim().length < 3)) {
            showEmpty('Yazmaya başlayın — analiz otomatik güncellenecek.');
            return;
        }
        const fp = fingerprint();
        if (fp === lastFp) return;
        lastFp = fp;

        if (pending) pending.aborted = true;
        const ticket = { aborted: false };
        pending = ticket;

        try {
            const r = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrf, Accept: 'application/json' },
                credentials: 'same-origin',
                body: payload(),
            });
            let j;
            try {
                j = await r.json();
            } catch (_) {
                j = { ok: false, _parse_err: true, _status: r.status };
            }
            if (ticket.aborted) return;
            // 401 — session timeout (AuthMiddleware JSON 401 dönüyor)
            if (r.status === 401 || (j && j.error === 'unauthenticated')) {
                showEmpty(
                    j.message || 'Oturum süresi doldu — sayfayı yenileyip tekrar giriş yapın.'
                );
                return;
            }
            // 419 — CSRF token süresi doldu
            if (r.status === 419) {
                showEmpty('Güvenlik tokenı süresi doldu — sayfayı yenileyin.');
                return;
            }
            if (!j || !j.ok) {
                showEmpty(
                    j && j._parse_err
                        ? 'Sunucu geçersiz yanıt verdi. Sayfayı yenileyin.'
                        : 'Bu özellik şu an kapalı veya endpoint ulaşılamıyor.'
                );
                return;
            }
            if (j.seo) renderSeo(j.seo);
            if (j.readability) renderReadability(j.readability);
        } catch (_) {
            if (!ticket.aborted) showEmpty('Bağlantı hatası — internet kontrolü.');
        }
    };

    const schedule = () => {
        clearTimeout(timer);
        timer = setTimeout(tick, DELAY);
    };

    // Initial calc
    setTimeout(tick, 800);

    // Reactive: input event'lerini dinle (contentEditable hem div'de hem textarea'da)
    [titleEl, slugEl, bodyEl, richEditor, excerptEl, metaTitleEl, metaDescEl].forEach(el => {
        if (el) el.addEventListener('input', schedule);
    });
    // Editor blur sırasında da analiz et — kullanıcı yazmayı bitirdi
    if (richEditor) richEditor.addEventListener('blur', schedule);
})();
