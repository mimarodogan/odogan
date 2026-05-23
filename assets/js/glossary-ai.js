// AI Sözlük Taslak Üreteci — admin form sayfası.
// Kullanıcı terim+bağlam+derinlik girer, fetch /admin/sozluk/ai-uret çağrılır,
// dönen JSON ile mevcut form alanları doldurulur (term, kategori, alias,
// definition HTML, references). Ölü kaynak URL'leri sarı rozetle işaretlenir.

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

    const setStatus = (msg, tone) => {
        statusEl.textContent = msg || '';
        statusEl.classList.remove('is-error', 'is-success', 'is-loading');
        if (tone) statusEl.classList.add('is-' + tone);
    };

    const setBusy = (busy) => {
        runBtn.disabled = busy;
        runBtn.classList.toggle('is-loading', busy);
        runBtn.textContent = busy ? 'Araştırılıyor…' : 'Taslak Üret';
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
            // Tek boş satır bırak
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

    // Mod: edit sayfasında "enhance", yeni sayfasında "new".
    // Panel HTML'de data-mode attribute'undan okunur (PHP tarafından set edilir).
    const mode = panel.getAttribute('data-mode') === 'enhance' ? 'enhance' : 'new';

    // Mevcut form değerlerini topla (enhance modu için).
    const currentSnapshot = () => {
        const def = document.getElementById('rich-body');
        const cat = form.querySelector('input[name="category"]');
        const ali = form.querySelector('input[name="aliases"]');
        // References: JSON formatında [{text,url},...]
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

    runBtn.addEventListener('click', async () => {
        const term = ($('#glossary-term') || {}).value || '';
        const context = ($('#glossary-ai-context') || {}).value || '';
        const depthEl = form.querySelector('input[name="ai-depth"]:checked');
        const depth = depthEl ? depthEl.value : 'orta';

        if (term.trim().length < 2) {
            setStatus('Önce terim adını yaz (en az 2 karakter).', 'error');
            return;
        }

        // Enhance modunda yazıyı üzerine yazmadan onay al — kaza önleme.
        const snap = mode === 'enhance' ? currentSnapshot() : null;
        if (mode === 'enhance') {
            const hasAny = (snap.definition.trim() !== '' || snap.category.trim() !== ''
                || snap.aliases.trim() !== '' || snap.references !== '[]');
            if (hasAny) {
                // eslint-disable-next-line no-alert -- native confirm yeterli: basit Y/N
                const ok = window.confirm(
                    'Mevcut tanım, kategori, alias ve kaynaklar AI tarafından GELİŞTİRİLECEK. '
                    + 'Mevcut yapı korunur ama metin değişir. Devam edilsin mi?\n\n'
                    + 'Tavsiye: Şu an formdayken kaybedersen geri alma yok; emin değilsen önce iptal et.'
                );
                if (!ok) return;
            }
        }

        setBusy(true);
        const loadingMsg = mode === 'enhance'
            ? 'Mevcut içerik analiz ediliyor… (≈10-20 sn + kaynak doğrulama)'
            : 'Claude API\'ye gönderiliyor… (≈5-15 sn + kaynak doğrulama)';
        setStatus(loadingMsg, 'loading');

        try {
            const body = new URLSearchParams();
            body.set('_csrf', csrf);
            body.set('term', term.trim());
            body.set('context', context.trim());
            body.set('depth', depth);
            if (snap) {
                body.set('current_definition', snap.definition);
                body.set('current_category', snap.category);
                body.set('current_aliases', snap.aliases);
                body.set('current_references', snap.references);
            }

            const res = await fetch(window.appUrl ? window.appUrl('/admin/sozluk/ai-uret') : '/admin/sozluk/ai-uret', {
                method: 'POST',
                headers: {
                    'content-type': 'application/x-www-form-urlencoded',
                    'accept': 'application/json',
                    'x-requested-with': 'XMLHttpRequest',
                },
                body: body.toString(),
                credentials: 'same-origin',
            });
            const data = await res.json().catch(() => ({ ok: false, message: 'Yanıt JSON değil' }));

            if (!res.ok || !data.ok) {
                setStatus('Hata: ' + (data.message || res.statusText || 'Bilinmeyen'), 'error');
                return;
            }

            const d = data.draft || {};

            // Term: enhance modunda DOKUNMA (kullanıcı zaten girmiş ve URL bağlı).
            // New modda kullanıcı alanı boşsa AI'dan al.
            if (mode === 'new'
                && (d.term || '').trim().length > 1
                && $('#glossary-term')
                && !$('#glossary-term').value.trim()) {
                $('#glossary-term').value = d.term;
            }
            // Kategori
            const catInp = form.querySelector('input[name="category"]');
            if (catInp && d.category) catInp.value = d.category;
            // Eş anlamlılar — virgülle birleştir
            const aliInp = form.querySelector('input[name="aliases"]');
            if (aliInp && Array.isArray(d.aliases)) aliInp.value = d.aliases.join(', ');
            // Tanım (HTML body)
            if (d.definition_html) setBodyHtml(d.definition_html);
            // Kaynaklar
            setReferences(d.references || []);

            const deadCount = (d.references || []).filter(r => r.dead).length;
            const okPrefix = mode === 'enhance' ? 'Geliştirildi' : 'Taslak hazır';
            const msg = deadCount > 0
                ? `${okPrefix} — ${deadCount} kaynak URL'si doğrulanamadı, manuel kontrol et.`
                : `${okPrefix}. İncele, düzenle, kaydet.`;
            setStatus(msg, 'success');

            // Görsel olarak forma kaydır
            const def = document.getElementById('rich-body');
            if (def) def.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (e) {
            setStatus('Bağlantı hatası: ' + (e && e.message ? e.message : 'bilinmeyen'), 'error');
        } finally {
            setBusy(false);
        }
    });
})();
