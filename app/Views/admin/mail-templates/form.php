<?php
/**
 * Mail şablonu formu — Atelier post-editor patternine uyumlu.
 * @var array $tpl
 */
\App\Core\View::layout('base');

$placeholders = !empty($tpl['variables'])
    ? array_map('trim', explode(',', (string) $tpl['variables']))
    : [];
// Ortak yer tutucular
$commonPlaceholders = ['site_name', 'site_url', 'date_time'];
?>

<section class="hero post-editor-hero">
    <div>
        <p class="post-editor-meta">
            <a href="<?= esc(url('/admin/mail-sablonlari')) ?>" class="muted">← Tüm Şablonlar</a>
        </p>
        <h1><?= esc($tpl['label']) ?></h1>
        <p class="post-editor-meta">
            <span class="badge <?= !empty($tpl['is_active']) ? 'badge-published' : 'badge-draft' ?>">
                <?= !empty($tpl['is_active']) ? 'Aktif' : 'Pasif' ?>
            </span>
            <span class="muted">·</span>
            <code class="muted"><?= esc($tpl['key_name']) ?></code>
        </p>
    </div>
    <?php require dirname(__DIR__, 2) . '/partials/flash.php'; ?>
</section>

<form method="post" action="<?= esc(url('/admin/mail-sablonlari/' . (int) $tpl['id'])) ?>" class="post-editor" id="tpl-form">
    <?= csrf_field() ?>

    <header class="post-editor-head">
        <input type="text"
               name="subject"
               class="post-title-input"
               required maxlength="255"
               placeholder="Mail konusu (Subject)…"
               value="<?= esc((string) $tpl['subject']) ?>">
    </header>

    <div class="post-editor-grid">
        <div class="post-editor-main">

            <?php if (!empty($tpl['description'])): ?>
            <section class="pe-section">
                <h2 class="pe-section-title">Açıklama</h2>
                <p class="pe-section-hint"><?= esc($tpl['description']) ?></p>
            </section>
            <?php endif; ?>

            <?php if (!empty($placeholders) || !empty($commonPlaceholders)): ?>
            <section class="pe-section pe-placeholders">
                <h2 class="pe-section-title">Kullanılabilir Yer Tutucular</h2>
                <p class="pe-section-hint">Mail gönderilirken bu yer tutucular gerçek değerlerle değiştirilir.</p>
                <div class="pe-placeholder-list">
                    <?php foreach ($placeholders as $v): if ($v === '') continue; ?>
                        <code class="pe-placeholder">{<?= esc($v) ?>}</code>
                    <?php endforeach; ?>
                    <?php foreach ($commonPlaceholders as $v): ?>
                        <code class="pe-placeholder pe-placeholder-common">{<?= esc($v) ?>}</code>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <section class="pe-section">
                <h2 class="pe-section-title">Mail Gövdesi</h2>
                <p class="pe-section-hint">HTML, link, liste, başlık kullanabilirsin. Yer tutucuları <code>{user_name}</code> şeklinde yaz.</p>
                <span class="visually-hidden" id="rich-body-label">Mail gövdesi</span>
                <textarea id="rich-body"
                          name="body_html"
                          rows="16"
                          data-format="html"
                          maxlength="20000"
                          aria-labelledby="rich-body-label"><?= esc((string) $tpl['body_html']) ?></textarea>
                <input type="hidden" name="body_format" value="html">
            </section>

        </div>

        <aside class="post-editor-side">

            <section class="pe-card">
                <h2 class="pe-section-title">Yayınla</h2>
                <label class="checkbox">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" <?= !empty($tpl['is_active']) ? 'checked' : '' ?>>
                    <span>Aktif (sistem bu şablonu kullansın)</span>
                </label>
                <p class="pe-helper">Pasif edilirse mail <strong>hiç gönderilmez</strong> — caller fallback hardcoded mail varsa onu kullanır.</p>
                <div class="pe-actions">
                    <button type="submit" class="btn btn-primary btn-block">Güncelle</button>
                </div>
            </section>

            <section class="pe-card">
                <h2 class="pe-section-title">Test Et</h2>
                <p class="pe-helper">Şablonu kendi e-posta adresinize gönderir. Render edilmiş hali (subject + body) ile görürsünüz.</p>
                <div class="pe-actions">
                    <button type="submit"
                            formaction="<?= esc(url('/admin/mail-sablonlari/' . (int) $tpl['id'] . '/test')) ?>"
                            formnovalidate
                            class="btn btn-secondary btn-block">
                        ✉ Test Maili Gönder
                    </button>
                </div>
            </section>

            <section class="pe-card">
                <h2 class="pe-section-title">Anahtar</h2>
                <p class="pe-helper">Bu şablon sistem tarafından <code><?= esc($tpl['key_name']) ?></code> anahtarıyla çağrılır. Kod tarafında değiştirilmez.</p>
            </section>

        </aside>
    </div>
</form>

<script src="<?= esc(asset('js/editor.js')) ?>" defer></script>
<script src="<?= esc(asset('js/media-picker.js')) ?>" defer></script>
<script nonce="<?= esc(csp_nonce()) ?>">
// Yer tutucu chip'ine tıklayınca clipboard'a kopyala (UX iyileştirmesi)
document.querySelectorAll('.pe-placeholder').forEach(function (el) {
    el.addEventListener('click', function (ev) {
        ev.preventDefault();
        var text = el.textContent.trim();
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function () {
                var original = el.textContent;
                el.textContent = '✓ Kopyalandı';
                setTimeout(function () { el.textContent = original; }, 1200);
            });
        }
    });
});
</script>
