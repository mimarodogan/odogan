<?php
/**
 * Post editor — sağ sidebar: yayın kutusu + kategori + URL + kapak + zamanlama.
 *
 * @var array $post
 * @var array $categories
 * @var array $revisions  Edit mode'da sağlanır (PostRevision::listForPost)
 */
$_postId = (int) ($post['id'] ?? 0);
$_isEdit = $_postId > 0;
$_revisions = $revisions ?? [];
?>
<aside class="post-editor-side">

    <section class="pe-card pe-publish">
        <h2 class="pe-section-title">Yayınla</h2>
        <button class="pe-btn" type="submit" name="action" value="draft">💾 Taslak Olarak Kaydet</button>
        <button class="pe-btn" type="submit" name="action" value="schedule">📅 Zamanla</button>
        <button class="pe-btn pe-btn-primary" type="submit" name="action" value="submit">📤 Onaya Gönder</button>

        <?php
        // Editörün Seçimi — yalnızca feature aktif ve admin/editor için.
        $_eRole = (string) (\App\Services\AuthService::user()['role'] ?? '');
        $_canFeature = function_exists('feature')
            && feature('editors_pick_enabled')
            && in_array($_eRole, ['admin', 'editor'], true);
        if ($_canFeature):
            $_isFeatured = ((int) ($post['featured'] ?? 0)) === 1;
        ?>
        <label class="pe-featured-toggle" title="Anasayfa 'Editörün Seçimi' bloğunda görünür">
            <input type="hidden" name="featured" value="0">
            <input type="checkbox" name="featured" value="1" <?= $_isFeatured ? 'checked' : '' ?>>
            <span>⭐ Editörün Seçimi</span>
        </label>
        <small class="muted" style="display:block;margin-top:.25rem;font-size:.8rem">
            İşaretlenirse anasayfa "Editörün Seçimi" bloğunda öne çıkar.
        </small>
        <?php endif; ?>
    </section>

    <?php if ($_isEdit): ?>
    <section class="pe-card pe-autosave"
             data-autosave-url="<?= esc(url('/panel/yazilar/' . $_postId . '/auto-save')) ?>"
             data-autosave-csrf="<?= esc(csrf_token()) ?>">
        <h2 class="pe-section-title">Otomatik Kayıt</h2>
        <p class="pe-autosave-status" id="pe-autosave-status">
            <span class="dot" aria-hidden="true">◷</span>
            <span class="label">Bekleniyor…</span>
        </p>
        <small class="muted">Yazarken her 30 saniyede bir sessizce kaydedilir.</small>
    </section>
    <?php endif; ?>

    <?php if (function_exists('feature') && feature('co_author_enabled')): ?>
    <?php $_coAuthors = isset($co_authors) && is_array($co_authors) ? $co_authors : []; ?>
    <section class="pe-card pe-coauthors"
             data-coauthor-search-url="<?= esc(url('/panel/yazilar/yazar-ara')) ?>">
        <h2 class="pe-section-title">Co-author / Eş Yazar</h2>
        <p class="pe-section-hint">
            İsim ya da e-posta yazarak ekle. Birincil yazar siz kalırsınız;
            seçilenler ortak yazar olarak atanır.
        </p>
        <div class="pe-coauthor-list" data-coauthor-list>
            <?php foreach ($_coAuthors as $ca): ?>
                <span class="pe-coauthor-chip" data-coauthor-chip>
                    <input type="hidden" name="co_authors[]" value="<?= (int) $ca['id'] ?>">
                    <?= esc((string) $ca['name']) ?>
                    <button type="button" class="pe-coauthor-remove" aria-label="Kaldır">×</button>
                </span>
            <?php endforeach; ?>
        </div>
        <input type="text" class="pe-coauthor-input" data-coauthor-input
               placeholder="İsim veya e-posta yazın…" autocomplete="off">
        <div class="pe-coauthor-results" data-coauthor-results hidden></div>
    </section>
    <?php endif; ?>

    <?php if (function_exists('feature') && feature('outline_panel_enabled')): ?>
    <section class="pe-card pe-outline">
        <h2 class="pe-section-title">📜 İçindekiler</h2>
        <div data-outline-target>
            <p class="muted" style="font-size:.82rem">Başlık eklendiğinde otomatik güncellenir.</p>
        </div>
    </section>
    <?php endif; ?>

    <?php if (function_exists('feature') && feature('internal_link_suggest')): ?>
    <section class="pe-card pe-suggest"
             data-suggest-container
             data-suggest-url="<?= esc(url('/panel/yazilar/onerile')) ?>"
             data-suggest-csrf="<?= esc(csrf_token()) ?>"
             data-suggest-post-id="<?= $_postId ?>">
        <h2 class="pe-section-title">🔗 İlgili Yazılar</h2>
        <p class="pe-section-hint">İçerik yazarken otomatik olarak alakalı yazılar önerilir.</p>
        <div data-suggest-list>
            <p class="muted" style="font-size:.82rem">Yazmaya başla, öneriler burada görünecek…</p>
        </div>
    </section>
    <?php endif; ?>

    <?php if (function_exists('feature') && (feature('seo_score_enabled') || feature('readability_enabled'))): ?>
    <section class="pe-card pe-analyze"
             data-analyze-container
             data-analyze-url="<?= esc(url('/panel/yazilar/analiz')) ?>"
             data-analyze-csrf="<?= esc(csrf_token()) ?>">
        <h2 class="pe-section-title">📊 Yazı Analizi</h2>
        <?php if (feature('seo_score_enabled')): ?>
        <div class="pe-analyze-box" data-seo-box>
            <p class="muted" style="font-size:.85rem">SEO skoru hesaplanıyor…</p>
        </div>
        <?php endif; ?>
        <?php if (feature('readability_enabled')): ?>
        <div class="pe-analyze-box" data-read-box style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--hair)">
            <p class="muted" style="font-size:.85rem">Okunabilirlik hesaplanıyor…</p>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if (function_exists('feature') && feature('sponsored_post_enabled')
        && in_array(($_eRole ?? \App\Services\AuthService::user()['role'] ?? ''), ['admin','editor'], true)): ?>
    <section class="pe-card">
        <h2 class="pe-section-title">📢 Sponsorlu İçerik</h2>
        <p class="pe-section-hint">Etik etiket: sponsor adı görünür. Yazı listelerinde "İlanlı" rozetiyle ayrışır.</p>
        <label style="display:flex;align-items:center;gap:.55rem">
            <input type="hidden" name="is_sponsored" value="0">
            <input type="checkbox" name="is_sponsored" value="1" <?= !empty($post['is_sponsored']) ? 'checked' : '' ?>>
            <span style="font-family:var(--mono);font-size:.7rem;letter-spacing:var(--tracked);text-transform:uppercase;font-weight:600">Bu yazı sponsorlu</span>
        </label>
        <label>
            <span>Sponsor Adı</span>
            <input type="text" name="sponsor_name" maxlength="160" placeholder="örn: ABC Mimarlık"
                   value="<?= esc((string) ($post['sponsor_name'] ?? '')) ?>">
        </label>
        <label>
            <span>Sponsor URL</span>
            <input type="url" name="sponsor_url" maxlength="300" placeholder="https://abc-mimarlik.com"
                   value="<?= esc((string) ($post['sponsor_url'] ?? '')) ?>">
        </label>
    </section>
    <?php endif; ?>

    <?php if (function_exists('feature') && feature('draft_preview_enabled') && $_isEdit): ?>
    <section class="pe-card pe-preview-link">
        <h2 class="pe-section-title">🔗 Önizleme Linki</h2>
        <p class="pe-section-hint">Yayınlanmamış taslağı dış kişilere göstermek için token URL üret.</p>
        <button type="button" class="pe-btn pe-preview-btn"
                data-preview-url="<?= esc(url('/panel/yazilar/' . $_postId . '/onizleme-token')) ?>"
                data-preview-csrf="<?= esc(csrf_token()) ?>">
            Önizleme linki üret/yenile
        </button>
        <input type="text" class="pe-preview-result" readonly placeholder="Token üretildiğinde burada görünür"
               style="margin-top:.5rem;font-family:var(--mono);font-size:.78rem;width:100%;padding:.5rem;border:1px solid var(--hair-2);background:#fff" hidden>
    </section>
    <?php endif; ?>

    <?php if (function_exists('feature') && feature('project_portfolio_enabled')):
        $_projects = \App\Models\Project::listActive(300);
    ?>
    <section class="pe-card pe-project">
        <h2 class="pe-section-title">▣ İlgili Proje</h2>
        <p class="pe-section-hint">Bu yazıyı bir mimari projeye bağla (opsiyonel).</p>
        <label class="pe-label-hidden" for="post-project">
            <span class="visually-hidden">Proje</span>
            <select id="post-project" name="project_id">
                <option value="">— Proje yok —</option>
                <?php foreach ($_projects as $_pr): ?>
                    <option value="<?= (int) $_pr['id'] ?>"
                        <?= ((int) ($post['project_id'] ?? 0) === (int) $_pr['id']) ? 'selected' : '' ?>>
                        <?= esc($_pr['name']) ?><?= $_pr['year_completed'] ? ' · ' . (int) $_pr['year_completed'] : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </section>
    <?php endif; ?>

    <?php if (function_exists('feature') && feature('paywall_enabled')):
        $_pwOn = ((int) ($post['paywall'] ?? 0)) === 1;
    ?>
    <section class="pe-card pe-paywall">
        <h2 class="pe-section-title">🔒 Üye Paywall</h2>
        <label class="pe-featured-toggle" title="Sadece kayıtlı üyelere açık">
            <input type="hidden" name="paywall" value="0">
            <input type="checkbox" name="paywall" value="1" <?= $_pwOn ? 'checked' : '' ?>>
            <span>Üye-only yap</span>
        </label>
        <label>
            <span>Misafire gösterilecek özet (boş = ilk 380 karakter)</span>
            <textarea name="paywall_excerpt" rows="4" maxlength="1000"><?= esc((string) ($post['paywall_excerpt'] ?? '')) ?></textarea>
        </label>
    </section>
    <?php endif; ?>

    <?php if (function_exists('feature') && feature('approval_workflow_enabled') && !empty($post['id']) && ($post['approval_stage'] ?? 'none') === 'none'): ?>
    <section class="pe-card pe-approval">
        <h2 class="pe-section-title">◐ Onay Süreci</h2>
        <p class="pe-section-hint">Yazıyı editör incelemesine gönder.</p>
        <form method="post" action="<?= esc(url('/panel/yazilar/' . (int) $post['id'] . '/gonder')) ?>" onsubmit="return confirm('Yazı editör onayına gönderilecek. Emin misin?');">
            <?= csrf_field() ?>
            <textarea name="note" rows="2" placeholder="Editöre not (opsiyonel)..."></textarea>
            <button type="submit" class="btn btn-primary btn-block">İncelemeye Gönder</button>
        </form>
    </section>
    <?php elseif (function_exists('feature') && feature('approval_workflow_enabled') && !empty($post['approval_stage']) && $post['approval_stage'] !== 'none'): ?>
    <section class="pe-card pe-approval">
        <h2 class="pe-section-title">◐ Onay Süreci</h2>
        <p class="pe-section-hint">Mevcut aşama: <strong><?= esc($post['approval_stage']) ?></strong></p>
    </section>
    <?php endif; ?>

    <?php if (function_exists('feature') && feature('series_enabled')):
        $_seriesList = \App\Models\Series::all(200);
    ?>
    <section class="pe-card pe-series">
        <h2 class="pe-section-title">📚 Dizi</h2>
        <p class="pe-section-hint">Bu yazıyı bir diziye dahil et — bölüm sırasıyla.</p>
        <label class="pe-label-hidden" for="post-series">
            <span class="visually-hidden">Dizi</span>
            <select id="post-series" name="series_id">
                <option value="">— Dizi yok —</option>
                <?php foreach ($_seriesList as $_s): ?>
                    <option value="<?= (int) $_s['id'] ?>"
                        <?= ((int) ($post['series_id'] ?? 0) === (int) $_s['id']) ? 'selected' : '' ?>>
                        <?= esc($_s['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Bölüm Sırası (varsa)</span>
            <input type="number" name="series_position" min="1" max="999"
                   value="<?= esc((string) ($post['series_position'] ?? '')) ?>"
                   placeholder="örn: 3">
            <small class="muted">Boş bırakırsan yayın tarihine göre otomatik sıralanır.</small>
        </label>
    </section>
    <?php endif; ?>

    <section class="pe-card">
        <h2 class="pe-section-title">Kategori</h2>
        <label class="pe-label-hidden" for="post-category">
            <span class="visually-hidden">Kategori</span>
            <select id="post-category" name="category_id" required>
                <option value="">— Seçin —</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"
                        <?= ((int) $post['category_id'] === (int) $c['id']) ? 'selected' : '' ?>>
                        <?= esc($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </section>

    <section class="pe-card">
        <h2 class="pe-section-title">İçerik Tipi (Schema)</h2>
        <p class="pe-section-hint">Google Rich Results için. Çoğu yazı için <strong>Blog Yazısı</strong> doğru seçim.</p>
        <label class="pe-label-hidden" for="post-article-type">
            <span class="visually-hidden">İçerik tipi</span>
            <?php $_at = (string) ($post['article_type'] ?? 'BlogPosting'); ?>
            <select id="post-article-type" name="article_type">
                <option value="BlogPosting" <?= $_at === 'BlogPosting' ? 'selected' : '' ?>>Blog Yazısı (varsayılan)</option>
                <option value="NewsArticle" <?= $_at === 'NewsArticle' ? 'selected' : '' ?>>Haber Makalesi</option>
                <option value="TechArticle" <?= $_at === 'TechArticle' ? 'selected' : '' ?>>Teknik Makale</option>
                <option value="HowTo"       <?= $_at === 'HowTo'       ? 'selected' : '' ?>>Adım-Adım Rehber (HowTo)</option>
                <option value="Article"     <?= $_at === 'Article'     ? 'selected' : '' ?>>Genel Makale</option>
            </select>
            <small class="muted">
                <strong>HowTo</strong> seçilirse aşağıda "Adım Editörü" alanı görünür ve Google adım-adım rich snippet üretir.
            </small>
        </label>
    </section>

    <section class="pe-card">
        <h2 class="pe-section-title">URL</h2>
        <label>
            <span>Slug</span>
            <input type="text" name="slug" maxlength="240" placeholder="otomatik üretilir"
                   value="<?= esc((string) $post['slug']) ?>">
            <small class="muted">Boş bırakırsan başlıktan üretilir.</small>
        </label>
    </section>

    <section class="pe-card">
        <h2 class="pe-section-title">Kapak Görseli</h2>
        <?php if (!empty($post['cover_image'])): ?>
            <div class="pe-cover-preview">
                <img src="<?= esc(url((string) $post['cover_image'])) ?>" alt="Mevcut kapak" loading="lazy">
                <small class="muted"><code><?= esc(basename((string) $post['cover_image'])) ?></code></small>
            </div>
        <?php endif; ?>
        <label>
            <span>Yeni Görsel Yükle</span>
            <input type="file" name="cover_image_file" accept="image/jpeg,image/png,image/webp">
            <small class="muted">JPEG / PNG / WebP. Otomatik 320 / 768 / 1280 boyutlarına optimize edilir.</small>
        </label>
        <label>
            <span>… veya mevcut yol</span>
            <input type="text" name="cover_image" maxlength="255" placeholder="uploads/2026/05/..."
                   value="<?= esc((string) ($post['cover_image'] ?? '')) ?>">
        </label>
    </section>

    <section class="pe-card">
        <h2 class="pe-section-title">Zamanlama</h2>
        <label>
            <span>İleri Tarihli Yayın</span>
            <input type="datetime-local" name="scheduled_at"
                   value="<?php
                   if (($post['status'] ?? '') === 'scheduled' && !empty($post['published_at'])) {
                       echo esc(date('Y-m-d\TH:i', strtotime((string) $post['published_at'])));
                   }
                   ?>">
            <small class="muted">Saat geldiğinde sistem otomatik yayınlar. Boş bırakılırsa zamanlama yok.</small>
        </label>
    </section>

    <?php if ($_isEdit && $_revisions): ?>
    <section class="pe-card pe-revisions">
        <h2 class="pe-section-title">Sürümler (<?= count($_revisions) ?>)</h2>
        <details>
            <summary class="muted" style="cursor:pointer;font-size:.9rem">Geçmiş kayıtları göster</summary>
            <ul class="pe-rev-list" style="margin-top:.75rem;list-style:none;padding:0;display:flex;flex-direction:column;gap:.5rem">
                <?php foreach ($_revisions as $_r): ?>
                <li style="display:flex;align-items:center;gap:.5rem;font-size:.85rem">
                    <span title="<?= $_r['is_autosave'] ? 'Otomatik kayıt' : 'Manuel kayıt' ?>" aria-hidden="true">
                        <?= $_r['is_autosave'] ? '🌀' : '💾' ?>
                    </span>
                    <a href="<?= esc(url('/panel/yazilar/' . $_postId . '/surumler/' . (int) $_r['id'])) ?>"
                       title="Bu sürümü incele">
                        <?= esc(date('d/m/Y H:i', strtotime((string) $_r['created_at']))) ?>
                    </a>
                    <?php if (!empty($_r['user_name'])): ?>
                        <span class="muted">— <?= esc((string) $_r['user_name']) ?></span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <p style="margin-top:.5rem">
                <a href="<?= esc(url('/panel/yazilar/' . $_postId . '/surumler')) ?>" class="muted" style="font-size:.85rem">
                    Tüm sürümleri gör →
                </a>
            </p>
        </details>
    </section>
    <?php endif; ?>

</aside>
