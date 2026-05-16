<?php
/**
 * A/B Test formu — Atelier post-editor patternine uyumlu.
 * @var array|null $post  Pre-fill için
 */
\App\Core\View::layout('base');
?>

<section class="hero post-editor-hero">
    <div>
        <p class="post-editor-meta">
            <a href="<?= esc(url('/admin/ab-test')) ?>" class="muted">← Tüm Testler</a>
        </p>
        <h1>Yeni A/B Test</h1>
        <p class="post-editor-meta">
            <span class="badge badge-draft">Yeni</span>
            <span class="muted">·</span>
            <span class="muted">CTR ölçümü</span>
        </p>
    </div>
    <?php require dirname(__DIR__, 2) . '/partials/flash.php'; ?>
</section>

<form method="post" action="<?= esc(url('/admin/ab-test/kaydet')) ?>" class="post-editor" id="abtest-form">
    <?= csrf_field() ?>

    <div class="post-editor-grid">
        <div class="post-editor-main">

            <section class="pe-section">
                <h2 class="pe-section-title">Test Edilecek Yazı</h2>
                <p class="pe-section-hint">Yazılar listesinden ID'yi al ve buraya gir.</p>
                <label>
                    <span>Yazı ID</span>
                    <input type="number" name="post_id" required min="1"
                           value="<?= $post ? (int) $post['id'] : '' ?>"
                           placeholder="örn: 142">
                    <?php if ($post): ?>
                        <small class="muted">Seçili: <strong><?= esc($post['title']) ?></strong></small>
                    <?php endif; ?>
                </label>
            </section>

            <section class="pe-section">
                <h2 class="pe-section-title">Varyant A — Orijinal Başlık</h2>
                <p class="pe-section-hint">Test başladığında "kontrol grubu" — yazıdaki mevcut başlık.</p>
                <label class="pe-label-hidden">
                    <span class="visually-hidden">Varyant A</span>
                    <input type="text" name="variant_a" required maxlength="220"
                           value="<?= $post ? esc($post['title']) : '' ?>">
                </label>
            </section>

            <section class="pe-section">
                <h2 class="pe-section-title">Varyant B — Alternatif Başlık</h2>
                <p class="pe-section-hint">Daha çekici, kısa veya somut bir alternatif dene. İyi varyantlar: sayısal ipucu ("5 Yöntem"), soru biçimi, daha duygusal.</p>
                <label class="pe-label-hidden">
                    <span class="visually-hidden">Varyant B</span>
                    <input type="text" name="variant_b" required maxlength="220"
                           placeholder="Alternatif başlığı buraya yaz">
                </label>
            </section>

        </div>

        <aside class="post-editor-side">

            <section class="pe-card">
                <h2 class="pe-section-title">Başlat</h2>
                <p class="pe-helper">Test başladığında trafiğin yarısı her bir varyantı görür. Görüntülenme ve tıklama otomatik sayılır.</p>
                <div class="pe-actions">
                    <button type="submit" class="btn btn-primary btn-block">Testi Başlat</button>
                </div>
            </section>

            <section class="pe-card">
                <h2 class="pe-section-title">Nasıl Çalışır?</h2>
                <ol class="pe-list-ordered">
                    <li>Ziyaretçi sayfaya girer — <code>ab_bucket</code> cookie'sine göre A veya B grubu seçilir.</li>
                    <li>Her görünüm <code>views</code> sayacını, her tıklama <code>clicks</code> sayacını artırır.</li>
                    <li>Anlamlı sonuç için ~200-500 görünüm/varyant bekle.</li>
                    <li>Kazananı seç → orijinal başlığa yazılır, test arşivlenir.</li>
                </ol>
            </section>

            <section class="pe-card">
                <h2 class="pe-section-title">İpuçları</h2>
                <p class="pe-helper">
                    Aynı anda en fazla 3-5 test yürüt. Çok sayıda paralel test
                    sonucu okumayı zorlaştırır. Test bitince mutlaka kazananı seç,
                    aksi halde rastgele varyant gösterilmeye devam eder.
                </p>
            </section>

        </aside>
    </div>
</form>
