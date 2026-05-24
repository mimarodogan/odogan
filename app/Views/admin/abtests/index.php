<?php \App\Core\View::layout('base'); ?>
<?php /** @var array<int,array<string,mixed>> $items */ ?>
<section class="hero">
    <h1>A/B Başlık Testleri</h1>
    <p class="lead">Aynı yazıya iki alternatif başlık tanımla → trafiği %50/%50 böl, CTR ölç → kazananı uygula.</p>
    <?php require dirname(__DIR__, 2) . '/partials/flash.php'; ?>
    <div class="hero-actions">
        <a class="btn btn-primary" href="<?= esc(url('/admin/ab-test/yeni')) ?>">+ Yeni Test</a>
    </div>
</section>

<?php if (empty($items)): ?>
    <div class="empty-card">
        <p>Aktif veya tamamlanmış A/B testi yok.</p>
        <a class="btn btn-secondary" href="<?= esc(url('/admin/ab-test/yeni')) ?>">İlk testi başlat →</a>
    </div>
<?php else: ?>
    <div class="ab-list">
        <?php foreach ($items as $t): ?>
            <article class="ab-card">
                <header class="ab-card-head">
                    <div>
                        <h2 class="ab-card-title"><?= esc($t['original_title']) ?></h2>
                        <p class="muted">Başlangıç: <?= esc($t['started_at']) ?></p>
                    </div>
                    <?php if ($t['active']): ?>
                        <span class="badge badge-accent">Aktif</span>
                    <?php else: ?>
                        <span class="badge">Kazanan: <strong><?= esc(strtoupper($t['winner'])) ?></strong></span>
                    <?php endif; ?>
                </header>

                <div class="ab-variants">
                    <div class="ab-variant">
                        <span class="ab-variant-letter">A</span>
                        <div class="ab-variant-body">
                            <p class="ab-variant-title"><?= esc($t['variant_a']) ?></p>
                            <p class="ab-variant-stats">
                                <span><strong><?= (int) $t['views_a'] ?></strong> görünüm</span>
                                <span><strong><?= (int) $t['clicks_a'] ?></strong> tık</span>
                                <span class="ab-ctr">CTR <strong>%<?= number_format($t['ctr_a'], 2) ?></strong></span>
                            </p>
                        </div>
                    </div>
                    <div class="ab-variant">
                        <span class="ab-variant-letter">B</span>
                        <div class="ab-variant-body">
                            <p class="ab-variant-title"><?= esc($t['variant_b']) ?></p>
                            <p class="ab-variant-stats">
                                <span><strong><?= (int) $t['views_b'] ?></strong> görünüm</span>
                                <span><strong><?= (int) $t['clicks_b'] ?></strong> tık</span>
                                <span class="ab-ctr">CTR <strong>%<?= number_format($t['ctr_b'], 2) ?></strong></span>
                            </p>
                        </div>
                    </div>
                </div>

                <footer class="ab-card-actions">
                    <?php if ($t['active']): ?>
                        <form method="post" action="<?= esc(url('/admin/ab-test/' . $t['post_id'] . '/sonuc')) ?>" class="ab-decision">
                            <?= csrf_field() ?>
                            <select name="winner" required>
                                <option value="">Kazanan seç…</option>
                                <option value="a">A kazandı</option>
                                <option value="b">B kazandı</option>
                                <option value="tie">Berabere</option>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">Sonuçla</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="<?= esc(url('/admin/ab-test/' . $t['post_id'] . '/sil')) ?>" style="display:inline" onsubmit="return confirm('Bu testi silmek istediğinize emin misiniz?');">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-link btn-link-danger">Sil</button>
                    </form>
                </footer>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
