<?php
/**
 * Canlı SEO Google önizleme + meta_title/meta_desc placeholder
 * senkronizasyonu — title/excerpt/slug/kategori değiştikçe güncellenir.
 */
?>
<script nonce="<?= esc(csp_nonce()) ?>">
(function () {
    var titleInput    = document.querySelector('input[name="title"]');
    var excerptInput  = document.querySelector('textarea[name="excerpt"]');
    var slugInput     = document.querySelector('input[name="slug"]');
    var categorySel   = document.querySelector('select[name="category_id"]');
    var metaTitle     = document.getElementById('seo-meta-title');
    var metaDesc      = document.getElementById('seo-meta-desc');
    var previewTitle  = document.querySelector('[data-preview="title"]');
    var previewDesc   = document.querySelector('[data-preview="desc"]');
    var previewCat    = document.querySelector('[data-preview="category"]');
    var previewSlug   = document.querySelector('[data-preview="slug"]');

    function slugify(text) {
        var tr = {'ç':'c','Ç':'c','ğ':'g','Ğ':'g','ı':'i','İ':'i','ö':'o','Ö':'o','ş':'s','Ş':'s','ü':'u','Ü':'u'};
        text = (text || '').replace(/[çÇğĞıİöÖşŞüÜ]/g, function (c) { return tr[c]; });
        return text.toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function update() {
        var titleVal   = titleInput   ? titleInput.value.trim()   : '';
        var excerptVal = excerptInput ? excerptInput.value.trim() : '';
        var slugVal    = slugInput    ? slugInput.value.trim()    : '';
        var catText    = '';
        if (categorySel && categorySel.selectedOptions[0]) {
            catText = categorySel.selectedOptions[0].textContent.trim();
            if (catText === '— Seçin —') catText = 'kategori';
        }
        var effectiveTitle = (metaTitle && metaTitle.value.trim()) || titleVal || 'Yazı başlığı';
        var effectiveDesc  = (metaDesc  && metaDesc.value.trim())  || excerptVal || 'Yazı özeti burada görünür — boş bırakırsan içerikten otomatik üretilir.';
        var effectiveSlug  = slugVal || slugify(titleVal) || 'yazi-slug';

        if (previewTitle) previewTitle.textContent = effectiveTitle;
        if (previewDesc)  previewDesc.textContent  = effectiveDesc.length > 160 ? effectiveDesc.slice(0, 157) + '…' : effectiveDesc;
        if (previewCat)   previewCat.textContent   = catText || 'kategori';
        if (previewSlug)  previewSlug.textContent  = effectiveSlug;

        // Placeholder senkronizasyonu — meta_title boşken canlı title'ı yansıt
        if (metaTitle && !metaTitle.value && titleVal) {
            metaTitle.placeholder = 'Boş ise: ' + titleVal;
        }
        if (metaDesc && !metaDesc.value && excerptVal) {
            metaDesc.placeholder = 'Boş ise: ' + (excerptVal.length > 80 ? excerptVal.slice(0, 77) + '…' : excerptVal);
        }
    }

    [titleInput, excerptInput, slugInput, metaTitle, metaDesc, categorySel].forEach(function (el) {
        if (!el) return;
        el.addEventListener('input', update);
        el.addEventListener('change', update);
    });
    update();

    // ─── Önizleme linki üret/yenile butonu ───────────────────────────
    var prevBtn = document.querySelector('.pe-preview-btn');
    var prevOut = document.querySelector('.pe-preview-result');
    if (prevBtn && prevOut) {
        prevBtn.addEventListener('click', function () {
            var endpoint = prevBtn.getAttribute('data-preview-url');
            var csrf = prevBtn.getAttribute('data-preview-csrf') || '';
            if (!endpoint) return;
            var origText = prevBtn.textContent;
            prevBtn.disabled = true;
            prevBtn.textContent = 'Üretiliyor…';
            var fd = new FormData();
            fd.append('_csrf', csrf);
            fetch(endpoint, {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrf, Accept: 'application/json' },
                credentials: 'same-origin',
                body: fd
            })
            .then(function (r) { return r.json().catch(function () { return null; }); })
            .then(function (j) {
                prevOut.hidden = false;
                if (j && j.ok && j.url) {
                    prevOut.value = j.url;
                    prevOut.focus();
                    prevOut.select();
                    try { navigator.clipboard && navigator.clipboard.writeText(j.url); } catch (e) {}
                } else {
                    prevOut.value = (j && j.error) ? ('Hata: ' + j.error) : 'Link üretilemedi — sayfayı yenileyip tekrar deneyin.';
                }
            })
            .catch(function () {
                prevOut.hidden = false;
                prevOut.value = 'Bağlantı hatası.';
            })
            .finally(function () {
                prevBtn.disabled = false;
                prevBtn.textContent = origText;
            });
        });
    }
})();
</script>
