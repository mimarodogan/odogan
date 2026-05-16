/* ════════════════════════════════════════════════════════════════════
 * Media Input — admin form'larda görsel seçici alan
 *
 * Single mode (.media-input): tek URL alanı + preview
 *   - "Kütüphaneden Seç" → window.openMediaPickerImpl(cb) açar
 *   - "Bilgisayardan Yükle" → file input → /panel/medya/yukle → URL yazılır
 *   - "×" → input'u temizler, preview kapanır
 *
 * List mode (.media-input-list): textarea + thumbnail strip + tile remove
 *   - Pick / Upload eklenen item textarea'ya yeni satır olarak eklenir
 *   - Thumbnail strip'te kart çıkar, "×" ile kaldırılır
 *   - Drag-drop ile sıralanır (HTML5 native)
 * ════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    const init = () => {
        document.querySelectorAll('[data-mi]').forEach(setup);
    };

    const setup = el => {
        if (el.dataset.miReady === '1') return;
        el.dataset.miReady = '1';

        const mode = el.getAttribute('data-mi-mode') || 'single';
        const pickBtn = el.querySelector('[data-mi-pick]');
        const upBtn = el.querySelector('[data-mi-upload]');

        if (pickBtn) {
            pickBtn.addEventListener('click', () => {
                if (typeof window.openMediaPickerImpl !== 'function') {
                    alert('Medya picker yüklü değil. Sayfayı yenileyip yeniden deneyin.');
                    return;
                }
                window.openMediaPickerImpl(item => {
                    if (mode === 'list') {
                        addToList(el, item);
                    } else {
                        setSingle(el, item);
                    }
                });
            });
        }

        if (upBtn) {
            upBtn.addEventListener('click', () => {
                triggerUpload(item => {
                    if (mode === 'list') {
                        addToList(el, item);
                    } else {
                        setSingle(el, item);
                    }
                });
            });
        }

        if (mode === 'single') {
            setupSingle(el);
        } else {
            setupList(el);
        }
    };

    /* ───── SINGLE MODE ───── */

    const setupSingle = el => {
        const clearBtn = el.querySelector('[data-mi-clear]');
        const urlInput = el.querySelector('[data-mi-url]');
        const preview = el.querySelector('[data-mi-preview]');
        const thumb = el.querySelector('[data-mi-thumb]');

        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                urlInput.value = '';
                if (preview) preview.hidden = true;
                if (thumb) {
                    thumb.src = '';
                    thumb.hidden = true;
                }
                urlInput.focus();
            });
        }

        // Manuel URL girilirse preview canlı güncelle
        if (urlInput) {
            urlInput.addEventListener('input', () => {
                const v = urlInput.value.trim();
                if (v && /^https?:\/\//i.test(v)) {
                    if (thumb) {
                        thumb.src = v;
                        thumb.hidden = false;
                    }
                    if (preview) preview.hidden = false;
                } else {
                    if (preview) preview.hidden = true;
                }
            });
        }
    };

    const setSingle = (el, item) => {
        const urlInput = el.querySelector('[data-mi-url]');
        const preview = el.querySelector('[data-mi-preview]');
        const thumb = el.querySelector('[data-mi-thumb]');
        if (!urlInput) return;

        // item: { url, path, thumb, name, width, height, alt }
        const value = item.url || item.path || '';
        urlInput.value = value;
        urlInput.dispatchEvent(new Event('change'));

        const thumbSrc = item.thumb || item.url || (item.path ? resolveUrl(item.path) : '');
        if (thumbSrc) {
            if (thumb) {
                thumb.src = thumbSrc;
                thumb.hidden = false;
                thumb.alt = item.alt || item.name || '';
            }
            if (preview) preview.hidden = false;
        }
    };

    /* ───── LIST MODE ───── */

    const setupList = el => {
        const textarea = el.querySelector('[data-mi-list-text]');
        const strip = el.querySelector('[data-mi-strip]');
        if (!textarea || !strip) return;

        // Tile remove
        strip.addEventListener('click', ev => {
            const rm = ev.target.closest('.ml-tile-remove');
            if (!rm) return;
            const tile = rm.closest('[data-ml-tile]');
            if (tile) tile.remove();
            syncListTextarea(el);
        });

        // Drag-drop sıralama
        strip.addEventListener('dragstart', ev => {
            const tile = ev.target.closest('[data-ml-tile]');
            if (!tile) return;
            tile.classList.add('ml-tile-dragging');
            ev.dataTransfer.effectAllowed = 'move';
            ev.dataTransfer.setData('text/plain', '');
        });
        strip.addEventListener('dragend', ev => {
            const tile = ev.target.closest('[data-ml-tile]');
            if (tile) tile.classList.remove('ml-tile-dragging');
            syncListTextarea(el);
        });
        strip.addEventListener('dragover', ev => {
            ev.preventDefault();
            const dragging = strip.querySelector('.ml-tile-dragging');
            if (!dragging) return;
            const after = getDragAfterElement(strip, ev.clientX);
            if (after == null) strip.appendChild(dragging);
            else strip.insertBefore(dragging, after);
        });

        // Tüm tile'lar draggable
        strip.querySelectorAll('[data-ml-tile]').forEach(t => {
            t.setAttribute('draggable', 'true');
        });

        // Textarea manuel değişirse strip'i regenerate et
        textarea.addEventListener('change', () => {
            rebuildStrip(el);
        });
    };

    const addToList = (el, item) => {
        const textarea = el.querySelector('[data-mi-list-text]');
        const strip = el.querySelector('[data-mi-strip]');
        if (!textarea || !strip) return;

        const value = item.url || item.path || '';
        if (!value) return;

        // Textarea'ya ekle
        const current = textarea.value.replace(/\s+$/, '');
        textarea.value = current + (current ? '\n' : '') + value;

        // Strip'e ekle
        const thumbSrc = item.thumb || item.url || (item.path ? resolveUrl(item.path) : '');
        const tile = document.createElement('div');
        tile.className = 'ml-tile';
        tile.setAttribute('data-ml-tile', '');
        tile.setAttribute('data-value', value);
        tile.setAttribute('draggable', 'true');
        tile.innerHTML =
            (thumbSrc
                ? `<img src="${escAttr(thumbSrc)}" alt="">`
                : `<div class="ml-tile-id">${escHtml(value)}</div>`) +
            '<button type="button" class="ml-tile-remove" title="Kaldır">×</button>';
        strip.appendChild(tile);
    };

    const syncListTextarea = el => {
        const textarea = el.querySelector('[data-mi-list-text]');
        const strip = el.querySelector('[data-mi-strip]');
        if (!textarea || !strip) return;
        const values = Array.from(strip.querySelectorAll('[data-ml-tile]'))
            .map(t => t.getAttribute('data-value') || '')
            .filter(v => v !== '');
        textarea.value = values.join('\n');
    };

    const rebuildStrip = el => {
        const textarea = el.querySelector('[data-mi-list-text]');
        const strip = el.querySelector('[data-mi-strip]');
        if (!textarea || !strip) return;
        const lines = textarea.value
            .split(/\r?\n/)
            .map(s => s.trim())
            .filter(Boolean);
        strip.innerHTML = '';
        lines.forEach(v => {
            const isUrl = /^https?:\/\//i.test(v);
            const isId = /^\d+$/.test(v);
            const thumbSrc = isUrl ? v : isId ? '' : resolveUrl(v);
            const tile = document.createElement('div');
            tile.className = 'ml-tile';
            tile.setAttribute('data-ml-tile', '');
            tile.setAttribute('data-value', v);
            tile.setAttribute('draggable', 'true');
            tile.innerHTML =
                (thumbSrc
                    ? `<img src="${escAttr(thumbSrc)}" alt="">`
                    : `<div class="ml-tile-id">#${escHtml(v)}</div>`) +
                '<button type="button" class="ml-tile-remove" title="Kaldır">×</button>';
            strip.appendChild(tile);
        });
    };

    const getDragAfterElement = (container, x) => {
        const tiles = Array.from(
            container.querySelectorAll('[data-ml-tile]:not(.ml-tile-dragging)')
        );
        return tiles.reduce(
            (closest, child) => {
                const { left, width } = child.getBoundingClientRect();
                const offset = x - left - width / 2;
                if (offset < 0 && offset > closest.offset) {
                    return { offset, element: child };
                }
                return closest;
            },
            { offset: -Infinity, element: null }
        ).element;
    };

    /* ───── DIRECT UPLOAD (file picker) ───── */

    const triggerUpload = onDone => {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.multiple = true;
        input.style.display = 'none';
        document.body.appendChild(input);
        input.addEventListener('change', () => {
            const { files } = input;
            if (!files || !files.length) {
                input.remove();
                return;
            }
            uploadFiles(
                files,
                item => {
                    onDone(item);
                },
                () => {
                    input.remove();
                }
            );
        });
        input.click();
    };

    const uploadFiles = async (files, onItem, onAll) => {
        const total = files.length;
        let done = 0;
        for (const f of files) {
            const fd = new FormData();
            fd.append('_csrf', csrf());
            fd.append('image', f);
            fd.append('alt', f.name.replace(/\.[^.]+$/, ''));
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
                } catch {
                    j = { ok: false, error: `HTTP ${r.status} — JSON döndürmedi` };
                }
                done++;
                if (j && j.ok) {
                    const item = j.item || j;
                    onItem({
                        url: item.url || item.path || '',
                        path: item.path || '',
                        thumb: item.thumb || item.url || '',
                        name: item.name || f.name,
                        alt: item.alt || f.name,
                    });
                } else {
                    console.warn('[media-input] upload failed:', j);
                    alert(`Yükleme başarısız: ${j && j.error ? j.error : 'bilinmeyen'}`);
                }
            } catch (err) {
                done++;
                console.warn('[media-input] upload error:', err);
            }
            if (done === total && onAll) onAll();
        }
    };

    const csrf = () => {
        const i = document.querySelector('input[name="_csrf"]');
        return i ? i.value : '';
    };

    const resolveUrl = path => {
        if (!path) return '';
        if (/^https?:\/\//i.test(path)) return path;
        if (path[0] === '/') return path;
        return `/${path}`;
    };

    const escAttr = s => String(s).replace(/"/g, '&quot;').replace(/</g, '&lt;');
    const escHtml = s =>
        String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

    if (document.readyState !== 'loading') init();
    else document.addEventListener('DOMContentLoaded', init);
})();
