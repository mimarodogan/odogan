/* ════════════════════════════════════════════════════════════════════
 * team-builder.js — Proje formundaki ekip (architects/engineers/
 * consultants) repeater'ı. Add/remove satır, name[]/title[]/url[]
 * paralel array'leri form içinde tutar.
 * ════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    const placeholders = {
        architects: 'Müellif Mimar / Yardımcı Mimar / Yüksek Mimar',
        engineers: 'Statik / Mekanik / Elektrik / Jeofizik',
        consultants: 'Akustik / Peyzaj / İç Mimar / Aydınlatma',
    };

    const rowHtml = group => {
        const ph = placeholders[group] || 'Ünvan';
        return (
            `<div class="team-row" data-team-row>` +
            `<input type="text" name="${group}_name[]" placeholder="Ad Soyad / Stüdyo Adı" class="team-input team-input-name">` +
            `<input type="text" name="${group}_title[]" placeholder="${ph}" class="team-input team-input-title">` +
            `<input type="url"  name="${group}_url[]"   placeholder="https://www.studio.com" class="team-input team-input-url">` +
            `<button type="button" class="team-row-remove" data-team-remove aria-label="Satırı sil">×</button>` +
            `</div>`
        );
    };

    const bind = () => {
        document.querySelectorAll('[data-team-add]').forEach(btn => {
            btn.addEventListener('click', () => {
                const group = btn.getAttribute('data-team-add');
                const groupEl = btn.closest('[data-team-group]');
                if (!groupEl) return;
                const rows = groupEl.querySelector('[data-team-rows]');
                if (!rows) return;
                const temp = document.createElement('div');
                temp.innerHTML = rowHtml(group);
                const newRow = temp.firstElementChild;
                rows.appendChild(newRow);
                // Focus ilk inputa
                const first = newRow.querySelector('input');
                if (first) first.focus();
            });
        });

        // Event delegation for remove buttons (yeni eklenenler dahil)
        document.addEventListener('click', e => {
            const btn = e.target.closest('[data-team-remove]');
            if (!btn) return;
            const row = btn.closest('[data-team-row]');
            if (!row) return;
            const rows = row.parentElement;
            // En son satırsa: temizle, silme (form en az 1 boş satır tutmalı görsel için)
            if (rows.querySelectorAll('[data-team-row]').length === 1) {
                row.querySelectorAll('input').forEach(i => {
                    i.value = '';
                });
                return;
            }
            row.remove();
        });
    };

    if (document.readyState !== 'loading') bind();
    else document.addEventListener('DOMContentLoaded', bind);
})();
