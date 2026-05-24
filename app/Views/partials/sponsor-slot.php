<?php
/**
 * Sponsor slot partial — bir slot render eder.
 *
 * @var string $placement — newsletter|sidebar|below_post|header
 */
if (!\App\Models\Setting::get('sponsor_slot_enabled', false, 'features')) return;
$slot = \App\Models\SponsorSlot::pickFor($placement ?? 'sidebar');
if (!$slot) return;
$go = url('/sponsor/git/' . (int) $slot['id']);
?>
<aside class="sponsor-slot sponsor-slot-<?= e($placement ?? 'sidebar') ?>">
    <span class="sponsor-label">Sponsor</span>
    <a class="sponsor-card" href="<?= e($go) ?>" rel="nofollow sponsored noopener" target="_blank">
        <?php if (!empty($slot['image_url'])): ?>
            <img class="sponsor-image" src="<?= e($slot['image_url']) ?>" alt="<?= e($slot['name']) ?>" loading="lazy" decoding="async">
        <?php endif; ?>
        <div class="sponsor-meta">
            <strong class="sponsor-name"><?= e($slot['name']) ?></strong>
            <?php if (!empty($slot['tagline'])): ?>
                <span class="sponsor-tagline"><?= e($slot['tagline']) ?></span>
            <?php endif; ?>
        </div>
    </a>
</aside>
