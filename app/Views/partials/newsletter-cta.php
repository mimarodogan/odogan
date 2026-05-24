<?php
/**
 * Newsletter CTA — ATELIER (architectural editorial) varyantı.
 *
 * Tasarım kararları:
 *   • Gradient / border-radius YOK — tüm site 0 köşe + hairline border kullanır.
 *   • Eyebrow: § BÜLTEN · AYDA BİR — mono uppercase tracked, cobalt.
 *   • Headline: serif monumental (clamp 1.8–2.4rem), -0.025em letter-spacing.
 *   • Body: serif italic muted-ash.
 *   • Form: hairline input + uppercase mono button, cobalt focus halkası.
 *   • Footer mikro-not: KVKK + spam yok bilgisi, mono küçük muted.
 *
 * Customize edilebilir opsiyonlar (include öncesi $cta_* override edilebilir):
 *   $cta_eyebrow  — eyebrow başlığı (varsayılan: "BÜLTEN · AYDA BİR")
 *   $cta_title    — başlık (varsayılan: "Yeni yazıları kaçırma")
 *   $cta_subtitle — alt cümle
 *   $cta_button   — buton metni
 *   $cta_note     — footer mikro-not
 */
$_cta_eyebrow  = $cta_eyebrow  ?? 'BÜLTEN · AYDA BİR';
$_cta_title    = $cta_title    ?? 'Yeni yazıları kaçırma';
$_cta_subtitle = $cta_subtitle ?? 'Mimarlık, yapı kültürü ve sözlüğe eklenen yeni terimler — ayda en fazla bir, hepsi tek e-postada.';
$_cta_button   = $cta_button   ?? 'Abone Ol';
$_cta_note     = $cta_note     ?? 'Spam yok · Tek tık çıkış · KVKK uyumlu';
?>
<section class="newsletter-cta" aria-labelledby="newsletter-cta-heading">
    <div class="newsletter-cta-inner">
        <p class="newsletter-cta-eyebrow"><span aria-hidden="true">§</span> <?= esc($_cta_eyebrow) ?></p>
        <div class="newsletter-cta-copy">
            <h3 id="newsletter-cta-heading" class="newsletter-cta-title"><?= esc($_cta_title) ?></h3>
            <p class="newsletter-cta-lead"><?= esc($_cta_subtitle) ?></p>
        </div>
        <form method="post" action="<?= esc(url('/newsletter/abone-ol')) ?>" class="newsletter-cta-form">
            <?= csrf_field() ?>
            <label class="visually-hidden" for="newsletter-cta-email">E-posta</label>
            <input type="email" id="newsletter-cta-email" name="email" required maxlength="190"
                   placeholder="ornek@email.com" autocomplete="email">
            <button type="submit" class="newsletter-cta-btn"><?= esc($_cta_button) ?> <span aria-hidden="true">→</span></button>
        </form>
        <p class="newsletter-cta-note"><?= esc($_cta_note) ?></p>
        <?php if ($s = flash('success_newsletter')): ?>
            <div class="newsletter-cta-flash newsletter-cta-flash-ok" role="status"><?= esc($s) ?></div>
        <?php endif; ?>
        <?php if ($e = flash('error_newsletter')): ?>
            <div class="newsletter-cta-flash newsletter-cta-flash-err" role="alert"><?= esc($e) ?></div>
        <?php endif; ?>
    </div>
</section>
