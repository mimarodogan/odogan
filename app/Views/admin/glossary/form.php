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
               value="<?= esc((string) ($item['term'] ?? '')) ?>">
    </header>

    <div class="post-editor-grid">
        <div class="post-editor-main">

            <?php if (!$isEdit && function_exists('feature') && feature('glossary_ai_enabled')): ?>
            <section class="pe-section glossary-ai-section" id="glossary-ai-panel">
                <h2 class="pe-section-title">
                    <span aria-hidden="true">✶</span>
                    AI ile Taslak Üret
                    <span class="badge badge-scheduled" style="margin-left:.5rem">opsiyonel</span>
                </h2>
                <p class="pe-section-hint">
                    Terim adını yaz, dilersen 1-2 cümlelik bağlam ekle. AI
                    Türkçe sözlük girdisi (tanım + kategori + alias + kaynaklar)
                    üretir, alanlar otomatik dolar. Yayına almadan önce
                    incele &amp; düzenleyebilirsin. Önerilen kaynakların URL'leri
                    otomatik doğrulanır — ölü olanlar sarı işaretle çıkar.
                </p>
                <div class="glossary-ai-grid">
                    <label>
                        <span>Bağlam (opsiyonel)</span>
                        <textarea id="glossary-ai-context"
                                  rows="2" maxlength="800"
                                  placeholder="Örn: Konstrüktif değil, sürdürülebilirlik açısından ele al."></textarea>
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
                        Taslak Üret
                    </button>
                    <span class="glossary-ai-status muted" id="glossary-ai-status" aria-live="polite"></span>
                </div>
            </section>
            <?php endif; ?>

            <section class="pe-section">
                <h2 class="pe-section-title">Tanım</h2>
                <p class="pe-section-hint">Kısa, açıklayıcı tanım. HTML kullanabilirsin — resim, link, alıntı, liste.</p>
                <span class="visually-hidden" id="rich-body-label">Tanım</span>
                <textarea id="rich-body"
                          name="definition"
                          rows="14"
                          required minlength="10" maxlength="10000"
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
                <label>
                    <span>Eş Anlamlılar (virgülle)</span>
                    <input type="text" name="aliases" maxlength="500"
                           value="<?= esc((string) ($item['aliases'] ?? '')) ?>"
                           placeholder="örn: konsol, balkon konsolu">
                    <small class="muted">Yazılarda bu kelimeler geçtiğinde de bu terime tooltip bağlanır.</small>
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
            <section class="pe-card">
                <h2 class="pe-section-title">URL</h2>
                <label>
                    <span>Slug</span>
                    <input type="text" name="slug" maxlength="120"
                           value="<?= esc((string) ($item['slug'] ?? '')) ?>">
                </label>
                <p class="pe-helper">Public URL: <code>/sozluk/<?= esc((string) $item['slug']) ?></code></p>
            </section>
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
<?php if (!$isEdit && function_exists('feature') && feature('glossary_ai_enabled')): ?>
<script src="<?= esc(asset('js/glossary-ai.js')) ?>" defer></script>
<?php endif; ?>
