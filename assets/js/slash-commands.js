// Slash commands — / tuşuyla overlay menü, Notion/Linear tarzı.
// Editor (contentEditable .wy-editor) içinde "/" yazınca floating menu açılır.
// Arrow up/down ile gezilir, Enter ile insert edilir, Escape kapatır.

(function () {
    'use strict';

    const editor = document.querySelector('.wy-editor');
    if (!editor) return;

    const COMMANDS = [
        {
            key: 'baslik2',
            label: 'Başlık 2',
            desc: 'Büyük alt başlık',
            icon: 'H2',
            action: () => {
                exec('formatBlock', 'h2');
            },
        },
        {
            key: 'baslik3',
            label: 'Başlık 3',
            desc: 'Orta alt başlık',
            icon: 'H3',
            action: () => {
                exec('formatBlock', 'h3');
            },
        },
        {
            key: 'alinti',
            label: 'Alıntı Bloğu',
            desc: 'Vurgu için italic alıntı',
            icon: '❝',
            action: () => {
                exec('formatBlock', 'blockquote');
            },
        },
        {
            key: 'liste',
            label: 'Sırasız Liste',
            desc: '• Liste',
            icon: '•',
            action: () => {
                exec('insertUnorderedList');
            },
        },
        {
            key: 'numarali',
            label: 'Numaralı Liste',
            desc: '1. 2. 3.',
            icon: '1.',
            action: () => {
                exec('insertOrderedList');
            },
        },
        {
            key: 'cizgi',
            label: 'Yatay Çizgi',
            desc: 'Bölüm ayırıcı (HR)',
            icon: '—',
            action: () => {
                exec('insertHTML', '<hr>');
            },
        },
        {
            key: 'kod',
            label: 'Kod Bloğu',
            desc: 'Monospace kod bloğu',
            icon: '</>',
            action: () => {
                exec('formatBlock', 'pre');
            },
        },
        {
            key: 'tablo',
            label: 'Tablo Ekle',
            desc: 'Satır × sütun girin',
            icon: '▦',
            action: () => {
                insertTable();
            },
        },
        {
            key: 'gorsel',
            label: 'Görsel Ekle',
            desc: 'Medya kütüphanesinden seç',
            icon: '◯',
            action: () => {
                openMediaPicker();
            },
        },
        {
            key: 'dipnot',
            label: 'Dipnot Marker',
            desc: '[^N] referans ekle',
            icon: '¹',
            action: () => {
                exec('insertHTML', '[^1]');
            },
        },
    ];

    let menu = null;
    let active = -1;
    let filtered = COMMANDS.slice();

    const exec = (cmd, val) => {
        closeMenu();
        try {
            document.execCommand(cmd, false, val || null);
        } catch {}
        editor.focus();
    };

    const insertTable = () => {
        const rows = parseInt(prompt('Kaç satır?', '3'), 10) || 3;
        const cols = parseInt(prompt('Kaç sütun?', '3'), 10) || 3;
        let html = '<table><thead><tr>';
        for (let c = 0; c < cols; c++) html += `<th>Başlık ${c + 1}</th>`;
        html += '</tr></thead><tbody>';
        for (let r = 0; r < rows; r++) {
            html += '<tr>';
            for (let c2 = 0; c2 < cols; c2++) html += '<td></td>';
            html += '</tr>';
        }
        html += '</tbody></table><p></p>';
        exec('insertHTML', html);
    };

    const openMediaPicker = () => {
        closeMenu();
        if (typeof window.openMediaPickerImpl === 'function') {
            window.openMediaPickerImpl(item => {
                if (item && item.url) {
                    document.execCommand(
                        'insertHTML',
                        false,
                        `<p><img src="${item.url}" alt="${item.alt || ''}"></p>`
                    );
                }
            });
        }
    };

    const ensureMenu = () => {
        if (menu) return menu;
        menu = document.createElement('div');
        menu.className = 'slash-menu';
        menu.setAttribute('role', 'listbox');
        menu.setAttribute('hidden', '');
        document.body.appendChild(menu);
        return menu;
    };

    const renderMenu = () => {
        const m = ensureMenu();
        if (!filtered.length) {
            closeMenu();
            return;
        }
        m.innerHTML = filtered
            .map(
                (cmd, i) =>
                    `<button type="button" class="slash-item ${i === active ? 'is-active' : ''}"` +
                    ` data-idx="${i}" role="option"` +
                    ` aria-selected="${i === active ? 'true' : 'false'}">` +
                    `<span class="slash-icon">${cmd.icon}</span>` +
                    `<span class="slash-body">` +
                    `<strong>${cmd.label}</strong>` +
                    `<small>${cmd.desc}</small>` +
                    `</span></button>`
            )
            .join('');
        m.removeAttribute('hidden');
    };

    const positionMenu = () => {
        if (!menu) return;
        const sel = window.getSelection();
        if (!sel || !sel.rangeCount) return;
        const range = sel.getRangeAt(0);
        let rect = range.getBoundingClientRect();
        if (!rect.left && !rect.top) {
            rect = editor.getBoundingClientRect();
        }
        menu.style.position = 'fixed';
        menu.style.top = `${rect.bottom + 6}px`;
        menu.style.left = `${rect.left}px`;
    };

    const closeMenu = () => {
        if (menu) menu.setAttribute('hidden', '');
        active = -1;
        filtered = COMMANDS.slice();
    };

    const applyFilter = query => {
        const q = (query || '').toLowerCase();
        filtered = COMMANDS.filter(
            cmd => cmd.key.includes(q) || cmd.label.toLowerCase().includes(q)
        );
        if (active >= filtered.length) active = filtered.length - 1;
        if (active < 0 && filtered.length) active = 0;
        renderMenu();
    };

    const getQueryAfterSlash = () => {
        const sel = window.getSelection();
        if (!sel || !sel.rangeCount) return null;
        const range = sel.getRangeAt(0);
        const node = range.startContainer;
        if (node.nodeType !== Node.TEXT_NODE) return null;
        const text = node.textContent || '';
        const pos = range.startOffset;
        const slashAt = text.lastIndexOf('/', pos - 1);
        if (slashAt === -1) return null;
        // / önce boşluk veya başlangıç olmalı
        if (slashAt > 0 && !/\s/.test(text[slashAt - 1])) return null;
        const query = text.slice(slashAt + 1, pos);
        if (query.length > 20 || /\s/.test(query)) return null;
        return { query, slashAt, node };
    };

    const removeSlashAndQuery = context => {
        const { node, slashAt, query } = context;
        const range = document.createRange();
        range.setStart(node, slashAt);
        range.setEnd(node, slashAt + query.length + 1);
        range.deleteContents();
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
    };

    editor.addEventListener('input', () => {
        const ctx = getQueryAfterSlash();
        if (!ctx) {
            closeMenu();
            return;
        }
        active = 0;
        applyFilter(ctx.query);
        if (filtered.length) {
            positionMenu();
        }
    });

    editor.addEventListener('keydown', e => {
        if (!menu || menu.hasAttribute('hidden')) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            active = (active + 1) % filtered.length;
            renderMenu();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            active = (active - 1 + filtered.length) % filtered.length;
            renderMenu();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const ctx = getQueryAfterSlash();
            const cmd = filtered[active];
            if (cmd) {
                if (ctx) removeSlashAndQuery(ctx);
                cmd.action();
            }
        } else if (e.key === 'Escape') {
            e.preventDefault();
            closeMenu();
        }
    });

    ensureMenu().addEventListener('click', e => {
        const btn = e.target.closest('.slash-item');
        if (!btn) return;
        const idx = parseInt(btn.dataset.idx, 10);
        const cmd = filtered[idx];
        if (cmd) {
            const ctx = getQueryAfterSlash();
            if (ctx) removeSlashAndQuery(ctx);
            cmd.action();
        }
    });

    document.addEventListener('click', e => {
        if (
            menu &&
            !menu.hasAttribute('hidden') &&
            !menu.contains(e.target) &&
            !editor.contains(e.target)
        ) {
            closeMenu();
        }
    });
})();
