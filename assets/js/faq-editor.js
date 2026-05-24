// FAQ repeater — sözlük formu için. footnote-editor / references-editor
// ile aynı pattern. "Soru ekle" yeni satır ekler, "Sil" kaldırır;
// index'ler her değişimde yeniden numaralandırılır.

(function () {
    'use strict';

    const list = document.getElementById('faq-list');
    const addBtn = document.getElementById('faq-add');
    if (!list || !addBtn) return;

    const reindex = () => {
        const rows = list.querySelectorAll('[data-faq-row]');
        rows.forEach((row, idx) => {
            row.querySelectorAll('input, textarea').forEach(el => {
                const name = el.getAttribute('name') || '';
                el.setAttribute('name', name.replace(/faq\[\d+\]/, `faq[${idx}]`));
            });
        });
    };

    addBtn.addEventListener('click', () => {
        const rows = list.querySelectorAll('[data-faq-row]');
        const idx = rows.length;
        const div = document.createElement('div');
        div.className = 'faq-row gloss-faq-row';
        div.setAttribute('data-faq-row', '');
        div.innerHTML =
            `<input type="text" name="faq[${idx}][q]"` +
            ` placeholder="Soru" maxlength="220" value="">` +
            `<textarea name="faq[${idx}][a]"` +
            ` placeholder="Cevap (2-3 cümle)" rows="2" maxlength="2000"></textarea>` +
            `<button type="button" class="btn btn-ghost faq-remove">Sil</button>`;
        list.appendChild(div);
        const firstInput = div.querySelector('input');
        if (firstInput) firstInput.focus();
    });

    list.addEventListener('click', e => {
        const target = e.target;
        if (!target || !target.classList || !target.classList.contains('faq-remove')) return;
        const row = target.closest('[data-faq-row]');
        if (row) {
            row.remove();
            reindex();
        }
    });
})();
