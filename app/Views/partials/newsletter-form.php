<?php
/**
 * Newsletter abonelik formu — footer veya dedicated sayfada include edilir.
 */
$brandName = (string) \App\Models\Setting::get('site_name', 'Odogan');
?>
<aside class="newsletter-form" aria-labelledby="newsletter-heading">
    <h3 id="newsletter-heading">Bültene Abone Ol</h3>
    <p class="muted"><?= esc($brandName) ?>'in yeni yazılarından haberdar ol — sadece kaliteli içerik, spam yok.</p>

    <?php if ($s = flash('success_newsletter')): ?>
        <div class="flash flash-success" role="status"><?= esc($s) ?></div>
    <?php endif; ?>
    <?php if ($e = flash('error_newsletter')): ?>
        <div class="flash flash-error" role="alert"><?= esc($e) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= esc(url('/newsletter/abone-ol')) ?>" class="form">
        <?= csrf_field() ?>
        <label>
            <span class="visually-hidden">E-posta</span>
            <input type="email" name="email" required maxlength="190"
                   placeholder="ornek@email.com"
                   autocomplete="email">
        </label>
        <button class="btn btn-primary" type="submit">Abone Ol</button>
        <p class="muted" style="font-size:.75rem;margin-top:.5rem">
            Onay e-postası gönderilecek. Her zaman çıkabilirsin.
        </p>
    </form>
</aside>
