<?php
/**
 * Öncesi/Sonrası Slider (Tier 7 → Tier 9 UI).
 *
 * `before_after_json` örnek format:
 *   {
 *       "items": [
 *           {"before": "https://.../before.jpg", "after": "https://.../after.jpg",
 *            "label_before": "1985", "label_after": "2024", "caption": "Cephe restorasyonu"}
 *       ]
 *   }
 *
 * @var array<string,mixed>|null $beforeAfter
 */

if (empty($beforeAfter) || !is_array($beforeAfter)) {
    return;
}
$items = is_array($beforeAfter['items'] ?? null) ? $beforeAfter['items'] : (isset($beforeAfter['before']) ? [$beforeAfter] : []);
if (empty($items)) return;
?>
<section class="before-after-block">
    <?php foreach ($items as $i => $it): ?>
        <?php
        $before = $it['before'] ?? '';
        $after = $it['after'] ?? '';
        if ($before === '' || $after === '') continue;
        ?>
        <figure class="before-after" data-ba-id="<?= $i ?>">
            <div class="before-after-stage">
                <img class="ba-img ba-after" src="<?= e($after) ?>" alt="Sonrası" loading="lazy" decoding="async">
                <div class="ba-clip" style="--ba-cut:50%;">
                    <img class="ba-img ba-before" src="<?= e($before) ?>" alt="Öncesi" loading="lazy" decoding="async">
                </div>
                <button type="button" class="ba-handle" aria-label="Karşılaştır kaydır" style="left:50%;">
                    <span class="ba-handle-icon">‹›</span>
                </button>
                <?php if (!empty($it['label_before'])): ?>
                    <span class="ba-label ba-label-before"><?= e($it['label_before']) ?></span>
                <?php endif; ?>
                <?php if (!empty($it['label_after'])): ?>
                    <span class="ba-label ba-label-after"><?= e($it['label_after']) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($it['caption'])): ?>
                <figcaption class="ba-caption"><?= e($it['caption']) ?></figcaption>
            <?php endif; ?>
        </figure>
    <?php endforeach; ?>
</section>
