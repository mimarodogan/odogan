<?php
/**
 * Post editor — sol ana kolon: body editor + özet + SSS + tag + SEO + canlı önizleme.
 *
 * @var array $post
 * @var array $faq
 * @var array $tags  Edit mode'da sağlanır (Tag::listForPost)
 */
$_tagsCsv = isset($tags) && is_array($tags)
    ? implode(', ', array_map(fn($t) => (string) ($t['name'] ?? ''), $tags))
    : '';
?>
<main class="post-editor-main">

    <!-- Body editör — kendi border'ı var, çerçevesiz section -->
    <section class="pe-section pe-section-flush">
        <textarea id="rich-body" name="body" rows="14" required minlength="30"
                  data-format="<?= esc((string) ($post['body_format'] ?? 'markdown')) ?>"><?= esc((string) $post['body']) ?></textarea>
        <p class="pe-helper">
            Toolbar'dan biçim ver · görsel butonu galeriden seçer · yazıldığı gibi yayınlanır.
        </p>
    </section>

    <section class="pe-section">
        <h2 class="pe-section-title">Özet</h2>
        <p class="pe-section-hint">Boş bırakırsanız içerik metninden otomatik üretilir. 280 karaktere kadar — listelerde ve sosyal paylaşımlarda görünür.</p>
        <label class="pe-label-hidden" for="post-excerpt"><span class="visually-hidden">Özet</span>
            <textarea id="post-excerpt" name="excerpt" rows="3" maxlength="500"><?= esc((string) ($post['excerpt'] ?? '')) ?></textarea>
        </label>
    </section>

    <section class="pe-section">
        <h2 class="pe-section-title">Sıkça Sorulan Sorular</h2>
        <p class="pe-section-hint">Aşama 4'te <code>FAQPage</code> JSON-LD şemasına dönüşür — arama motoru rich result'larında listelenir.</p>
        <div id="faq-list" class="pe-faq-list">
            <?php foreach ($faq as $i => $row): ?>
                <div class="faq-row">
                    <input type="text" name="faq[<?= $i ?>][q]" placeholder="Soru" maxlength="220"
                           value="<?= esc((string) $row['q']) ?>">
                    <textarea name="faq[<?= $i ?>][a]" placeholder="Cevap (markdown)" rows="2" maxlength="4000"><?= esc((string) $row['a']) ?></textarea>
                    <button type="button" class="btn btn-ghost faq-remove">Sil</button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost" id="faq-add">+ Soru ekle</button>
    </section>

    <?php if (function_exists('feature') && feature('footnotes_enabled')): ?>
    <?php
    $_footnotes = isset($footnotes) && is_array($footnotes) ? $footnotes : [];
    if (!$_footnotes && !empty($post['footnotes_json'])) {
        $_footnotes = \App\Services\FootnoteService::decode((string) $post['footnotes_json']);
    }
    if (!$_footnotes) {
        $_footnotes = [['n' => 1, 'text' => '', 'url' => '']];
    }
    ?>
    <section class="pe-section">
        <h2 class="pe-section-title">Kaynaklar / Dipnot</h2>
        <p class="pe-section-hint">
            Yazı içinde <code>[^1]</code> <code>[^2]</code> şeklinde yer alan
            markerlar otomatik sup link'e dönüşür ve yazı sonunda
            <strong>Kaynaklar</strong> başlıklı liste oluşur. Akademik atıflar
            ve kaynak gösterimi için uygundur.
        </p>
        <div id="footnotes-list" class="pe-faq-list" data-footnotes>
            <?php foreach ($_footnotes as $i => $row): ?>
                <div class="faq-row footnote-row" data-fn-row>
                    <input type="text" name="footnotes[<?= (int) $i ?>][text]"
                           placeholder="Dipnot metni (örn: Tanyeli, Uğur. Modern Türkiye Mimarlığı, İletişim, 2007, s.142)"
                           maxlength="2000"
                           value="<?= esc((string) ($row['text'] ?? '')) ?>">
                    <input type="url" name="footnotes[<?= (int) $i ?>][url]"
                           placeholder="https://... (opsiyonel link)"
                           maxlength="500"
                           value="<?= esc((string) ($row['url'] ?? '')) ?>">
                    <button type="button" class="btn btn-ghost footnote-remove">Sil</button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost" id="footnote-add">+ Kaynak ekle</button>
    </section>
    <?php endif; ?>

    <section class="pe-section">
        <h2 class="pe-section-title">Etiketler</h2>
        <p class="pe-section-hint">
            Virgülle ayırarak yazın: <code>mimari, geleneksel, tarih</code>. En fazla 10 etiket.
            Etiketler <code>/etiket/{slug}</code> arşiv sayfalarında ve "ilgili yazılar" hesaplarında kullanılır.
        </p>
        <label class="pe-label-hidden" for="post-tags">
            <span class="visually-hidden">Etiketler</span>
            <input type="text" id="post-tags" name="tags"
                   maxlength="500"
                   placeholder="mimari, geleneksel mimari, taş işçiliği"
                   value="<?= esc($_tagsCsv) ?>">
        </label>
    </section>

    <?php
    $_howto = [];
    if (!empty($post['howto_steps_json'])) {
        $_howto = is_array($post['howto_steps_json'])
            ? $post['howto_steps_json']
            : (array) json_decode((string) $post['howto_steps_json'], true);
    }
    $_steps = (array) ($_howto['steps'] ?? []);
    if (!$_steps) {
        $_steps = [['name' => '', 'text' => '', 'image' => '']];
    }
    ?>
    <section class="pe-section pe-howto-section" id="howto-section"
             data-visible-when-type="HowTo"
             <?= (($post['article_type'] ?? 'BlogPosting') !== 'HowTo') ? 'hidden' : '' ?>>
        <h2 class="pe-section-title">Adım Editörü <span class="badge badge-scheduled">HowTo</span></h2>
        <p class="pe-section-hint">
            <strong>İçerik Tipi: HowTo</strong> seçildiğinde devreye girer. Adımlar Google'da
            <em>rich card</em> olarak görünür — kullanıcı yazıyı açmadan adımları görebilir.
        </p>

        <div class="pe-howto-meta">
            <label>
                <span>Toplam Süre (dakika)</span>
                <input type="number" name="howto[total_time_minutes]" min="0" max="100000"
                       value="<?= esc((string) ($_howto['total_time_minutes'] ?? '')) ?>"
                       placeholder="örn. 30">
            </label>
            <label>
                <span>Gerekli Malzemeler · her satıra bir tane</span>
                <textarea name="howto[supply]" rows="3" placeholder="AutoCAD lisansı&#10;Mimari plan&#10;Hesap makinesi"><?= esc(implode("\n", (array) ($_howto['supply'] ?? []))) ?></textarea>
            </label>
            <label>
                <span>Kullanılan Araçlar · her satıra bir tane</span>
                <textarea name="howto[tool]" rows="3" placeholder="Bilgisayar&#10;Cetvel"><?= esc(implode("\n", (array) ($_howto['tool'] ?? []))) ?></textarea>
            </label>
        </div>

        <h3 class="pe-howto-steps-title">Adımlar</h3>
        <div id="howto-steps" class="pe-howto-steps">
            <?php foreach ($_steps as $i => $step): ?>
                <div class="pe-howto-step" data-step-index="<?= (int) $i ?>">
                    <div class="pe-howto-step-head">
                        <span class="pe-howto-step-num"><?= (int) $i + 1 ?></span>
                        <input type="text" name="howto[steps][<?= (int) $i ?>][name]"
                               placeholder="Adım başlığı (örn. Arsa analizi)"
                               maxlength="220"
                               value="<?= esc((string) ($step['name'] ?? '')) ?>">
                        <button type="button" class="btn btn-ghost howto-step-remove" title="Bu adımı sil">×</button>
                    </div>
                    <textarea name="howto[steps][<?= (int) $i ?>][text]"
                              rows="3" maxlength="2000"
                              placeholder="Adım açıklaması — kısa, net, eylem odaklı"><?= esc((string) ($step['text'] ?? '')) ?></textarea>
                    <input type="text" name="howto[steps][<?= (int) $i ?>][image]"
                           placeholder="(Opsiyonel) Adım görseli yolu — örn. uploads/2026/05/step1.jpg"
                           maxlength="255"
                           value="<?= esc((string) ($step['image'] ?? '')) ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-ghost" id="howto-add-step">+ Adım ekle</button>
    </section>

    <section class="pe-section" id="seo-section">
        <h2 class="pe-section-title">SEO &amp; Sosyal Medya</h2>
        <p class="pe-section-hint">
            <strong>Hepsini boş bırakabilirsin</strong> — sistem otomatik olarak başlık, özet ve kapak görselini kullanır.
            Sadece <em>farklı</em> bir başlık, açıklama veya paylaşım görseli istiyorsan doldur.
        </p>

        <label>
            <span>Meta Başlık · arama sonuçları için</span>
            <input type="text"
                   name="meta_title"
                   id="seo-meta-title"
                   maxlength="220"
                   placeholder="Boş ise: yazı başlığı kullanılır"
                   data-fallback-from="title"
                   value="<?= esc((string) $post['meta_title']) ?>">
            <small class="muted">İdeal uzunluk: 50–60 karakter. Google sonuç başlığında bunu görür.</small>
        </label>

        <label>
            <span>Meta Açıklama · arama sonuçları için</span>
            <input type="text"
                   name="meta_description"
                   id="seo-meta-desc"
                   maxlength="255"
                   placeholder="Boş ise: özetten kullanılır"
                   data-fallback-from="excerpt"
                   value="<?= esc((string) $post['meta_description']) ?>">
            <small class="muted">İdeal uzunluk: 140–160 karakter. Google sonuç snippet'inde bunu görür.</small>
        </label>

        <label>
            <span>Sosyal Medya Görseli · WhatsApp / LinkedIn / X paylaşımı</span>
            <input type="text"
                   name="og_image"
                   id="seo-og-image"
                   maxlength="255"
                   placeholder="Boş ise: kapak görseli kullanılır"
                   data-fallback-from="cover"
                   value="<?= esc((string) ($post['og_image'] ?? '')) ?>">
            <small class="muted">Yalnız <strong>farklı</strong> bir paylaşım kartı (1200×630 önerilen) istiyorsan doldur. Aksi halde kapak görseli kullanılır.</small>
        </label>

        <!-- Canlı önizleme — Google sonuç kartı -->
        <div class="seo-preview" aria-hidden="true">
            <span class="seo-preview-label">Google önizleme</span>
            <div class="seo-preview-card">
                <div class="seo-preview-url"><?= esc((string) \App\Core\Config::get('APP_URL', 'https://odogan.com.tr')) ?> › <span data-preview="category">kategori</span> › <span data-preview="slug">yazi-slug</span></div>
                <div class="seo-preview-title" data-preview="title">Başlık burada görünür</div>
                <div class="seo-preview-desc"  data-preview="desc">Açıklama burada görünür…</div>
            </div>
        </div>
    </section>

</main>
