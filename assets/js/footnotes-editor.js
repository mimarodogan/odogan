// Footnote repeater — admin editör tarafı.
// "Kaynak ekle" butonu yeni satır ekler, "Sil" butonu kaldırır.
// İndex'ler her ekleme/silmede yeniden numaralandırılır.

(function () {
    'use strict';

    const list = document.getElementById('footnotes-list');
    const addBtn = document.getElementById('footnote-add');
    if (!list || !addBtn) return;

    const reindex = () => {
        const rows = list.querySelectorAll('[data-fn-row]');
        rows.forEach((row, idx) => {
            row.querySelectorAll('input').forEach(el => {
                const name = el.getAttribute('name') || '';
                el.setAttribute('name', name.replace(/footnotes\[\d+\]/, `footnotes[${idx}]`));
            });
        });
    };

    addBtn.addEventListener('click', () => {
        const rows = list.querySelectorAll('[data-fn-row]');
        const idx = rows.length;
        const div = document.createElement('div');
        div.className = 'faq-row footnote-row';
        div.setAttribute('data-fn-row', '');
        div.innerHTML =
            `<input type="text" name="footnotes[${idx}][text]"` +
            ` placeholder="Dipnot metni" maxlength="2000" value="">` +
            `<input type="url" name="footnotes[${idx}][url]"` +
            ` placeholder="https://... (opsiyonel)" maxlength="500" value="">` +
            `<button type="button" class="btn btn-ghost footnote-remove">Sil</button>`;
        list.appendChild(div);
        div.querySelector('input').focus();
    });

    list.addEventListener('click', e => {
        const { target } = e;
        if (!target || !target.classList || !target.classList.contains('footnote-remove')) return;
        const row = target.closest('[data-fn-row]');
        if (row) {
            row.remove();
            reindex();
        }
    });
})();
