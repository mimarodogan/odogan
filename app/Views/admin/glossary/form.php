<?php
/**
 * Mimari Sözlük terim formu — Atelier post-editor patternine uyumlu.
 * @var array $item
 */
\App\Core\View::layout('base');

$isEdit = !empty($item['id']);
$action = $isEdit ? url('/admin/sozluk/' . (int) $item['id']) : url('/admin/sozluk');
?>

<section class="hero post-editor-hero">
    <div>
        <p class="post-editor-meta">
            <a href="<?= esc(url('/admin/sozluk')) ?>" class="muted">← Tüm Terimler</a>
        </p>
        <h1><?= $isEdit ? 'Terimi Düzenle' : 'Yeni Terim' ?></h1>
        <p class="post-editor-meta">
            <?php if ($isEdit): ?>
                <span class="badge <?= !empty($item['is_active']) ? 'badge-published' : 'badge-draft' ?>">
                    <?= !empty($item['is_active']) ? 'Aktif' : 'Gizli' ?>
                </span>
                <span class="muted">·</span>
                <span class="muted">/sozluk/<?= esc((string) ($item['slug'] ?? '')) ?></span>
            <?php else: ?>
                <span class="badge badge-draft">Yeni</span>
            <?php endif; ?>
        </p>
    </div>
    <?php require dirname(__DIR__, 2) . '/partials/flash.php'; ?>
</section>

<form method="post" action="<?= esc($action) ?>" class="post-editor" id="glossary-form">
    <?= csrf_field() ?>

    <header class="post-editor-head">
        <input type="text"
               name="term"
               id="glossary-term"
               class="post-title-input"
               required minlength="2" maxlength="180"
               placeholder="Terim (örn: Konsol kiriş)…"
               autocomplete="off"
               data-existing-id="<?= $isEdit ? (int) $item['id'] : 0 ?>"
               value="<?= esc((string) ($item['term'] ?? '')) ?>">
        <p id="glossary-dup-status" class="glossary-dup-status" aria-live="polite" hidden></p>
    </header>

    <div class="post-editor-grid">
        <div class="post-editor-main">

            <?php if (function_exists('feature') && feature('glossary_ai_enabled')): ?>
            <section class="pe-section glossary-ai-section <?= $isEdit ? 'glossary-ai-enhance' : 'glossary-ai-new' ?>"
                     id="glossary-ai-panel"
                     data-mode="<?= $isEdit ? 'enhance' : 'new' ?>">
                <h2 class="pe-section-title">
                    <span aria-hidden="true">✶</span>
                    <?= $isEdit ? 'AI ile Mevcut Girdiyi Geliştir' : 'AI ile Taslak Üret' ?>
                    <span class="badge badge-scheduled" style="margin-left:.5rem">opsiyonel</span>
                </h2>
                <?php if ($isEdit): ?>
                <p class="pe-section-hint">
                    AI mevcut tanımı, kategorini, alias'larını ve kaynaklarını okuyacak;
                    <strong>sıfırdan yeniden yazmadan</strong> eksikleri tamamlayacak,
                    yazım hatalarını düzeltecek, gerekirse emin olduğu yeni kaynaklar
                    ekleyecek. Yazar sesin korunur. Çalıştırınca form alanları
                    geliştirilmiş içerikle değişir — kaydet'e basmadan inceleyebilirsin.
                </p>
                <?php else: ?>
                <p class="pe-section-hint">
                    Terim adını yaz, dilersen 1-2 cümlelik bağlam ekle. AI
                    Türkçe sözlük girdisi (tanım + kategori + alias + kaynaklar)
                    üretir, alanlar otomatik dolar. Yayına almadan önce
                    incele &amp; düzenleyebilirsin. Önerilen kaynakların URL'leri
                    otomatik doğrulanır — ölü olanlar sarı işaretle çıkar.
                </p>
                <?php endif; ?>
                <div class="glossary-ai-grid">
                    <label>
                        <span>
                            <?= $isEdit ? 'Geliştirme yönlendirmesi (opsiyonel)' : 'Bağlam (opsiyonel)' ?>
                        </span>
                        <textarea id="glossary-ai-context"
                                  rows="2" maxlength="800"
                                  placeholder="<?= $isEdit
                                    ? 'Örn: Statik açıdan zayıf, daha çok teknik detay ekle.'
                                    : 'Örn: Konstrüktif değil, sürdürülebilirlik açısından ele al.' ?>"></textarea>
                    </label>
                    <fieldset class="glossary-ai-depth" aria-label="Derinlik">
                        <legend class="visually-hidden">Derinlik</legend>
                        <label><input type="radio" name="ai-depth" value="kisa"> Kısa</label>
                        <label><input type="radio" name="ai-depth" value="orta" checked> Orta</label>
                        <label><input type="radio" name="ai-depth" value="derin"> Derin</label>
                    </fieldset>
                </div>
                <div class="glossary-ai-actions">
                    <button type="button" class="btn btn-primary" id="glossary-ai-run">
                        <?= $isEdit ? 'Mevcut Girdiyi Geliştir' : 'Taslak Üret' ?>
                    </button>
                    <span class="glossary-ai-status muted" id="glossary-ai-status" aria-live="polite"></span>
                </div>
            </section>
            <?php endif; ?>

            <section class="pe-section">
                <h2 class="pe-section-title">Tanım</h2>
                <p class="pe-section-hint">
                    Kapsamlı tanım. HTML kullanabilirsin — başlık (H2/H3/H4),
                    resim, link, alıntı, liste, tablo. <strong>H1 kullanma</strong>
                    (sayfa zaten H1 üretiyor). AI üreteci ansiklopedik yapıda
                    girdi üretir; kısa terimler için manuel düzenleyebilirsin.
                </p>
                <span class="visually-hidden" id="rich-body-label">Tanım</span>
                <textarea id="rich-body"
                          name="definition"
                          rows="14"
                          required minlength="10" maxlength="60000"
                          data-format="html"
                          aria-labelledby="rich-body-label"><?= esc((string) ($item['definition'] ?? '')) ?></textarea>
                <input type="hidden" name="body_format" value="html">
            </section>

            <section class="pe-section">
                <h2 class="pe-section-title">Sınıflandırma</h2>
                <p class="pe-section-hint">Sözlüğü filtrelerken ve ilgili terimleri önerirken kullanılır.</p>
                <label>
                    <span>Kategori</span>
                    <input type="text" name="category" maxlength="80"
                           value="<?= esc((string) ($item['category'] ?? '')) ?>"
                           placeholder="örn: Strüktür, Yapı Elemanı, BIM">
                </label>

                <?php // Q4: Bağlam Etiketi — AI disambiguation hint (drift önleme)
                $_ctxTypes = \App\Services\GlossaryValidationService::CONTEXT_TYPES;
                $_currentCtx = (string) ($item['context_type'] ?? 'diger');
                ?>
                <label>
                    <span>Bağlam Türü <em style="color:var(--cobalt)">*</em></span>
                    <select name="context_type" id="glossary-context-type" required>
                        <?php foreach ($_ctxTypes as $key => $label): ?>
                            <option value="<?= esc($key) ?>" <?= $_currentCtx === $key ? 'selected' : '' ?>>
                                <?= esc($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="muted">
                        <strong>Önemli:</strong> "Döşeme" gibi çok-anlamlı kelimelerde AI'nın yanlış
                        bağlamı yorumlamasını engeller. Örnek: "Döşeme" yapı elemanı seçilirse, AI
                        fayans döşeme eylemini değil zemin/tavan plakını tarif eder.
                    </small>
                </label>

                <label>
                    <span>Eş Anlamlılar (virgülle)</span>
                    <input type="text" name="aliases" maxlength="2000"
                           value="<?= esc((string) ($item['aliases'] ?? '')) ?>"
                           placeholder="örn: konsol, balkon konsolu, cantilever, kragträger">
                    <small class="muted">Yazılarda bu kelimeler geçtiğinde de bu terime tooltip bağlanır. Yabancı dilde karşılıkları da ekleyebilirsin.</small>
                </label>
            </section>

            <?php
            // Kaynak satırlarını çoz: önce JSON (yeni format), olmazsa legacy `;`
            $_refsRaw = (string) ($item['references'] ?? '');
            $_refs    = [];
            if ($_refsRaw !== '') {
                $decoded = json_decode($_refsRaw, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $r) {
                        if (!is_array($r)) continue;
                        $_refs[] = [
                            'text' => (string) ($r['text'] ?? ''),
                            'url'  => (string) ($r['url']  ?? ''),
                        ];
                    }
                } else {
                    // Legacy: noktalı virgül ayraçlı string
                    foreach (array_filter(array_map('trim', explode(';', $_refsRaw))) as $part) {
                        $isUrl = (bool) preg_match('#^https?://#i', $part);
                        $_refs[] = [
                            'text' => $part,
                            'url'  => $isUrl ? $part : '',
                        ];
                    }
                }
            }
            if ($_refs === []) {
                $_refs = [['text' => '', 'url' => '']];
            }
            ?>
            <section class="pe-section">
                <h2 class="pe-section-title">Kaynaklar</h2>
                <p class="pe-section-hint">
                    Tanıma dayanak gösteren akademik kaynaklar. Her satır bir kaynaktır;
                    metin alanı zorunlu, link alanı opsiyoneldir. Link verilirse
                    public sayfada kaynak metni dış bağlantı olarak işlenir.
                </p>
                <div id="references-list" class="pe-faq-list" data-references>
                    <?php foreach ($_refs as $i => $row): ?>
                        <div class="faq-row reference-row" data-ref-row>
                            <input type="text" name="references[<?= (int) $i ?>][text]"
                                   placeholder="Kaynak metni (örn: Tanyeli, U. Modern Türkiye Mimarlığı, İletişim, 2007, s.142)"
                                   maxlength="2000"
                                   value="<?= esc((string) ($row['text'] ?? '')) ?>">
                            <input type="url" name="references[<?= (int) $i ?>][url]"
                                   placeholder="https://... (opsiyonel link)"
                                   maxlength="500"
                                   value="<?= esc((string) ($row['url'] ?? '')) ?>">
                            <button type="button" class="btn btn-ghost reference-remove">Sil</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-ghost" id="reference-add">+ Kaynak ekle</button>
            </section>

            <?php
            // FAQ rows — AI chunk_5 üreteci data.faq array'i döndürür ve
            // doğrudan bu repeater'a yazılır. Public sayfada <details>
            // accordion olarak render edilir + FAQPage schema markup üretir.
            $_faqRaw = (string) ($item['faq_json'] ?? '');
            $_faqs   = [];
            if ($_faqRaw !== '') {
                $decoded = json_decode($_faqRaw, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $f) {
                        if (!is_array($f)) continue;
                        $_faqs[] = [
                            'q' => (string) ($f['q'] ?? ''),
                            'a' => (string) ($f['a'] ?? ''),
                        ];
                    }
                }
            }
            if ($_faqs === []) {
                $_faqs = [['q' => '', 'a' => '']];
            }
            ?>
            <section class="pe-section">
                <h2 class="pe-section-title">Sıkça Sorulan Sorular</h2>
                <p class="pe-section-hint">
                    Konuyla ilgili 3-5 SSS — public sayfada açılır-kapanır
                    accordion olarak görünür. Aynı zamanda Schema.org
                    <code>FAQPage</code> markup'ı otomatik üretilir
                    (Google'da "People Also Ask" rich result eligibility).
                    AI taslak üretirken bu alanı otomatik doldurur.
                </p>
                <div id="faq-list" class="pe-faq-list" data-faq-list>
                    <?php foreach ($_faqs as $i => $row): ?>
                        <div class="faq-row gloss-faq-row" data-faq-row>
                            <input type="text" name="faq[<?= (int) $i ?>][q]"
                                   placeholder="Soru (örn: Brüt beton ile parlatılmış beton arasındaki fark nedir?)"
                                   maxlength="220"
                                   value="<?= esc((string) ($row['q'] ?? '')) ?>">
                            <textarea name="faq[<?= (int) $i ?>][a]"
                                      placeholder="Cevap (2-3 cümle, net)"
                                      rows="2" maxlength="2000"><?= esc((string) ($row['a'] ?? '')) ?></textarea>
                            <button type="button" class="btn btn-ghost faq-remove">Sil</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-ghost" id="faq-add">+ Soru ekle</button>
            </section>

        </div>

        <aside class="post-editor-side">

            <section class="pe-card">
                <h2 class="pe-section-title">Yayınla</h2>
                <label class="checkbox">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" <?= !empty($item['is_active']) ? 'checked' : '' ?>>
                    <span>Aktif (sözlükte görünür)</span>
                </label>
                <p class="pe-helper">Pasif terimler public sayfada görünmez ama yazıdaki tooltip referansları korunur.</p>
                <div class="pe-actions">
                    <button type="submit" class="btn btn-primary btn-block">
                        <?= $isEdit ? 'Güncelle' : 'Ekle' ?>
                    </button>
                </div>
            </section>

            <?php if ($isEdit): ?>
            <?php // Q5: Bağlam Denetimi kartı — drift kontrolü + yeniden denetle
            $_qs = $item['quality_score'] ?? null;
            $_df = !empty($item['drift_flag']);
            $_dr = (string) ($item['drift_reason'] ?? '');
            $_dsf = (string) ($item['drift_suggested_fix'] ?? '');
            $_dca = (string) ($item['drift_checked_at'] ?? '');
            ?>
            <section class="pe-card glossary-drift-card <?= $_df ? 'is-drift' : ($_qs !== null ? 'is-ok' : 'is-unchecked') ?>">
                <h2 class="pe-section-title">
                    Bağlam Denetimi
                    <?php if ($_qs !== null): ?>
                        <span class="glossary-quality-badge"
                              data-state="<?= $_df ? 'dup' : ($_qs >= 75 ? 'ok' : 'warn') ?>"
                              style="margin-left:.4rem">
                            <?= $_df ? '🔴' : ($_qs >= 75 ? '🟢' : '🟡') ?> <?= (int) $_qs ?>/100
                        </span>
                    <?php else: ?>
                        <span class="badge badge-draft" style="margin-left:.4rem">Henüz denetlenmedi</span>
                    <?php endif; ?>
                </h2>
                <?php if ($_df && $_dr !== ''): ?>
                    <p style="background:rgba(176,36,29,.06);border-left:3px solid #B0241D;padding:.55rem .75rem;margin:.5rem 0;font-size:.9rem">
                        <strong style="color:#B0241D">⚠ Drift:</strong> <?= esc($_dr) ?>
                    </p>
                    <?php if ($_dsf !== ''): ?>
                        <p style="background:rgba(31,58,138,.04);border-left:3px solid var(--cobalt);padding:.55rem .75rem;margin:.5rem 0;font-size:.88rem">
                            <strong style="color:var(--cobalt)">💡 Öneri:</strong> <?= esc($_dsf) ?>
                        </p>
                    <?php endif; ?>
                <?php elseif ($_qs !== null && !$_df): ?>
                    <p class="pe-helper">✓ Tanım, seçilen bağlam türünde doğru görünüyor.</p>
                <?php else: ?>
                    <p class="pe-helper">AI tabanlı bir doğrulama yapılmadı. "Denetle" ile drift kontrolü çalıştır.</p>
                <?php endif; ?>
                <?php if ($_dca !== ''): ?>
                    <p class="pe-helper" style="font-size:.72rem;color:var(--ash)">Son denetim: <?= esc($_dca) ?></p>
                <?php endif; ?>
                <form method="post" action="<?= esc(url('/admin/sozluk/' . (int) $item['id'] . '/denetle')) ?>"
                      style="margin-top:.5rem">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-block">
                        <?= $_qs === null ? 'Denetle' : 'Yeniden Denetle' ?>
                    </button>
                </form>
            </section>

            <section class="pe-card">
                <h2 class="pe-section-title">URL</h2>
                <label>
                    <span>Slug</span>
                    <input type="text" name="slug" maxlength="120"
                           value="<?= esc((string) ($item['slug'] ?? '')) ?>">
                </label>
                <p class="pe-helper">Public URL: <code>/sozluk/<?= esc((string) $item['slug']) ?></code></p>
            </section>

            <?php if (function_exists('feature') && feature('auto_internal_link')): ?>
            <section class="pe-card">
                <h2 class="pe-section-title">Otomatik Linkleme</h2>
                <p class="pe-helper">
                    Bu sayfa için AutoLinkService'in skor tablosunu görüntüle —
                    hangi 2 aday seçilmiş, hangileri eşleşmiş ama atlanmış.
                </p>
                <p>
                    <a href="<?= esc(url('/admin/sozluk/' . (int) $item['id'] . '/autolink-debug')) ?>"
                       class="btn btn-ghost btn-block" target="_blank">
                        Skor Tablosunu Aç →
                    </a>
                </p>
            </section>
            <?php endif; ?>
            <?php endif; ?>

            <section class="pe-card">
                <h2 class="pe-section-title">İpucu</h2>
                <p class="pe-helper">
                    Sözlük terimleri yazılarda otomatik tooltip olarak işlenir.
                    Aliasları doğru yazarsan kelime geçtiğinde okur tanımı görmek için
                    altı çizili kelimeye dokunabilir.
                </p>
            </section>

        </aside>
    </div>
</form>

<script src="<?= esc(asset('js/editor.js')) ?>" defer></script>
<script src="<?= esc(asset('js/references-editor.js')) ?>" defer></script>
<script src="<?= esc(asset('js/faq-editor.js')) ?>" defer></script>
<?php if (function_exists('feature') && feature('glossary_ai_enabled')): ?>
<script src="<?= esc(asset('js/glossary-ai.js')) ?>" defer></script>
<?php endif; ?>

<style>
/* H3: Duplicate term uyarı badge'i */
.glossary-dup-status {
    margin: .5rem 0 0;
    padding: .6rem .85rem;
    font-family: var(--mono, monospace);
    font-size: .82rem;
    line-height: 1.4;
    border-left: 3px solid currentColor;
}
.glossary-dup-status[data-state="checking"] {
    color: var(--ash, #5A544D);
    background: rgba(17, 17, 17, .03);
}
.glossary-dup-status[data-state="ok"] {
    color: var(--ok, #2F6A3E);
    background: rgba(47, 106, 62, .07);
}
.glossary-dup-status[data-state="dup"] {
    color: var(--err, #B0241D);
    background: rgba(176, 36, 29, .07);
    font-weight: 600;
}
.glossary-dup-status a {
    color: inherit;
    text-decoration: underline;
    font-weight: 600;
}
/* Form input duplicate olduğunda kırmızı kenar */
#glossary-term.gli-dup-input {
    border-color: var(--err, #B0241D) !important;
    background-color: rgba(176, 36, 29, .04);
}
</style>

<script>
/* H3: Term inputu blur'da AJAX duplicate kontrolü.
   Mevcut terim varsa kırmızı uyarı + submit disable. */
(function () {
    'use strict';
    var termInp = document.getElementById('glossary-term');
    var statusEl = document.getElementById('glossary-dup-status');
    var form = document.getElementById('glossary-form');
    var submitBtns = form ? form.querySelectorAll('button[type="submit"]') : [];
    if (!termInp || !statusEl || !form) return;

    var existingId = parseInt(termInp.getAttribute('data-existing-id') || '0', 10);
    var lastChecked = '';
    var pendingTimer = null;

    var setStatus = function (state, html) {
        statusEl.hidden = false;
        statusEl.setAttribute('data-state', state);
        statusEl.innerHTML = html;
        if (state === 'dup') {
            termInp.classList.add('gli-dup-input');
            submitBtns.forEach(function (b) { b.disabled = true; b.title = 'Duplicate terim — düzelt'; });
        } else {
            termInp.classList.remove('gli-dup-input');
            submitBtns.forEach(function (b) { b.disabled = false; b.title = ''; });
        }
    };
    var clearStatus = function () {
        statusEl.hidden = true;
        statusEl.removeAttribute('data-state');
        statusEl.innerHTML = '';
        termInp.classList.remove('gli-dup-input');
        submitBtns.forEach(function (b) { b.disabled = false; b.title = ''; });
    };

    var check = function () {
        var term = (termInp.value || '').trim();
        if (term.length < 2) { clearStatus(); return; }
        if (term === lastChecked) return;
        lastChecked = term;
        setStatus('checking', '⏳ Kontrol ediliyor…');
        var fd = new FormData();
        // CSRF token: önce <meta>'dan, yoksa form'daki gizli input'tan al
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrfInp  = form.querySelector('input[name="_csrf"]');
        var csrfVal  = csrfMeta ? csrfMeta.getAttribute('content')
                                : (csrfInp ? csrfInp.value : '');
        fd.append('_csrf', csrfVal);
        fd.append('term', term);
        fd.append('exclude_id', String(existingId));
        fetch('<?= esc(url('/admin/sozluk/check-dup')) ?>', {
            method: 'POST',
            credentials: 'same-origin',
            body: fd,
        })
            .then(function (r) {
                if (!r.ok) {
                    // 404 = route yok, 419 = CSRF, 500 = server hatası
                    throw new Error('HTTP ' + r.status);
                }
                return r.json();
            })
            .then(function (json) {
                if (!json || !json.ok) {
                    // Spinner takılı kalmasın
                    clearStatus();
                    return;
                }
                if (json.exists && json.existing) {
                    var e = json.existing;
                    var status = e.is_active ? 'aktif' : 'taslak (pasif)';
                    setStatus('dup',
                        '⚠ Bu terim zaten kayıtlı: <strong>' + (e.term || '').replace(/[<>&]/g, '') + '</strong>'
                        + ' (' + status + ') · '
                        + '<a href="' + e.edit_url + '">Mevcut kaydı düzenle →</a>'
                    );
                } else {
                    setStatus('ok', '✓ Kullanılabilir — bu terim henüz kayıtlı değil.');
                }
            })
            .catch(function (err) {
                // Hata olursa "kontrol ediliyor" sonsuza takılmasın.
                // Endpoint deploy edilmemişse 404 alır — kullanıcıya net mesaj.
                var msg = (err && err.message) || 'bilinmeyen hata';
                if (msg.indexOf('HTTP 404') >= 0) {
                    setStatus('checking',
                        '⚠ Kontrol endpoint\'i bulunamadı. ' +
                        'Sunucuya routes.php + GlossaryController.php yüklenmemiş olabilir.');
                } else if (msg.indexOf('HTTP 419') >= 0) {
                    setStatus('checking', '⚠ Oturum süresi doldu — sayfayı yenileyin.');
                } else {
                    setStatus('checking', '⚠ Kontrol yapılamadı (' + msg + '). Yine de submit edebilirsiniz.');
                }
                // Submit'e engel olma — server-side validation yine duplicate'ı yakalar
                submitBtns.forEach(function (b) { b.disabled = false; b.title = ''; });
            });
    };

    var schedule = function () {
        if (pendingTimer) clearTimeout(pendingTimer);
        pendingTimer = setTimeout(check, 350);
    };

    termInp.addEventListener('input', schedule);
    termInp.addEventListener('blur', check);
    // Submit'i de duplicate'a karşı korumalı yap
    form.addEventListener('submit', function (e) {
        if (statusEl.getAttribute('data-state') === 'dup') {
            e.preventDefault();
            alert('Bu terim zaten kayıtlı — değiştir veya mevcut kaydı düzenle.');
        }
    });
    // İlk yüklemede edit modunda mevcut term varsa sessiz kontrol
    if (termInp.value && termInp.value.trim().length >= 2) {
        check();
    }
})();
</script>
