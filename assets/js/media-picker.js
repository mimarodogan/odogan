// Media library picker modal — opened by editor.js (or any script via
// window.openMediaPickerImpl(onPick)). Loads images from
// /panel/medya/picker.json and lets the user click one to insert.
(function () {
    'use strict';

    let modal = null;
    let currentCb = null;

    const escAttr = s => String(s).replace(/"/g, '&quot;').replace(/</g, '&lt;');
    const escHtml = s =>
        String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

    const getCsrf = () => {
        const i = document.querySelector('input[name="_csrf"]');
        return i ? i.value : '';
    };

    const setStatus = msg => {
        modal.querySelector('.mp-status').textContent = msg || '';
    };

    const buildTileHtml = it => {
        const { thumb, alt, name, width, height, url, path } = it;
        return (
            `<img src="${thumb}" alt="${escAttr(alt || name)}" loading="lazy"` +
            ` onerror="this.style.background='#fee';this.title='404: '+this.src;this.alt='YOK: '+this.src.split('/').pop();">` +
            `<figcaption>${escHtml(name)} <small>${width}×${height}</small><br>` +
            `<a href="${url}" target="_blank" style="font-family:var(--mono);font-size:.6rem">⤴ ${path}</a>` +
            `</figcaption>`
        );
    };

    const load = async q => {
        setStatus('Yükleniyor…');
        const url = `/panel/medya/picker.json${q ? `?q=${encodeURIComponent(q)}` : ''}`;
        try {
            const r = await fetch(url, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const j = await r.json();
            const grid = modal.querySelector('.mp-grid');
            grid.innerHTML = '';
            (j.items || []).forEach(it => {
                const fig = document.createElement('figure');
                fig.className = 'mp-tile';
                fig.innerHTML = buildTileHtml(it);
                fig.addEventListener('click', () => {
                    if (currentCb) currentCb(it);
                    close();
                });
                grid.appendChild(fig);
            });
            setStatus(`${(j.items || []).length} görsel`);
        } catch (e) {
            setStatus(`Hata: ${e.message}`);
        }
    };

    const uploadFile = async f => {
        const fd = new FormData();
        fd.append('_csrf', getCsrf());
        fd.append('image', f);
        fd.append('alt', f.name.replace(/\.[^.]+$/, ''));
        setStatus(`Yükleniyor: ${f.name}`);
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
            } catch (_) {
                j = {
                    ok: false,
                    error: `HTTP ${r.status} — Sunucu JSON döndürmedi: ${t.substring(0, 240)}`,
                };
            }
            if (j && j.ok) {
                load('');
            } else {
                setStatus(`Hata: ${j && j.error ? j.error : 'bilinmeyen'}`);
            }
        } catch (e) {
            setStatus(`Ağ hatası: ${e.message}`);
        }
    };

    const uploadFiles = files => {
        if (!files || !files.length) return;
        Array.from(files).forEach(f => uploadFile(f));
    };

    const build = () => {
        modal = document.createElement('div');
        modal.className = 'media-modal';
        modal.innerHTML =
            '<div class="media-modal-inner">' +
            '<header>' +
            '<h2>Galeriden Görsel Seç</h2>' +
            '<input type="search" class="mp-search" placeholder="ara: dosya adı veya alt metin">' +
            '<button type="button" class="btn mp-upload">+ Yeni yükle</button>' +
            '<button type="button" class="btn mp-close" aria-label="Kapat">×</button>' +
            '</header>' +
            '<div class="mp-grid"></div>' +
            '<footer><span class="muted mp-status"></span></footer>' +
            '<input type="file" class="mp-file" accept="image/*" hidden>' +
            '</div>';
        document.body.appendChild(modal);
        modal.addEventListener('click', ev => {
            if (ev.target === modal) close();
        });
        modal.querySelector('.mp-close').addEventListener('click', close);

        const search = modal.querySelector('.mp-search');
        let debounce;
        search.addEventListener('input', () => {
            clearTimeout(debounce);
            debounce = setTimeout(() => load(search.value), 250);
        });

        const upBtn = modal.querySelector('.mp-upload');
        const fileInput = modal.querySelector('.mp-file');
        upBtn.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', ev => {
            uploadFiles(ev.target.files);
            fileInput.value = '';
        });
    };

    const open = cb => {
        if (!modal) build();
        currentCb = cb;
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
        load('');
    };

    const close = () => {
        if (modal) modal.classList.remove('open');
        document.body.style.overflow = '';
        currentCb = null;
    };

    window.openMediaPickerImpl = open;
})();
