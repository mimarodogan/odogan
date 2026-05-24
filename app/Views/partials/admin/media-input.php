<?php
/**
 * Media Input — tek görsel seçme/yükleme alanı.
 *
 * Kullanım:
 *   <?php
 *   require ... . '/partials/admin/media-input.php';
 *   ?>
 *
 * Değişkenler (require öncesi tanımla, sonra unset edilir):
 *   string  $mi_name        Form field adı (örn. "cover_image")
 *   string  $mi_value       Mevcut değer (URL veya path)
 *   string  $mi_label       Label başlığı (örn. "Cover URL")
 *   ?string $mi_hint        Alt yardım metni (opsiyonel)
 *   ?string $mi_placeholder Input placeholder (opsiyonel)
 */
$mi_name        = $mi_name        ?? 'image';
$mi_value       = (string) ($mi_value ?? '');
$mi_label       = $mi_label       ?? 'Görsel';
$mi_hint        = $mi_hint        ?? null;
$mi_placeholder = $mi_placeholder ?? 'https://… veya kütüphaneden seç';

$mi_resolved = $mi_value;
if ($mi_resolved !== '' && !preg_match('#^(https?:)?//#i', $mi_resolved)) {
    $mi_resolved = url($mi_resolved);
}
$mi_id = 'mi_' . preg_replace('/[^a-z0-9_]/i', '_', $mi_name) . '_' . substr(md5($mi_name . microtime()), 0, 4);
?>
<div class="media-input" data-mi data-mi-mode="single">
    <label for="<?= esc($mi_id) ?>">
        <span><?= esc($mi_label) ?></span>
        <input type="text"
               id="<?= esc($mi_id) ?>"
               name="<?= esc($mi_name) ?>"
               value="<?= esc($mi_value) ?>"
               placeholder="<?= esc($mi_placeholder) ?>"
               data-mi-url>
        <?php if ($mi_hint): ?>
            <small class="muted"><?= esc($mi_hint) ?></small>
        <?php endif; ?>
    </label>

    <div class="media-input-preview" data-mi-preview <?= $mi_value === '' ? 'hidden' : '' ?>>
        <?php if ($mi_value !== ''): ?>
            <img src="<?= esc($mi_resolved) ?>" alt="" data-mi-thumb>
        <?php else: ?>
            <img src="" alt="" data-mi-thumb hidden>
        <?php endif; ?>
        <button type="button" class="mi-clear" data-mi-clear title="Temizle">×</button>
    </div>

    <div class="media-input-actions">
        <button type="button" class="btn btn-secondary btn-sm" data-mi-pick>
            <span class="mi-icon">▦</span> Kütüphaneden Seç
        </button>
        <button type="button" class="btn btn-secondary btn-sm" data-mi-upload>
            <span class="mi-icon">↑</span> Bilgisayardan Yükle
        </button>
    </div>
</div>
<?php unset($mi_name, $mi_value, $mi_label, $mi_hint, $mi_placeholder, $mi_resolved, $mi_id); ?>
