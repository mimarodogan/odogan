// AI Sözlük Taslak Üreteci — admin form sayfası.
// PARÇALI ÜRETİM (5 chunk): max_tokens hatalarından kalıcı kurtuluş.
// Her chunk ~3K token output → Haiku 4.5'in 8192 tavanına rahat sığar.
// Bölümler form alanlarına canlı eklenir; kullanıcı progress'i görür.

(function () {
    'use strict';

    const panel = document.getElementById('glossary-ai-panel');
    const runBtn = document.getElementById('glossary-ai-run');
    const statusEl = document.getElementById('glossary-ai-status');
    if (!panel || !runBtn || !statusEl) return;

    const form = document.getElementById('glossary-form');
    if (!form) return;
    const csrfInput = form.querySelector('input[name="_csrf"]');
    const csrf = csrfInput ? csrfInput.value : '';

    const $ = (sel, root) => (root || document).querySelector(sel);

    // PARÇA PLANI — server tarafıyla aynı (CHUNK_PLAN). Buradaki etiketler
    // sadece progress UI içindir; gerçek üretim sunucu tarafında.
    const CHUNKS = [
        { id: 'chunk_1', label: 'Tanım & Köken' },
        { id: 'chunk_2', label: 'Tarih & Kullanım' },
        { id: 'chunk_3', label: 'Türler & Tasarım' },
        { id: 'chunk_4', label: 'Karşılaştırma & Örnekler' },
        { id: 'chunk_5', label: 'Yerel + Eleştirel + Kaynaklar' },
    ];

    const setStatus = (msg, tone) => {
        statusEl.textContent = msg || '';
        statusEl.classList.remove('is-error', 'is-success', 'is-loading');
        if (tone) statusEl.classList.add('is-' + tone);
    };

    // Mod: edit sayfasında "enhance", yeni sayfasında "new".
    const mode = panel.getAttribute('data-mode') === 'enhance' ? 'enhance' : 'new';
    const defaultBtnText = mode === 'enhance' ? 'Mevcut Girdiyi Geliştir' : 'Taslak Üret';

    const setBusy = (busy, label) => {
        runBtn.disabled = busy;
        runBtn.classList.toggle('is-loading', busy);
        runBtn.textContent = busy ? (label || 'Üretiliyor…') : defaultBtnText;
    };

    // Mevcut WYSIWYG editorüne HTML inject — `editor.js` `.wy-editor`'u init eder
    const setBodyHtml = (html) => {
        const ta = document.getElementById('rich-body');
        if (!ta) return;
        ta.value = html || '';
        const wy = document.querySelector('.wysiwyg .wy-editor');
        if (wy) {
            wy.innerHTML = html || '';
            wy.dispatchEvent(new Event('input', { bubbles: true }));
        }
    };

    // Referans satırlarını formdaki `#references-list` repeater'ına yaz
    const setReferences = (refs) => {
        const list = document.getElementById('references-list');
        if (!list) return;
        list.innerHTML = '';
        (refs || []).forEach((ref, idx) => {
            const row = document.createElement('div');
            row.className = 'faq-row reference-row';
            row.setAttribute('data-ref-row', '');
            if (ref.dead) row.classList.add('reference-row-dead');
            row.innerHTML =
                `<input type="text" name="references[${idx}][text]"` +
                ` placeholder="Kaynak metni" maxlength="2000"` +
                ` value="${escAttr(ref.text || '')}">` +
                `<input type="url" name="references[${idx}][url]"` +
                ` placeholder="https://..." maxlength="500"` +
                ` value="${escAttr(ref.url || '')}">` +
                `<button type="button" class="btn btn-ghost reference-remove">Sil</button>` +
                (ref.dead
                    ? `<small class="reference-dead-warn" role="alert">⚠ Bu URL doğrulanamadı — manuel kontrol et.</small>`
                    : '');
            list.appendChild(row);
        });
        if ((refs || []).length === 0) {
            const row = document.createElement('div');
            row.className = 'faq-row reference-row';
            row.setAttribute('data-ref-row', '');
            row.innerHTML =
                `<input type="text" name="references[0][text]" placeholder="Kaynak metni" maxlength="2000" value="">` +
                `<input type="url" name="references[0][url]" placeholder="https://..." maxlength="500" value="">` +
                `<button type="button" class="btn btn-ghost reference-remove">Sil</button>`;
            list.appendChild(row);
        }
    };

    const escAttr = (s) =>
        String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

    // Mevcut form değerlerini topla (enhance modu için).
    const currentSnapshot = () => {
        const def = document.getElementById('rich-body');
        const cat = form.querySelector('input[name="category"]');
        const ali = form.querySelector('input[name="aliases"]');
        const refs = [];
        form.querySelectorAll('[data-ref-row]').forEach((row) => {
            const t = row.querySelector('input[name^="references"][name$="[text]"]');
            const u = row.querySelector('input[name^="references"][name$="[url]"]');
            const tv = t ? (t.value || '').trim() : '';
            const uv = u ? (u.value || '').trim() : '';
            if (tv || uv) refs.push({ text: tv, url: uv });
        });
        return {
            definition: def ? (def.value || '') : '',
            category: cat ? (cat.value || '') : '',
            aliases: ali ? (ali.value || '') : '',
            references: JSON.stringify(refs),
        };
    };

    // Tek bir chunk için API çağrısı
    const fetchChunk = async (chunkId, term, context, depth, snap) => {
        const body = new URLSearchParams();
        body.set('_csrf', csrf);
        body.set('term', term);
        body.set('context', context);
        body.set('depth', depth);
        body.set('chunk', chunkId);
        if (snap) {
            body.set('current_definition', snap.definition);
            body.set('current_category', snap.category);
            body.set('current_aliases', snap.aliases);
            body.set('current_references', snap.references);
        }

        const res = await fetch(
            window.appUrl ? window.appUrl('/admin/sozluk/ai-uret') : '/admin/sozluk/ai-uret',
            {
                method: 'POST',
                headers: {
                    'content-type': 'application/x-www-form-urlencoded',
                    'accept': 'application/json',
                    'x-requested-with': 'XMLHttpRequest',
                },
                body: body.toString(),
                credentials: 'same-origin',
            }
        );

        let data;
        try {
            data = await res.json();
        } catch {
            const txt = await res.text().catch(() => '');
            throw new Error('Yanıt JSON değil. HTTP ' + res.status + '. Başı: ' + (txt || '').slice(0, 200));
        }

        if (!res.ok || !data.ok) {
            throw new Error(data.message || res.statusText || 'Bilinmeyen hata');
        }
        return data.data || {};
    };

    runBtn.addEventListener('click', async () => {
        const term = ($('#glossary-term') || {}).value || '';
        const context = ($('#glossary-ai-context') || {}).value || '';
        const depthEl = form.querySelector('input[name="ai-depth"]:checked');
        const depth = depthEl ? depthEl.value : 'orta';

        if (term.trim().length < 2) {
            setStatus('Önce terim adını yaz (en az 2 karakter).', 'error');
            return;
        }

        const snap = mode === 'enhance' ? currentSnapshot() : null;
        if (mode === 'enhance' && snap) {
            const hasAny = (snap.definition.trim() !== '' || snap.category.trim() !== ''
                || snap.aliases.trim() !== '' || snap.references !== '[]');
            if (hasAny) {
                // eslint-disable-next-line no-alert -- native confirm yeterli: basit Y/N
                const ok = window.confirm(
                    'Mevcut tanım, kategori, alias ve kaynaklar AI tarafından GELİŞTİRİLECEK. '
                    + 'Mevcut yapı korunur ama metin değişir. Devam edilsin mi?\n\n'
                    + 'Sistem 5 ardışık API çağrısıyla bölüm bölüm üretir, ilerleme aşağıda görünür. '
                    + 'Toplam süre 30-90 sn arası.'
                );
                if (!ok) return;
            }
        }

        setBusy(true, 'Üretiliyor (1/5)…');
        let combinedHtml = '';
        let success = 0;
        const failed = [];
        let deadRefs = 0;

        // Her chunk için ardışık çağrı — biri patlarsa devam et
        for (let i = 0; i < CHUNKS.length; i++) {
            const chunk = CHUNKS[i];
            setBusy(true, `Üretiliyor (${i + 1}/${CHUNKS.length})…`);
            setStatus(`Bölüm ${i + 1}/${CHUNKS.length}: ${chunk.label} üretiliyor…`, 'loading');

            try {
                const data = await fetchChunk(chunk.id, term.trim(), context.trim(), depth, snap);

                // Chunk 1: term + category + aliases
                if (chunk.id === 'chunk_1') {
                    if (mode === 'new'
                        && (data.term || '').trim().length > 1
                        && $('#glossary-term')
                        && !$('#glossary-term').value.trim()) {
                        $('#glossary-term').value = data.term;
                    }
                    const catInp = form.querySelector('input[name="category"]');
                    if (catInp && data.category) catInp.value = data.category;
                    const aliInp = form.querySelector('input[name="aliases"]');
                    if (aliInp && Array.isArray(data.aliases)) {
                        aliInp.value = data.aliases.join(', ');
                    }
                }

                // HTML birleştir + canlı güncelle
                if (data.html) {
                    combinedHtml += (combinedHtml ? '\n' : '') + data.html;
                    setBodyHtml(combinedHtml);
                }

                // Chunk 5: references
                if (chunk.id === 'chunk_5' && Array.isArray(data.references)) {
                    setReferences(data.references);
                    deadRefs = data.references.filter(r => r && r.dead).length;
                }

                success++;
            } catch (err) {
                failed.push({ chunk: chunk.id, label: chunk.label, msg: err.message });
                // Devam et — diğer chunk'lar etkilenmesin
            }
        }

        // Final durum mesajı
        if (success === CHUNKS.length) {
            const okPrefix = mode === 'enhance' ? 'Geliştirildi' : 'Taslak hazır';
            const refMsg = deadRefs > 0
                ? ` — ${deadRefs} kaynak URL'si doğrulanamadı (sarı rozet).`
                : '';
            setStatus(`✓ ${okPrefix}: 5/5 bölüm tamam${refMsg} İncele, düzenle, kaydet.`, 'success');
        } else if (success > 0) {
            const fl = failed.map(f => `${f.chunk}: ${f.msg}`).join(' | ');
            setStatus(
                `Kısmi başarı: ${success}/${CHUNKS.length} bölüm üretildi. Başarısız: ${fl}`,
                'error'
            );
        } else {
            setStatus(
                `Tüm bölümler başarısız: ${failed.map(f => f.msg).join(' | ')}`,
                'error'
            );
        }

        setBusy(false);

        // Forma kaydır
        const defEl = document.getElementById('rich-body');
        if (defEl && success > 0) defEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
})();
