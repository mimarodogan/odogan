// Inline image insertion for the markdown editor.
// Adds a button + drag-drop on the body textarea. Uploads via the existing
// /panel/medya/yukle JSON endpoint and inserts ![](path) markdown at caret.
(function () {
    'use strict';

    const ta = document.getElementById('md-body');
    if (!ta) return;

    const csrf = () => {
        const i = document.querySelector('input[name="_csrf"]');
        return i ? i.value : '';
    };

    const insertAtCaret = text => {
        const start = ta.selectionStart;
        const end = ta.selectionEnd;
        ta.value = ta.value.substring(0, start) + text + ta.value.substring(end);
        ta.selectionStart = ta.selectionEnd = start + text.length;
        ta.focus();
        ta.dispatchEvent(new Event('input'));
    };

    const uploadOne = async file => {
        const fd = new FormData();
        fd.append('_csrf', csrf());
        fd.append('image', file);
        fd.append('alt', file.name.replace(/\.[^.]+$/, ''));
        const placeholder = `\n![Yükleniyor: ${file.name}]()\n`;
        insertAtCaret(placeholder);
        try {
            const r = await fetch('/panel/medya/yukle', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const t = await r.text();
            let j;
            try {
                j = JSON.parse(t);
            } catch (e) {
                j = {
                    ok: false,
                    error: `HTTP ${r.status} — ${t.substring(0, 240)}`,
                };
            }
            if (!j || !j.ok) {
                ta.value = ta.value.replace(
                    placeholder,
                    `\n[Yükleme başarısız: ${j && j.error ? j.error : 'bilinmeyen'}]\n`
                );
                return;
            }
            const alt = file.name.replace(/\.[^.]+$/, '');
            const md = `\n![${alt}](/${j.path})\n`;
            ta.value = ta.value.replace(placeholder, md);
            ta.dispatchEvent(new Event('input'));
        } catch (e) {
            ta.value = ta.value.replace(placeholder, `\n[Yükleme hatası: ${e.message}]\n`);
        }
    };

    const uploadFiles = files => {
        Array.prototype.forEach.call(files, f => {
            if (/^image\//.test(f.type)) uploadOne(f);
        });
    };

    // Toolbar button
    const bar = document.createElement('div');
    bar.className = 'md-toolbar';
    bar.innerHTML =
        '<button type="button" class="btn" id="md-add-image">📷 Resim ekle</button>' +
        '<input type="file" id="md-file-input" accept="image/*" multiple hidden>' +
        '<small class="muted">veya görseli yazı alanına sürükle</small>';
    ta.parentNode.insertBefore(bar, ta);

    document.getElementById('md-add-image').addEventListener('click', () => {
        document.getElementById('md-file-input').click();
    });
    document.getElementById('md-file-input').addEventListener('change', ev => {
        uploadFiles(ev.target.files);
        ev.target.value = '';
    });

    // Drag & drop on textarea
    ['dragenter', 'dragover'].forEach(e => {
        ta.addEventListener(e, ev => {
            ev.preventDefault();
            ta.classList.add('drag-over');
        });
    });
    ['dragleave', 'drop'].forEach(e => {
        ta.addEventListener(e, ev => {
            ev.preventDefault();
            ta.classList.remove('drag-over');
        });
    });
    ta.addEventListener('drop', ev => {
        if (ev.dataTransfer && ev.dataTransfer.files) uploadFiles(ev.dataTransfer.files);
    });

    // Paste images
    ta.addEventListener('paste', ev => {
        const items = ev.clipboardData && ev.clipboardData.items;
        if (!items) return;
        for (let i = 0; i < items.length; i++) {
            if (items[i].type && items[i].type.indexOf('image') === 0) {
                const f = items[i].getAsFile();
                if (f) uploadOne(f);
            }
        }
    });
})();
