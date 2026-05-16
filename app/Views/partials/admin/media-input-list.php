<?php
/**
 * Media Input List — galeri / çoklu görsel seçme/yükleme alanı.
 * Textarea'ya satır satır URL veya media ID kaydedilir; her kayıt için
 * sürüklenebilir thumbnail strip + temizle butonu görünür.
 *
 * Değişkenler:
 *   string $ml_name   Form field adı (örn. "gallery")
 *   string $ml_value  Mevcut değer — satır başına URL veya ID
 *   string $ml_label  Label başlığı
 *   ?string $ml_hint  Alt yardım metni
 */
$ml_name  = $ml_name  ?? 'gallery';
$ml_value = (string) ($ml_value ?? '');
$ml_label = $ml_label ?? 'Galeri Görselleri';
$ml_hint  = $ml_hint  ?? 'Her satıra bir URL veya Medya Kütüphanesi ID. Sürükleyerek sırala.';
$ml_id    = 'ml_' . preg_replace('/[^a-z0-9_]/i', '_', $ml_name) . '_' . substr(md5($ml_name . microtime()), 0, 4);

$ml_items = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $ml_value))));
?>
<div class="media-input-list" data-mi data-mi-mode="list">
    <label class="media-input-list-head">
        <span><?= esc($ml_label) ?></span>
        <textarea id="<?= esc($ml_id) ?>"
                  name="<?= esc($ml_name) ?>"
                  rows="3"
                  data-mi-list-text
                  placeholder="https://… veya media ID (her satıra bir)"><?= esc($ml_value) ?></textarea>
        <small class="muted"><?= esc($ml_hint) ?></small>
    </label>

    <div class="media-input-list-strip" data-mi-strip>
        <?php foreach ($ml_items as $i => $item):
            $thumbSrc = preg_match('#^https?://#i', $item)
                ? $item
                : (ctype_digit($item) ? '' : url($item)); // numeric → media-picker resolve sonradan
        ?>
            <div class="ml-tile" data-ml-tile data-value="<?= esc($item) ?>">
                <?php if ($thumbSrc !== ''): ?>
                    <img src="<?= esc($thumbSrc) ?>" alt="">
                <?php else: ?>
                    <div class="ml-tile-id">#<?= esc($item) ?></div>
                <?php endif; ?>
                <button type="button" class="ml-tile-remove" title="Kaldır">×</button>
            </div>
        <?php endforeach; ?>
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
<?php unset($ml_name, $ml_value, $ml_label, $ml_hint, $ml_id, $ml_items); ?>
