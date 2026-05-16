// HowTo adım editörü — article_type=HowTo seçilince görünür,
// "+ Adım ekle" butonu yeni satır ekler, "×" tek satırı kaldırır.
(function () {
    'use strict';

    const section = document.getElementById('howto-section');
    const typeSel = document.getElementById('post-article-type');
    if (!section || !typeSel) return;

    const list = document.getElementById('howto-steps');
    const addBtn = document.getElementById('howto-add-step');

    const syncVisibility = () => {
        const isHowTo = typeSel.value === 'HowTo';
        section.hidden = !isHowTo;
    };
    syncVisibility();
    typeSel.addEventListener('change', syncVisibility);

    const renumber = () => {
        const steps = list.querySelectorAll('.pe-howto-step');
        steps.forEach((step, i) => {
            step.dataset.stepIndex = i;
            const num = step.querySelector('.pe-howto-step-num');
            if (num) num.textContent = i + 1;
            step.querySelectorAll('[name^="howto[steps]"]').forEach(el => {
                el.name = el.name.replace(/howto\[steps\]\[\d+\]/, `howto[steps][${i}]`);
            });
        });
    };

    const buildStep = idx => {
        const div = document.createElement('div');
        div.className = 'pe-howto-step';
        div.dataset.stepIndex = idx;
        div.innerHTML =
            `<div class="pe-howto-step-head">` +
            `<span class="pe-howto-step-num">${idx + 1}</span>` +
            `<input type="text" name="howto[steps][${idx}][name]" placeholder="Adım başlığı" maxlength="220">` +
            `<button type="button" class="btn btn-ghost howto-step-remove" title="Bu adımı sil">×</button>` +
            `</div>` +
            `<textarea name="howto[steps][${idx}][text]" rows="3" maxlength="2000" placeholder="Adım açıklaması"></textarea>` +
            `<input type="text" name="howto[steps][${idx}][image]" placeholder="(Opsiyonel) Adım görseli yolu" maxlength="255">`;
        return div;
    };

    if (addBtn) {
        addBtn.addEventListener('click', () => {
            const idx = list.querySelectorAll('.pe-howto-step').length;
            const node = buildStep(idx);
            list.appendChild(node);
            const firstInput = node.querySelector('input[type="text"]');
            if (firstInput) firstInput.focus();
        });
    }

    list.addEventListener('click', ev => {
        const btn = ev.target.closest('.howto-step-remove');
        if (!btn) return;
        const step = btn.closest('.pe-howto-step');
        if (!step) return;
        // En az 1 adım kalsın
        if (list.querySelectorAll('.pe-howto-step').length <= 1) {
            // Temizle ama silme
            step.querySelectorAll('input,textarea').forEach(el => {
                el.value = '';
            });
            return;
        }
        step.remove();
        renumber();
    });
})();
