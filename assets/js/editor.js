// Otorite Yayin — Zengin metin editörü
// contentEditable + execCommand. HTML server-side Sanitizer ile temizlenir.
// Body alanı: hidden <textarea#rich-body> ↔ visible .wy-editor.

(function () {
    'use strict';

    const ta = document.getElementById('rich-body');
    if (!ta) return;

    // ──────────────────────── Renk paletleri ─────────────────────────
    const TEXT_COLORS = [
        { v: '', n: 'Varsayılan' },
        { v: '#111111', n: 'Siyah' },
        { v: '#5A544D', n: 'Gri' },
        { v: '#B0241D', n: 'Kırmızı' },
        { v: '#C8421E', n: 'Turuncu' },
        { v: '#8C6A12', n: 'Hardal' },
        { v: '#2F6A3E', n: 'Yeşil' },
        { v: '#1F3A8A', n: 'Cobalt' },
        { v: '#5B2A86', n: 'Mor' },
        { v: '#FFFFFF', n: 'Beyaz' },
    ];
    const BG_COLORS = [
        { v: '', n: 'Yok' },
        { v: '#FFF59D', n: 'Sarı' },
        { v: '#C8E6C9', n: 'Yeşil' },
        { v: '#FFCDD2', n: 'Kırmızı' },
        { v: '#BBDEFB', n: 'Mavi' },
        { v: '#E1BEE7', n: 'Mor' },
        { v: '#F1ECE2', n: 'Bej' },
        { v: '#111111', n: 'Siyah' },
    ];
    const FONTS = [
        { v: '', n: 'Varsayılan' },
        { v: 'Newsreader, Georgia, serif', n: 'Serif' },
        { v: 'Inter, system-ui, sans-serif', n: 'Sans' },
        { v: '"JetBrains Mono", ui-monospace, monospace', n: 'Mono' },
    ];
    const SIZES = [
        { v: '', n: 'Boyut' },
        { v: '0.85em', n: 'Küçük' },
        { v: '1em', n: 'Normal' },
        { v: '1.25em', n: 'Büyük' },
        { v: '1.5em', n: 'X-Büyük' },
        { v: '2em', n: 'XX-Büyük' },
    ];

    // ──────────────────────── Host iskeleti ──────────────────────────
    const host = document.createElement('div');
    host.className = 'wysiwyg';
    host.innerHTML =
        '<div class="wy-toolbar" role="toolbar" aria-label="Biçim araçları"></div>' +
        '<div class="wy-editor" contenteditable="true" spellcheck="true" lang="tr"></div>' +
        '<textarea class="wy-source-view" spellcheck="false" hidden></textarea>' +
        '<div class="wy-statusbar"><span class="wy-count">0 kelime · 0 karakter</span><span class="wy-mode">Görsel mod</span></div>';
    ta.parentNode.insertBefore(host, ta);
    ta.classList.add('wy-source');
    ta.setAttribute('tabindex', '-1');

    const editor = host.querySelector('.wy-editor');
    const toolbar = host.querySelector('.wy-toolbar');
    const sourceTA = host.querySelector('.wy-source-view');
    const countEl = host.querySelector('.wy-count');
    const modeEl = host.querySelector('.wy-mode');

    // ────────────────────  Temel yardımcılar (erken tanım)  ──────────
    const escAttr = s => String(s).replace(/"/g, '&quot;').replace(/</g, '&lt;');
    const escHtml = s =>
        String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

    const mdToHtmlBasic = md => {
        if (!md) return '';
        let s = escHtml(md);
        s = s
            .replace(/^### (.+)$/gm, '<h3>$1</h3>')
            .replace(/^## (.+)$/gm, '<h2>$1</h2>')
            .replace(/^# (.+)$/gm, '<h1>$1</h1>')
            .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
            .replace(/\*([^*]+)\*/g, '<em>$1</em>')
            .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>');
        return s
            .split(/\n{2,}/)
            .map(p => {
                if (/^<(h\d|ul|ol|blockquote|pre|figure)/.test(p)) return p;
                return `<p>${p.replace(/\n/g, '<br>')}</p>`;
            })
            .join('\n');
    };

    const updateCount = () => {
        const text = (editor.innerText || '').trim();
        const chars = text.length;
        const words = text === '' ? 0 : text.split(/\s+/).length;
        countEl.textContent = `${words} kelime · ${chars} karakter`;
    };

    // İlk seed
    if (ta.dataset.format === 'html') {
        editor.innerHTML = ta.value || '';
    } else {
        editor.innerHTML = mdToHtmlBasic(ta.value || '');
    }
    updateCount();

    const syncToTextarea = () => {
        // Source modunda hidden textarea'dan oku, görsel modda editor'dan
        ta.value = host.classList.contains('wy-source-mode')
            ? (sourceTA.value || '').trim()
            : editor.innerHTML.trim();
        updateCount();
    };
    editor.addEventListener('input', syncToTextarea);
    editor.addEventListener('blur', syncToTextarea);
    sourceTA.addEventListener('input', syncToTextarea);

    const form = ta.closest('form');
    if (form)
        form.addEventListener('submit', () => {
            // Source modunda ise önce görsele kopyala
            if (host.classList.contains('wy-source-mode')) {
                editor.innerHTML = sourceTA.value;
            }
            const fmtInput = form.querySelector('input[name="body_format"]');
            if (fmtInput) fmtInput.value = 'html';
            syncToTextarea();
        });

    // ─────────────────────────  Toolbar  ─────────────────────────────
    // Toolbar yapısı: gruplara ayrılmış butonlar / dropdown'lar
    const groups = [
        // Geri al / yinele
        [
            { fn: 'undo', l: '↶', t: 'Geri al (Ctrl+Z)' },
            { fn: 'redo', l: '↷', t: 'Yinele (Ctrl+Y)' },
        ],
        // Paragraf tipi
        [
            {
                dropdown: 'block',
                l: 'Paragraf ▾',
                t: 'Paragraf tipi',
                items: [
                    { tag: 'p', n: 'Paragraf' },
                    { tag: 'h1', n: 'Başlık 1' },
                    { tag: 'h2', n: 'Başlık 2' },
                    { tag: 'h3', n: 'Başlık 3' },
                    { tag: 'h4', n: 'Başlık 4' },
                    { tag: 'blockquote', n: 'Alıntı' },
                    { tag: 'pre', n: 'Kod bloğu' },
                ],
            },
        ],
        // Font ailesi & boyutu
        [
            { dropdown: 'font', l: 'Yazı tipi ▾', t: 'Yazı tipi', items: FONTS },
            { dropdown: 'size', l: 'Boyut ▾', t: 'Yazı boyutu', items: SIZES },
        ],
        // Temel biçim
        [
            { c: 'bold', l: '𝐁', t: 'Kalın (Ctrl+B)' },
            { c: 'italic', l: '𝑰', t: 'Eğik (Ctrl+I)' },
            { c: 'underline', l: '𝐔̲', t: 'Altçizgi (Ctrl+U)' },
            { c: 'strikeThrough', l: 'S̶', t: 'Üstü çizili' },
            { fn: 'mark', l: '🖍', t: 'Vurgula' },
            { c: 'subscript', l: 'X₂', t: 'Alt simge' },
            { c: 'superscript', l: 'X²', t: 'Üst simge' },
        ],
        // Renkler
        [
            {
                dropdown: 'color',
                l: '<span class="wy-color-swatch" style="background:#111"></span>A',
                t: 'Yazı rengi',
                items: TEXT_COLORS,
            },
            {
                dropdown: 'bg',
                l: '<span class="wy-color-swatch" style="background:#FFF59D"></span>🖌',
                t: 'Arka plan rengi',
                items: BG_COLORS,
            },
        ],
        // Hizalama
        [
            { c: 'justifyLeft', l: '◧', t: 'Sola hizala' },
            { c: 'justifyCenter', l: '◫', t: 'Ortaya hizala' },
            { c: 'justifyRight', l: '◨', t: 'Sağa hizala' },
            { c: 'justifyFull', l: '☰', t: 'İki yana yasla' },
        ],
        // Listeler ve girinti
        [
            { c: 'insertUnorderedList', l: '• ≡', t: 'Madde liste' },
            { c: 'insertOrderedList', l: '1. ≡', t: 'Sıralı liste' },
            { c: 'outdent', l: '⇤', t: 'Girintiyi azalt' },
            { c: 'indent', l: '⇥', t: 'Girinti ekle' },
        ],
        // Ek bloklar
        [
            { fn: 'link', l: '🔗', t: 'Bağlantı ekle (Ctrl+K)' },
            { fn: 'unlink', l: '⛓̸', t: 'Bağlantıyı kaldır' },
            { fn: 'image', l: '🖼', t: 'Galeriden görsel ekle' },
            { fn: 'table', l: '⊞', t: 'Tablo ekle' },
            { fn: 'hr', l: '―', t: 'Yatay çizgi' },
            { fn: 'inline-code', l: '</>', t: 'Satır içi kod' },
            { fn: 'special', l: 'Ω', t: 'Özel karakter' },
        ],
        // Temizle / kaynak / tam ekran
        [
            { fn: 'clear', l: '⌫', t: 'Biçimi temizle' },
            { fn: 'source', l: '⟨/⟩', t: 'HTML kaynağı' },
            { fn: 'fullscreen', l: '⛶', t: 'Tam ekran' },
        ],
    ];

    const renderItem = a => {
        if (a.dropdown) {
            renderDropdown(a);
            return;
        }
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'wy-btn';
        b.title = a.t;
        b.innerHTML = a.l;
        b.addEventListener('mousedown', ev => {
            ev.preventDefault();
        });
        b.addEventListener('click', () => {
            runAction(a);
        });
        toolbar.appendChild(b);
    };

    const renderDropdown = a => {
        const wrap = document.createElement('span');
        wrap.className = 'wy-dd';
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'wy-btn wy-dd-trigger';
        trigger.title = a.t;
        trigger.innerHTML = a.l;
        trigger.setAttribute('aria-haspopup', 'true');
        trigger.setAttribute('aria-expanded', 'false');
        wrap.appendChild(trigger);

        const menu = document.createElement('div');
        menu.className = `wy-dd-menu wy-dd-${a.dropdown}`;
        menu.hidden = true;

        a.items.forEach(item => {
            const entry = document.createElement('button');
            entry.type = 'button';
            entry.className = 'wy-dd-item';
            if (a.dropdown === 'color' || a.dropdown === 'bg') {
                entry.classList.add('wy-dd-color');
                const swatchBorder = item.v ? '' : 'border:1px dashed var(--hair-2)';
                entry.innerHTML =
                    `<span class="wy-color-swatch" style="background:${item.v || 'transparent'};${swatchBorder}"></span>` +
                    escHtml(item.n);
            } else {
                entry.textContent = item.n;
                if (a.dropdown === 'font' && item.v) entry.style.fontFamily = item.v;
                if (a.dropdown === 'size' && item.v) entry.style.fontSize = item.v;
            }
            entry.addEventListener('mousedown', ev => {
                ev.preventDefault();
            });
            entry.addEventListener('click', () => {
                applyDropdown(a.dropdown, item);
                closeMenu();
            });
            menu.appendChild(entry);
        });
        wrap.appendChild(menu);

        const closeMenu = () => {
            menu.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
        };
        const openMenu = () => {
            // Diğerlerini kapat
            host.querySelectorAll('.wy-dd-menu').forEach(m => {
                m.hidden = true;
            });
            menu.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
        };
        trigger.addEventListener('mousedown', ev => {
            ev.preventDefault();
        });
        trigger.addEventListener('click', ev => {
            ev.stopPropagation();
            if (menu.hidden) openMenu();
            else closeMenu();
        });
        document.addEventListener('click', ev => {
            if (!wrap.contains(ev.target)) closeMenu();
        });
        toolbar.appendChild(wrap);
    };

    // ─── Toolbar render — renderItem + renderDropdown tanımları yukarıda
    // Bu çağrı IIFE içinde synchronous loop'ta yapılıyor, dolayısıyla
    // renderItem'ı önceden const olarak tanımlamamız ŞART (TDZ aksi halde).
    groups.forEach((group, gi) => {
        if (gi > 0) {
            const sep = document.createElement('span');
            sep.className = 'wy-sep';
            toolbar.appendChild(sep);
        }
        group.forEach(a => {
            renderItem(a);
        });
    });

    const applyDropdown = (kind, item) => {
        editor.focus();
        restoreSelection();
        if (kind === 'block') {
            document.execCommand('formatBlock', false, item.tag);
        } else if (kind === 'color') {
            if (!item.v) {
                document.execCommand('foreColor', false, 'inherit');
                wrapSelectionStyle('color', null);
            } else {
                document.execCommand('foreColor', false, item.v);
            }
        } else if (kind === 'bg') {
            if (!item.v) {
                wrapSelectionStyle('background-color', null);
            } else {
                // Hilite/Back doesn't work consistently — wrap as <span style>
                wrapSelectionStyle('background-color', item.v);
            }
        } else if (kind === 'font') {
            wrapSelectionStyle('font-family', item.v || null);
        } else if (kind === 'size') {
            wrapSelectionStyle('font-size', item.v || null);
        }
        syncToTextarea();
    };

    // ─────────────────────  execCommand sarmalayıcı  ─────────────────
    const runAction = a => {
        editor.focus();
        restoreSelection();
        if (a.c) {
            document.execCommand(a.c, false, null);
        } else if (a.fn === 'undo') {
            document.execCommand('undo');
        } else if (a.fn === 'redo') {
            document.execCommand('redo');
        } else if (a.fn === 'link') {
            insertLink();
        } else if (a.fn === 'unlink') {
            document.execCommand('unlink', false, null);
        } else if (a.fn === 'image') {
            openMediaPicker(insertImage);
        } else if (a.fn === 'table') {
            insertTable();
        } else if (a.fn === 'hr') {
            document.execCommand('insertHorizontalRule', false, null);
        } else if (a.fn === 'inline-code') {
            wrapSelectionInline('code');
        } else if (a.fn === 'mark') {
            wrapSelectionInline('mark');
        } else if (a.fn === 'special') {
            insertSpecialChar();
        } else if (a.fn === 'clear') {
            document.execCommand('removeFormat', false, null);
        } else if (a.fn === 'source') {
            toggleSource();
        } else if (a.fn === 'fullscreen') {
            host.classList.toggle('wy-fullscreen');
            document.body.classList.toggle(
                'wy-fullscreen-on',
                host.classList.contains('wy-fullscreen')
            );
        }
        syncToTextarea();
    };

    // ─────────────────────  Yardımcılar  ─────────────────────────────
    const insertLink = () => {
        const sel = window.getSelection();
        let current = '';
        if (sel && sel.anchorNode) {
            const a = sel.anchorNode.parentElement
                ? sel.anchorNode.parentElement.closest('a')
                : null;
            if (a) current = a.getAttribute('href') || '';
        }
        let url = prompt("Bağlantı URL'i (https://...)", current || 'https://');
        if (url === null) return;
        url = url.trim();
        if (!url) {
            document.execCommand('unlink', false, null);
            return;
        }
        if (!/^(https?:\/\/|mailto:|tel:|\/|#)/i.test(url)) {
            url = `https://${url}`;
        }
        document.execCommand('createLink', false, url);
    };

    const insertTable = () => {
        let rows = parseInt(prompt('Satır sayısı (1-20)', '3'), 10);
        if (!rows || rows < 1) return;
        rows = Math.min(rows, 20);
        let cols = parseInt(prompt('Sütun sayısı (1-10)', '3'), 10);
        if (!cols || cols < 1) return;
        cols = Math.min(cols, 10);

        let html = '<table class="wy-table"><thead><tr>';
        for (let c = 0; c < cols; c++) html += `<th>Başlık ${c + 1}</th>`;
        html += '</tr></thead><tbody>';
        for (let r = 0; r < rows; r++) {
            html += '<tr>';
            for (let c2 = 0; c2 < cols; c2++) html += '<td>&nbsp;</td>';
            html += '</tr>';
        }
        html += '</tbody></table><p><br></p>';
        document.execCommand('insertHTML', false, html);
    };

    const insertSpecialChar = () => {
        const ch = prompt('Karakter girin (örn: ©, —, …, →, ❝, €, ★):', '—');
        if (ch) document.execCommand('insertText', false, ch);
    };

    const buildImageHtml = item => {
        const { variants = {}, alt, width, height, url } = item;
        const webpParts = [];
        [320, 768, 1280].forEach(w => {
            if (variants[w] && variants[w].webp) {
                webpParts.push(`/${variants[w].webp} ${w}w`);
            }
        });
        const imgAttrs =
            (alt ? ` alt="${escAttr(alt)}"` : ' alt=""') +
            (width ? ` width="${width}"` : '') +
            (height ? ` height="${height}"` : '') +
            ' loading="lazy" decoding="async"';
        if (webpParts.length) {
            return (
                `<picture>` +
                `<source type="image/webp" srcset="${webpParts.join(', ')}" sizes="(max-width:720px) 100vw, 720px">` +
                `<img src="${url}"${imgAttrs}>` +
                `</picture>`
            );
        }
        return `<img src="${url}"${imgAttrs}>`;
    };

    const insertImage = item => {
        const inner = buildImageHtml(item);
        const html =
            `<figure>` +
            inner +
            (item.alt ? `<figcaption>${escHtml(item.alt)}</figcaption>` : '') +
            `</figure>`;
        document.execCommand('insertHTML', false, html);
    };

    // execCommand 'foreColor' tag çıkartır ama background-color, font-family,
    // font-size için seçimi span ile sarmamız gerek. null değer geçilirse
    // ilgili stil sıfırlanır.
    const wrapSelectionStyle = (prop, value) => {
        const sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) return;
        const range = sel.getRangeAt(0);
        if (range.collapsed) return;

        const existing = findAncestor(range.commonAncestorContainer, el => {
            return el.tagName === 'SPAN' && el.style && el.style[propToCamel(prop)];
        });
        if (existing && existing.textContent === range.toString()) {
            if (value === null) {
                existing.style[propToCamel(prop)] = '';
                if (!existing.getAttribute('style')) existing.removeAttribute('style');
                if (!existing.attributes.length && existing.tagName === 'SPAN') {
                    unwrap(existing);
                }
            } else {
                existing.style[propToCamel(prop)] = value;
            }
            return;
        }
        if (value === null) return;

        const span = document.createElement('span');
        span.style[propToCamel(prop)] = value;
        const contents = range.extractContents();
        span.appendChild(contents);
        range.insertNode(span);
        // Cursor'u span sonuna taşı
        const after = document.createRange();
        after.selectNodeContents(span);
        after.collapse(false);
        sel.removeAllRanges();
        sel.addRange(after);
    };

    const wrapSelectionInline = tagName => {
        const sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) return;
        const range = sel.getRangeAt(0);
        if (range.collapsed) return;
        const existing = findAncestor(range.commonAncestorContainer, el => {
            return el.tagName === tagName.toUpperCase();
        });
        if (existing && existing.textContent === range.toString()) {
            unwrap(existing);
            return;
        }
        const el = document.createElement(tagName);
        el.appendChild(range.extractContents());
        range.insertNode(el);
        const after = document.createRange();
        after.selectNodeContents(el);
        after.collapse(false);
        sel.removeAllRanges();
        sel.addRange(after);
    };

    const unwrap = node => {
        const parent = node.parentNode;
        while (node.firstChild) parent.insertBefore(node.firstChild, node);
        parent.removeChild(node);
    };

    const findAncestor = (node, predicate) => {
        while (node && node !== editor) {
            if (node.nodeType === 1 && predicate(node)) return node;
            node = node.parentNode;
        }
        return null;
    };

    const propToCamel = p => p.replace(/-([a-z])/g, (_, c) => c.toUpperCase());

    // Source/Visual mode
    const toggleSource = () => {
        if (host.classList.contains('wy-source-mode')) {
            // Source → Visual
            editor.innerHTML = sourceTA.value;
            host.classList.remove('wy-source-mode');
            sourceTA.hidden = true;
            modeEl.textContent = 'Görsel mod';
        } else {
            // Visual → Source
            sourceTA.value = formatHtml(editor.innerHTML);
            host.classList.add('wy-source-mode');
            sourceTA.hidden = false;
            modeEl.textContent = 'HTML kaynak modu';
        }
    };

    const formatHtml = s => {
        // Basit pretty-printer (sadece yeni satır eklemek için)
        return s
            .replace(/></g, '>\n<')
            .replace(/(<\/(h[1-6]|p|div|li|tr|figure|figcaption|blockquote|pre)>)/g, '$1\n');
    };

    // Seçim koruma (dropdown açıldığında kaybolmaması için)
    let savedRange = null;
    const saveSelection = () => {
        const sel = window.getSelection();
        if (sel.rangeCount > 0 && editor.contains(sel.anchorNode)) {
            savedRange = sel.getRangeAt(0).cloneRange();
        }
    };
    const restoreSelection = () => {
        if (!savedRange) return;
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(savedRange);
    };
    editor.addEventListener('mouseup', saveSelection);
    editor.addEventListener('keyup', saveSelection);

    // ───────────────────  Klavye kısayolları  ────────────────────────
    editor.addEventListener('keydown', ev => {
        if (!(ev.ctrlKey || ev.metaKey)) return;
        const k = ev.key.toLowerCase();
        if (k === 'b' || k === 'i' || k === 'u') {
            ev.preventDefault();
            document.execCommand({ b: 'bold', i: 'italic', u: 'underline' }[k]);
            syncToTextarea();
        } else if (k === 'k') {
            ev.preventDefault();
            insertLink();
            syncToTextarea();
        } else if (k === 'z' && !ev.shiftKey) {
            ev.preventDefault();
            document.execCommand('undo');
            syncToTextarea();
        } else if (k === 'y' || (k === 'z' && ev.shiftKey)) {
            ev.preventDefault();
            document.execCommand('redo');
            syncToTextarea();
        }
    });

    // ESC = fullscreen'den çık
    document.addEventListener('keydown', ev => {
        if (ev.key === 'Escape' && host.classList.contains('wy-fullscreen')) {
            host.classList.remove('wy-fullscreen');
            document.body.classList.remove('wy-fullscreen-on');
        }
    });

    // ─────────────────  Backspace history-back koruması  ───────────────
    // Browser, focus body/document'te iken Backspace tuşunu history.back()
    // olarak yorumlayabilir (eski davranış bazı Chromium sürümlerinde hala
    // mevcut). Editor sayfasında yazı yazılırken sayfanın geri gitmesini
    // engelle: focus content-editable veya input/textarea değilse Backspace
    // ve Alt+ArrowLeft tuşlarını yutuyoruz.
    document.addEventListener(
        'keydown',
        ev => {
            if (ev.defaultPrevented) return;
            if (ev.key !== 'Backspace' && !(ev.altKey && ev.key === 'ArrowLeft')) return;
            const t = ev.target;
            if (!t) return;
            const tag = (t.tagName || '').toLowerCase();
            // Input / textarea / select / contenteditable doğal davranışı korusun
            if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
            if (t.isContentEditable) return;
            // Burada focus body/document/div vb. → tarayıcı history.back yapardı
            ev.preventDefault();
        },
        true
    ); // capture phase — diğer handler'lardan önce dur

    // ──────────────────────  Paste — temizleyici  ────────────────────
    editor.addEventListener('paste', ev => {
        // Word/Google Docs'tan zengin biçimi atıp, plain text yapıştır.
        if (ev.clipboardData && ev.clipboardData.getData) {
            const html = ev.clipboardData.getData('text/html');
            const text = ev.clipboardData.getData('text/plain');
            if (html && /style=|class=|<o:p>|MsoNormal/i.test(html)) {
                ev.preventDefault();
                // Sadece güvenli tag'ler
                const clean = html
                    .replace(/<!--[\s\S]*?-->/g, '')
                    .replace(/<\/?(o:p|w:|m:|v:|xml|meta|link|style|script)[^>]*>/gi, '')
                    .replace(/ (style|class|lang|align|width|height)="[^"]*"/gi, '')
                    .replace(/<span>/gi, '')
                    .replace(/<\/span>/gi, '')
                    .replace(/<p[^>]*>/gi, '<p>');
                document.execCommand('insertHTML', false, clean);
                return;
            }
            if (text && !html) {
                ev.preventDefault();
                document.execCommand('insertText', false, text);
            }
        }
    });

    const openMediaPicker = onPick => {
        if (typeof window.openMediaPickerImpl === 'function') {
            window.openMediaPickerImpl(onPick);
        } else {
            alert('Görsel seçici yüklenmedi. Sayfayı yenileyin.');
        }
    };
})();
