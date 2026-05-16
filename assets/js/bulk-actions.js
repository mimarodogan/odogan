// bulk-actions.js — Posts listesi toplu işlem deneyimi (Tier 5 feature 4.2).
//
// Görevleri:
//   * "Tümünü seç" checkbox → satır checkbox'larını işaretler
//   * Seçili sayısını canlı gösterir
//   * change_category seçildiğinde kategori dropdown'ı ortaya çıkar
//   * add_tag seçildiğinde etiket input'u ortaya çıkar
//   * Submit öncesi confirm + minimum 1 seçim doğrulaması
(function () {
    'use strict';

    const form = document.getElementById('bulk-form');
    if (!form) return;

    const allBox = document.querySelector('[data-bulk-all]');
    const itemBoxes = document.querySelectorAll('[data-bulk-item]');
    const countEl = document.querySelector('[data-bulk-count]');
    const actionSel = form.querySelector('select[name="bulk_action"]');
    const catSel = form.querySelector('[data-bulk-cat]');
    const tagInput = form.querySelector('[data-bulk-tag]');

    const updateCount = () => {
        let n = 0;
        for (let i = 0; i < itemBoxes.length; i++) {
            if (itemBoxes[i].checked) n++;
        }
        if (countEl) countEl.textContent = `${n} yazı seçili`;
        // "Tümünü seç" durumu (intermediate)
        if (allBox) {
            allBox.checked = n === itemBoxes.length && n > 0;
            allBox.indeterminate = n > 0 && n < itemBoxes.length;
        }
    };

    const toggleActionExtras = () => {
        const v = actionSel.value;
        if (catSel) {
            catSel.toggleAttribute('hidden', v !== 'change_category');
            catSel.required = v === 'change_category';
        }
        if (tagInput) {
            tagInput.toggleAttribute('hidden', v !== 'add_tag');
            tagInput.required = v === 'add_tag';
        }
    };

    if (allBox) {
        allBox.addEventListener('change', () => {
            for (let i = 0; i < itemBoxes.length; i++) {
                itemBoxes[i].checked = allBox.checked;
            }
            updateCount();
        });
    }
    for (let i = 0; i < itemBoxes.length; i++) {
        itemBoxes[i].addEventListener('change', updateCount);
    }
    if (actionSel) {
        actionSel.addEventListener('change', toggleActionExtras);
    }

    // Confirm helper — global'de tanımlı (HTML onsubmit'ten çağrılır)
    window.bulkConfirm = f => {
        const checked = f.querySelectorAll('[data-bulk-item]:checked').length;
        if (!checked) {
            alert('Önce en az bir yazı seçin.');
            return false;
        }
        const action = (f.bulk_action && f.bulk_action.value) || '';
        if (!action) {
            alert('İşlem seçmediniz.');
            return false;
        }
        let msg = `${checked} yazıya işlem uygulanacak: ${action}`;
        if (action === 'delete') {
            msg = `${checked} yazı KALICI olarak silinecek. Emin misin?`;
        }
        return confirm(msg);
    };

    updateCount();
    toggleActionExtras();
})();
