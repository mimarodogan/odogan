// internal-link-suggest.js — Tier 5 feature 4.5
// Editör body'sini izle, 5sn debounced POST → öneriler kartlarını sidebar'a render.
// Bir karta tıklanınca editor'e <a href> insert (mevcut seçili text varsa link'ler).
(function () {
    'use strict';

    const container = document.querySelector('[data-suggest-container]');
    if (!container) return;

    const url = container.getAttribute('data-suggest-url') || '';
    const csrf = container.getAttribute('data-suggest-csrf') || '';
    const postId = parseInt(container.getAttribute('data-suggest-post-id') || '0', 10);
    const listEl = container.querySelector('[data-suggest-list]');
    if (!url || !listEl) return;

    const editor = document.querySelector('.wy-editor');
    const bodyInput = document.querySelector('textarea[name="body"], input[name="body"]');

    const currentBody = () => {
        if (editor && editor.innerHTML) return editor.innerHTML;
        if (bodyInput && bodyInput.value) return bodyInput.value;
        return '';
    };

    const DEBOUNCE_MS = 5000;
    let timer = null;
    let lastBody = '';
    let inFlight = null;

    const debounceFetch = () => {
        clearTimeout(timer);
        timer = setTimeout(fetchSuggestions, DEBOUNCE_MS);
    };

    const fetchSuggestions = async () => {
        const body = currentBody();
        if (!body || body.length < 80) {
            listEl.innerHTML =
                '<p class="muted" style="font-size:.82rem">Yazmaya başlayın (en az 80 karakter) — öneriler burada çıkacak.</p>';
            return;
        }
        if (body === lastBody) return;
        lastBody = body;
        if (inFlight && typeof inFlight.abort === 'function') {
            try {
                inFlight.abort();
            } catch (e) {}
        }

        // Yükleniyor göstergesi
        listEl.innerHTML = '<p class="muted" style="font-size:.82rem">Öneriler hesaplanıyor…</p>';

        const fd = new FormData();
        fd.append('body', body);
        fd.append('post_id', String(postId));
        fd.append('_token', csrf);

        const ctrl = typeof AbortController === 'function' ? new AbortController() : null;
        inFlight = ctrl;

        try {
            const r = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: fd,
                credentials: 'same-origin',
                signal: ctrl ? ctrl.signal : undefined,
            });
            let data;
            try {
                data = await r.json();
            } catch {
                data = { ok: false, _parse_err: true };
            }
            // 401 — session timeout
            if (r.status === 401 || (data && data.error === 'unauthenticated')) {
                listEl.innerHTML = `<p class="muted" style="font-size:.82rem">${
                    data.message || 'Oturum süresi doldu — sayfayı yenileyip tekrar giriş yapın.'
                }</p>`;
                return;
            }
            // 419 — CSRF token süresi doldu
            if (r.status === 419) {
                listEl.innerHTML =
                    '<p class="muted" style="font-size:.82rem">Güvenlik tokenı süresi doldu — sayfayı yenileyin.</p>';
                return;
            }
            if (!data || !data.ok) {
                let msg;
                if (data && data._parse_err) {
                    msg = 'Sunucu geçersiz yanıt verdi.';
                } else if (data && data.error === 'disabled') {
                    msg =
                        'Bu özellik kapalı. Admin → Ayarlar → Özellikler → "Internal Link Önerisi (editörde)" açın.';
                } else {
                    msg = 'Endpoint ulaşılamıyor.';
                }
                listEl.innerHTML = `<p class="muted" style="font-size:.82rem">${msg}</p>`;
                return;
            }
            renderList(data.suggestions || []);
        } catch (e) {
            if (e && e.name === 'AbortError') return;
            listEl.innerHTML = '<p class="muted" style="font-size:.82rem">Bağlantı hatası.</p>';
        }
    };

    const esc = s =>
        String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');

    const renderList = items => {
        if (!items.length) {
            listEl.innerHTML =
                '<p class="muted" style="font-size:.82rem">Henüz alakalı yazı bulunamadı.</p>';
            return;
        }
        let html = '<ul class="pe-suggest-list">';
        for (let i = 0; i < items.length; i++) {
            const { url: sUrl, category, title } = items[i];
            html +=
                `<li class="pe-suggest-item">` +
                `<a class="pe-suggest-link" href="${esc(sUrl)}" target="_blank" rel="noopener" title="Yazıyı yeni sekmede aç">` +
                `<span class="pe-suggest-cat">${esc(category)}</span>` +
                `<span class="pe-suggest-title">${esc(title)}</span>` +
                `</a>` +
                `<button type="button" class="pe-suggest-insert" ` +
                `data-suggest-url="${esc(sUrl)}" ` +
                `data-suggest-title="${esc(title)}" ` +
                `title="Editöre link olarak ekle">+ ekle</button>` +
                `</li>`;
        }
        html += '</ul>';
        listEl.innerHTML = html;
    };

    // Click-to-insert
    listEl.addEventListener('click', e => {
        const btn = e.target.closest('[data-suggest-url]');
        if (!btn) return;
        if (!btn.matches('.pe-suggest-insert')) return;
        e.preventDefault();
        const linkUrl = btn.getAttribute('data-suggest-url');
        const linkTitle = btn.getAttribute('data-suggest-title');
        if (!editor) return;
        editor.focus();
        const sel = window.getSelection();
        const range = sel && sel.rangeCount ? sel.getRangeAt(0) : null;

        if (range && !range.collapsed && editor.contains(range.commonAncestorContainer)) {
            // Seçili text varsa link'le
            try {
                document.execCommand('createLink', false, linkUrl);
            } catch (err) {}
        } else {
            // Boş seçim → editörün sonuna <a> insert
            try {
                document.execCommand(
                    'insertHTML',
                    false,
                    `<a href="${esc(linkUrl)}">${esc(linkTitle)}</a> `
                );
            } catch (err) {}
        }
        // Visual feedback
        btn.textContent = 'eklendi';
        btn.disabled = true;
        setTimeout(() => {
            btn.textContent = '+ ekle';
            btn.disabled = false;
        }, 1500);
    });

    // Input olayları
    if (editor) {
        editor.addEventListener('input', debounceFetch);
        editor.addEventListener('blur', debounceFetch);
    }
    if (bodyInput) {
        bodyInput.addEventListener('input', debounceFetch);
        bodyInput.addEventListener('blur', debounceFetch);
    }

    // İlk yüklemede mevcut body varsa fetch et
    setTimeout(() => {
        if (currentBody().length >= 80) fetchSuggestions();
    }, 1500);
})();
