// Yazı Analizi — yazarken canlı, debounced birleşik içerik notu.
// Tek halka (A–F) + eksen bölümleri + öncelikli aksiyonlar.
// Opsiyonel "AI Derin Analiz" butonu (talep-üzerine).

(function () {
    'use strict';

    const container = document.querySelector('[data-analyze-container]');
    if (!container) return;

    const url = container.getAttribute('data-analyze-url');
    const csrf = container.getAttribute('data-analyze-csrf');
    const box = container.querySelector('[data-analyze-box]');
    if (!url || !csrf || !box) return;

    const $ = sel => document.querySelector(sel);
    const titleEl = $('input[name="title"]');
    const slugEl = $('input[name="slug"]');
    const bodyEl = document.getElementById('rich-body');
    const richEditor = document.querySelector('.wy-editor');
    const excerptEl = $('textarea[name="excerpt"]');
    const metaTitleEl = $('input[name="meta_title"]');
    const metaDescEl = $('textarea[name="meta_description"]');
    const focusEl = $('input[name="focus_keyword"]');
    const secEl = $('input[name="secondary_keywords"]');
    const catEl = $('select[name="category_id"]');
    const tagsEl = $('input[name="tags"]');

    if (!bodyEl && !richEditor) return;

    const getBody = () => {
        if (richEditor && richEditor.innerHTML) return richEditor.innerHTML;
        if (bodyEl && bodyEl.value) return bodyEl.value;
        return '';
    };
    const val = el => (el ? el.value : '');

    let lastFp = '';
    let pending = null;
    let timer = null;
    const DELAY = 1500;

    const fingerprint = () =>
        [val(titleEl), val(slugEl), getBody().length, val(excerptEl).length,
         val(metaTitleEl), val(metaDescEl), val(focusEl), val(secEl), val(catEl),
         val(tagsEl), collectExtra().length].join('|');

    // Ayrı bölümlerdeki içeriği (SSS + kaynaklar) analiz gövdesine ekle — yoksa
    // analiz/AI bu bölümleri görmez ve "eksik" sanır.
    const collectExtra = () => {
        let extra = '';
        document.querySelectorAll('#faq-list .faq-row').forEach(row => {
            const q = (row.querySelector('[name$="[q]"]') || {}).value || '';
            const a = (row.querySelector('[name$="[a]"]') || {}).value || '';
            if (q.trim() || a.trim()) extra += '<h2>' + q + '</h2><p>' + a + '</p>';
        });
        let fns = '';
        document.querySelectorAll('[data-footnotes] [data-fn-row]').forEach(row => {
            const t = (row.querySelector('[name$="[text]"]') || {}).value || '';
            const u = (row.querySelector('[name$="[url]"]') || {}).value || '';
            if (t.trim() || u.trim()) fns += '<li>' + t + (u ? ' ' + u : '') + '</li>';
        });
        if (fns) extra += '<h2>Kaynaklar</h2><ul>' + fns + '</ul>';
        return extra;
    };

    const payload = () => {
        const fd = new FormData();
        fd.append('_csrf', csrf);
        fd.append('title', val(titleEl));
        fd.append('slug', val(slugEl));
        fd.append('body', getBody() + collectExtra());
        fd.append('body_format', 'html');
        fd.append('excerpt', val(excerptEl));
        fd.append('meta_title', val(metaTitleEl));
        fd.append('meta_description', val(metaDescEl));
        fd.append('focus_keyword', val(focusEl));
        fd.append('secondary_keywords', val(secEl));
        fd.append('category_id', val(catEl));
        fd.append('tags', val(tagsEl));
        return fd;
    };

    const escapeHtml = s =>
        String(s).replace(/[&<>"']/g, c =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    const colorFor = pct => (pct >= 75 ? '#2F6A3E' : pct >= 50 ? '#8C6A12' : '#B0241D');

    const renderPart = p => {
        const cls = p.ok ? 'ca-ok' : 'ca-warn';
        const icon = p.ok ? '✓' : '!';
        return `<li class="${cls}"><span class="mark">${icon}</span>` +
            `<span class="lbl">${escapeHtml(p.name)}</span>` +
            `<span class="pts">${p.score}/${p.max}</span>` +
            `<div class="tip">${escapeHtml(p.tip)}</div></li>`;
    };

    const renderSection = s => {
        const open = s.pct < 75 ? ' open' : '';
        const col = colorFor(s.pct);
        return `<details class="ca-sec"${open}><summary>` +
            `<span class="ca-sec-lbl">${escapeHtml(s.label)}</span>` +
            `<span class="ca-sec-bar"><i style="width:${s.pct}%;background:${col}"></i></span>` +
            `<span class="ca-sec-pts">${s.score}/${s.max}</span>` +
            `</summary><ul class="ca-parts">${(s.parts || []).map(renderPart).join('')}</ul></details>`;
    };

    const render = a => {
        if (!a) return;
        const col = colorFor(a.score);
        const actions = (a.actions || []).length
            ? `<div class="ca-actions"><h4>Önce şunları düzelt</h4><ol>` +
              a.actions.map(t => `<li>${escapeHtml(t)}</li>`).join('') + `</ol></div>`
            : '';
        box.innerHTML =
            `<div class="ca-head">` +
                `<div class="ca-ring" style="--ca-pct:${a.score};--ca-col:${col}">` +
                    `<span class="ca-grade" style="color:${col}">${escapeHtml(a.grade)}</span>` +
                    `<span class="ca-score">${a.score}<small>/100</small></span>` +
                `</div>${actions}` +
            `</div>` +
            `<div class="ca-sections">${(a.sections || []).map(renderSection).join('')}</div>`;
    };

    const showMsg = msg => { box.innerHTML = `<p class="muted" style="font-size:.85rem">${escapeHtml(msg)}</p>`; };

    const tick = async () => {
        if (getBody().trim().length < 50 && val(titleEl).trim().length < 3) {
            showMsg('Yazmaya başlayın — analiz otomatik güncellenecek.');
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
            if (ticket.aborted) return;
            if (r.status === 401) return showMsg('Oturum süresi doldu — sayfayı yenileyin.');
            if (r.status === 419) return showMsg('Güvenlik tokenı süresi doldu — sayfayı yenileyin.');
            const j = await r.json().catch(() => null);
            if (r.status >= 500) return showMsg('Sunucu hatası — admin Loglar (editorial) kanalını kontrol edin.');
            if (!j || !j.ok || !j.analysis) return showMsg('Analiz şu an kullanılamıyor.');
            render(j.analysis);
        } catch (_) {
            if (!ticket.aborted) showMsg('Bağlantı hatası — internet kontrolü.');
        }
    };

    const schedule = () => { clearTimeout(timer); timer = setTimeout(tick, DELAY); };

    setTimeout(tick, 800);
    [titleEl, slugEl, bodyEl, richEditor, excerptEl, metaTitleEl, metaDescEl, focusEl, secEl, catEl, tagsEl]
        .forEach(el => { if (el) el.addEventListener('input', schedule); });
    if (catEl) catEl.addEventListener('change', schedule);
    if (richEditor) richEditor.addEventListener('blur', schedule);

    // ─── Opsiyonel AI Derin Analiz ───────────────────────────────
    const aiBtn = container.querySelector('[data-ai-btn]');
    const aiBox = container.querySelector('[data-ai-box]');
    const aiUrl = container.getAttribute('data-ai-url');
    if (aiBtn && aiBox && aiUrl) {
        const renderAi = ai => {
            let h = '';
            if (ai.verdict) h += `<p class="ca-ai-verdict">${escapeHtml(ai.verdict)}</p>`;
            if (ai.intent && (ai.intent.match || ai.intent.note))
                h += `<p class="ca-ai-row"><strong>Arama niyeti:</strong> ${escapeHtml(ai.intent.match || '')} — ${escapeHtml(ai.intent.note || '')}</p>`;
            if (Array.isArray(ai.gaps) && ai.gaps.length)
                h += `<div class="ca-ai-block"><strong>Eksik alt-konular:</strong><ul>` +
                     ai.gaps.map(g => `<li>${escapeHtml(g)}</li>`).join('') + `</ul></div>`;
            const sg = ai.suggestions || {};
            if (sg.tldr) h += `<div class="ca-ai-block"><strong>Önerilen TL;DR:</strong><p>${escapeHtml(sg.tldr)}</p></div>`;
            if (Array.isArray(sg.title) && sg.title.length)
                h += `<div class="ca-ai-block"><strong>Başlık önerileri:</strong><ul>` +
                     sg.title.map(t => `<li>${escapeHtml(t)}</li>`).join('') + `</ul></div>`;
            if (sg.meta) h += `<div class="ca-ai-block"><strong>Meta açıklama:</strong><p>${escapeHtml(sg.meta)}</p></div>`;
            if (Array.isArray(sg.faq) && sg.faq.length)
                h += `<div class="ca-ai-block"><strong>SSS önerileri:</strong><ul>` +
                     sg.faq.map(f => `<li><em>${escapeHtml(f.q || '')}</em> — ${escapeHtml(f.a || '')}</li>`).join('') + `</ul></div>`;
            aiBox.innerHTML = h || '<p class="muted">AI bir öneri döndürmedi.</p>';
        };
        aiBtn.addEventListener('click', async () => {
            aiBtn.disabled = true;
            const orig = aiBtn.textContent;
            aiBtn.textContent = '⏳ İnceleniyor…';
            aiBox.hidden = false;
            aiBox.innerHTML = '<p class="muted" style="font-size:.85rem">AI yazıyı inceliyor… (birkaç saniye)</p>';
            try {
                const r = await fetch(aiUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrf, Accept: 'application/json' },
                    credentials: 'same-origin',
                    body: payload(),
                });
                const j = await r.json().catch(() => null);
                if (j && j.ok && j.ai) renderAi(j.ai);
                else aiBox.innerHTML = `<p class="ca-err">${escapeHtml((j && j.message) || 'AI analizi başarısız.')}</p>`;
            } catch (_) {
                aiBox.innerHTML = '<p class="ca-err">Bağlantı hatası.</p>';
            } finally {
                aiBtn.disabled = false;
                aiBtn.textContent = orig;
            }
        });
    }
})();
