<?php
/**
 * @var bool $ok
 * @var string $error
 * @var bool $unsub_mode
 */
\App\Core\View::layout('base');
$unsubMode = !empty($unsub_mode);
?>
<section class="hero">
    <?php if ($ok && $unsubMode): ?>
        <h1>Çıkış Tamamlandı</h1>
        <p class="lead">Bültenden çıkışınız işlendi. Bizi terketmenize üzüldük.</p>
    <?php elseif ($ok): ?>
        <h1>✓ Abonelik Onaylandı</h1>
        <p class="lead">
            Tebrikler! Artık abone listemizdesiniz. Yeni yazılar yayınlandığında
            haberdar olacaksınız.
        </p>
    <?php else: ?>
        <h1>Bir Sorun Oluştu</h1>
        <p class="lead muted"><?= esc($error) ?: 'Geçersiz veya süresi dolmuş bağlantı.' ?></p>
    <?php endif; ?>

    <p style="margin-top:2rem">
        <a class="btn btn-primary" href="<?= esc(url('/')) ?>" title="Ana sayfaya dön">← Ana Sayfa</a>
    </p>
</section>
