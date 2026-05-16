<?php
/**
 * Üye-only Paywall (Tier 9).
 *
 * Yazıda `paywall=1` ise body yerine bu partial render edilir.
 * @var array<string,mixed> $post
 * @var string $paywallExcerpt — özet metni
 */
?>
<section class="paywall">
    <div class="paywall-excerpt prose">
        <?= nl2br(e($paywallExcerpt)) ?>
    </div>
    <div class="paywall-gate">
        <span class="paywall-icon">§</span>
        <h2 class="paywall-title">Bu yazı üyelere özel</h2>
        <p class="paywall-sub">
            Odogan'da yayınlanan derinlemesine yazıların tamamına erişmek için ücretsiz üye olun
            veya giriş yapın. Üyeler ayrıca özel bültenler ve arşiv erişimi alır.
        </p>
        <div class="paywall-cta">
            <a class="btn btn-primary" href="<?= e(url('/kayit?dest=' . urlencode(canonical()))) ?>">Ücretsiz Üye Ol</a>
            <a class="btn btn-secondary" href="<?= e(url('/giris?dest=' . urlencode(canonical()))) ?>">Giriş Yap</a>
        </div>
        <p class="paywall-note">
            Üyelik kalıcı ve ücretsizdir. Yalnızca arşiv erişimi içindir; reklam yok.
        </p>
    </div>
</section>
