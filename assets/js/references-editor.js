// Reference repeater — sözlük formu için.
// "Kaynak ekle" butonu yeni satır ekler, "Sil" butonu kaldırır.
// İndex'ler her ekleme/silmede yeniden numaralandırılır. Pattern: footnotes-editor.js.

(function () {
    'use strict';

    const list = document.getElementById('references-list');
    const addBtn = document.getElementById('reference-add');
    if (!list || !addBtn) return;

    const reindex = () => {
        const rows = list.querySelectorAll('[data-ref-row]');
        rows.forEach((row, idx) => {
            row.querySelectorAll('input').forEach(el => {
                const name = el.getAttribute('name') || '';
                el.setAttribute('name', name.replace(/references\[\d+\]/, `references[${idx}]`));
            });
        });
    };

    addBtn.addEventListener('click', () => {
        const rows = list.querySelectorAll('[data-ref-row]');
        const idx = rows.length;
        const div = document.createElement('div');
        div.className = 'faq-row reference-row';
        div.setAttribute('data-ref-row', '');
        div.innerHTML =
            `<input type="text" name="references[${idx}][text]"` +
            ` placeholder="Kaynak metni" maxlength="2000" value="">` +
            `<input type="url" name="references[${idx}][url]"` +
            ` placeholder="https://... (opsiyonel)" maxlength="500" value="">` +
            `<button type="button" class="btn btn-ghost reference-remove">Sil</button>`;
        list.appendChild(div);
        const firstInput = div.querySelector('input');
        if (firstInput) firstInput.focus();
    });

    list.addEventListener('click', e => {
        const target = e.target;
        if (!target || !target.classList || !target.classList.contains('reference-remove')) return;
        const row = target.closest('[data-ref-row]');
        if (row) {
            row.remove();
            reindex();
        }
    });
})();
