<?php
/**
 * Sözleşme formu — Atelier post-editor patternine uyumlu.
 * @var array $doc
 */
\App\Core\View::layout('base');

$bodyLen = mb_strlen((string) ($doc['body_html'] ?? ''));
?>

<section class="hero post-editor-hero">
    <div>
        <p class="post-editor-meta">
            <a href="<?= esc(url('/admin/sozlesmeler')) ?>" class="muted">← Tüm Sözleşmeler</a>
        </p>
        <h1>Sözleşmeyi Düzenle</h1>
        <p class="post-editor-meta">
            <span class="badge <?= !empty($doc['is_active']) ? 'badge-published' : 'badge-draft' ?>">
                <?= !empty($doc['is_active']) ? 'Yayında' : 'Gizli' ?>
            </span>
            <span class="muted">·</span>
            <span class="muted">Sürüm v<?= (int) $doc['version'] ?></span>
            <span class="muted">·</span>
            <code class="muted">/sozlesmeler/<?= esc($doc['slug']) ?></code>
        </p>
    </div>
    <?php require dirname(__DIR__, 2) . '/partials/flash.php'; ?>
</section>

<form method="post" action="<?= esc(url('/admin/sozlesmeler/' . (int) $doc['id'])) ?>" class="post-editor" id="legal-form">
    <?= csrf_field() ?>

    <header class="post-editor-head">
        <input type="text"
               name="title"
               class="post-title-input"
               required minlength="3" maxlength="200"
               placeholder="Sözleşme başlığı…"
               value="<?= esc((string) $doc['title']) ?>">
    </header>

    <div class="post-editor-grid">
        <div class="post-editor-main">

            <section class="pe-section">
                <h2 class="pe-section-title">Metin</h2>
                <p class="pe-section-hint">Başlık, paragraf, liste, link, alıntı kullanabilirsin. Body değiştiğinde sürüm otomatik bir artar — eski sürümü kabul etmiş kullanıcılardan yeniden onay istenir.</p>
                <span class="visually-hidden" id="rich-body-label">Sözleşme metni</span>
                <textarea id="rich-body"
                          name="body_html"
                          rows="22"
                          data-format="html"
                          maxlength="50000"
                          aria-labelledby="rich-body-label"><?= esc((string) ($doc['body_html'] ?? '')) ?></textarea>
                <input type="hidden" name="body_format" value="html">
            </section>

        </div>

        <aside class="post-editor-side">

            <section class="pe-card">
                <h2 class="pe-section-title">Yayınla</h2>
                <label class="checkbox">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" <?= !empty($doc['is_active']) ? 'checked' : '' ?>>
                    <span>Aktif (public sayfa açık)</span>
                </label>
                <p class="pe-helper">Pasif edilirse <code>/sozlesmeler/<?= esc($doc['slug']) ?></code> 404 döner. Footer linki de gizlenir.</p>
                <div class="pe-actions">
                    <button type="submit" class="btn btn-primary btn-block">Güncelle</button>
                </div>
            </section>

            <section class="pe-card">
                <h2 class="pe-section-title">Sürüm</h2>
                <div class="pe-stat-row">
                    <div class="pe-stat">
                        <span>Mevcut</span>
                        <strong>v<?= (int) $doc['version'] ?></strong>
                    </div>
                    <div class="pe-stat">
                        <span>Karakter</span>
                        <strong><?= number_format($bodyLen, 0, ',', '.') ?></strong>
                    </div>
                </div>
                <p class="pe-helper">Metin değiştiğinde sürüm <strong>v<?= (int) $doc['version'] + 1 ?></strong>'e çıkar ve kullanıcılardan yeniden kabul istenir.</p>
            </section>

            <section class="pe-card">
                <h2 class="pe-section-title">URL</h2>
                <p class="pe-helper">Sözleşme şu adreste yayında:</p>
                <p>
                    <a href="<?= esc(url('/sozlesmeler/' . $doc['slug'])) ?>"
                       target="_blank" rel="noopener"
                       class="pe-url-link">
                        /sozlesmeler/<?= esc($doc['slug']) ?> ↗
                    </a>
                </p>
            </section>

        </aside>
    </div>
</form>

<script src="<?= esc(asset('js/editor.js')) ?>" defer></script>
<script src="<?= esc(asset('js/media-picker.js')) ?>" defer></script>
