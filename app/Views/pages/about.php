<?php
/**
 * /hakkimda — site sahibinin niş manifestosu + bio + üretim göstergeleri.
 *
 * @var array $user
 * @var array $profile
 * @var array $stats     ['posts'=>int, 'projects'=>int, 'glossary'=>int]
 * @var array $recent    son 3 yazı
 * @var string $author_url
 */
\App\Core\View::layout('base');
?>

<?= breadcrumbs_html($breadcrumbs ?? []) ?>

<article class="about-page">
    <header class="about-head">
        <p class="about-eyebrow">Hakkımda</p>
        <h1><?= esc($user['name']) ?></h1>
        <?php if ($profile['headline'] ?? ''): ?>
            <p class="about-headline"><?= esc($profile['headline']) ?></p>
        <?php endif; ?>
        <p class="about-meta muted">
            <?php if ($profile['location'] ?? ''): ?>
                <span><?= esc($profile['location']) ?></span>
            <?php endif; ?>
            <?php if (!empty($profile['social']['website'])): ?>
                <span aria-hidden="true">·</span>
                <a href="<?= esc($profile['social']['website']) ?>" rel="noopener" target="_blank">
                    <?= esc(parse_url($profile['social']['website'], PHP_URL_HOST) ?: 'web') ?>
                </a>
            <?php endif; ?>
        </p>
    </header>

    <?php
    // G2: Manifesto metni admin-yönetilebilir (Setting → pages.about_manifesto_html).
    // HTML serbest (<strong>, <em>, <a>); boşsa varsayılan kopya devreye girer.
    $_aboutManifesto = trim((string) \App\Models\Setting::get('about_manifesto_html', '', 'pages'));
    if ($_aboutManifesto === '') {
        $_aboutManifesto = 'Bursa\'da mimarlık ve inşaat mühendisliğini bir arada uygulayan'
                         . ' bir mimarın, <strong>yapı kültürü, mimari tasarım, yapı teknolojisi'
                         . ' ve kent</strong> üzerine notları.';
    }
    ?>
    <section class="about-manifesto">
        <p class="about-manifesto-lead"><?= $_aboutManifesto /* HTML allowed via admin */ ?></p>
        <?php if ($profile['bio'] ?? ''): ?>
            <div class="about-bio">
                <?= nl2br(esc($profile['bio'])) ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($stats['posts'] > 0 || $stats['projects'] > 0 || $stats['glossary'] > 0): ?>
    <section class="about-stats">
        <?php if ($stats['posts'] > 0): ?>
            <a href="<?= esc(url('/')) ?>" class="about-stat">
                <span class="about-stat-num"><?= number_format($stats['posts'], 0, ',', '.') ?></span>
                <span class="about-stat-lbl">Yazı</span>
            </a>
        <?php endif; ?>
        <?php if ($stats['projects'] > 0 && function_exists('feature') && feature('project_portfolio_enabled')): ?>
            <a href="<?= esc(url('/projeler')) ?>" class="about-stat">
                <span class="about-stat-num"><?= number_format($stats['projects'], 0, ',', '.') ?></span>
                <span class="about-stat-lbl">Proje</span>
            </a>
        <?php endif; ?>
        <?php if ($stats['glossary'] > 0 && function_exists('feature') && feature('glossary_enabled')): ?>
            <a href="<?= esc(url('/sozluk')) ?>" class="about-stat">
                <span class="about-stat-num"><?= number_format($stats['glossary'], 0, ',', '.') ?></span>
                <span class="about-stat-lbl">Sözlük Terimi</span>
            </a>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if (!empty($profile['expertise'])): ?>
    <section class="about-section">
        <h2>Uzmanlık Alanları</h2>
        <ul class="tags about-expertise">
            <?php foreach ($profile['expertise'] as $tag): ?>
                <li><?= esc($tag) ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php if (!empty($profile['education'])): ?>
    <section class="about-section">
        <h2>Eğitim</h2>
        <ul class="timeline about-timeline">
            <?php foreach ($profile['education'] as $edu): ?>
                <li>
                    <strong><?= esc((string) ($edu['institution'] ?? '')) ?></strong>
                    <?php if (!empty($edu['degree'])): ?>— <?= esc($edu['degree']) ?><?php endif; ?>
                    <?php if (!empty($edu['field'])): ?>, <?= esc($edu['field']) ?><?php endif; ?>
                    <?php if (!empty($edu['year_start']) || !empty($edu['year_end'])): ?>
                        <span class="muted">(<?= esc((string) ($edu['year_start'] ?? '')) ?>–<?= esc((string) ($edu['year_end'] ?? '')) ?>)</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php if (!empty($profile['experience'])): ?>
    <section class="about-section">
        <h2>Deneyim</h2>
        <ul class="timeline about-timeline">
            <?php foreach ($profile['experience'] as $exp): ?>
                <li>
                    <strong><?= esc((string) ($exp['title'] ?? '')) ?></strong>
                    <?php if (!empty($exp['org'])): ?>— <?= esc($exp['org']) ?><?php endif; ?>
                    <?php if (!empty($exp['year_start']) || !empty($exp['year_end'])): ?>
                        <span class="muted">(<?= esc((string) ($exp['year_start'] ?? '')) ?>–<?= esc((string) ($exp['year_end'] ?? 'devam ediyor')) ?>)</span>
                    <?php endif; ?>
                    <?php if (!empty($exp['url'])): ?>
                        — <a href="<?= esc($exp['url']) ?>" rel="noopener" target="_blank">↗</a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php if (!empty($recent)): ?>
    <section class="about-section about-recent">
        <h2>Son Yazılarım</h2>
        <ul class="about-recent-list">
            <?php foreach ($recent as $r): ?>
                <li>
                    <a href="<?= esc(url('/' . $r['category_slug'] . '/' . $r['slug'])) ?>">
                        <strong><?= esc($r['title']) ?></strong>
                    </a>
                    <?php if (!empty($r['excerpt'])): ?>
                        <p class="muted"><?= esc(mb_substr((string) $r['excerpt'], 0, 140)) ?>…</p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php if (!empty($profile['social']) || !empty($profile['profiles'])): ?>
    <section class="about-section">
        <h2>Bağlantılar</h2>
        <ul class="about-social">
            <?php foreach (['website','twitter','linkedin','github','instagram','youtube','mastodon'] as $key): ?>
                <?php if (!empty($profile['social'][$key])): ?>
                    <li>
                        <a href="<?= esc($profile['social'][$key]) ?>"
                           rel="noopener me" target="_blank"
                           aria-label="<?= esc(social_icon_label($key)) ?>"
                           title="<?= esc(social_icon_label($key)) ?>">
                            <?= social_icon_svg($key) ?>
                            <span><?= esc(social_icon_label($key)) ?></span>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php foreach ((array) ($profile['profiles'] ?? []) as $p): ?>
                <?php if (!empty($p)): ?>
                    <?php $_host = parse_url($p, PHP_URL_HOST) ?: $p; ?>
                    <li>
                        <a href="<?= esc($p) ?>" rel="noopener me" target="_blank"
                           aria-label="<?= esc($_host) ?>"
                           title="<?= esc($_host) ?>">
                            <?= social_icon_svg('website') ?>
                            <span><?= esc($_host) ?></span>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <footer class="about-footer">
        <p>
            <a href="<?= esc($author_url) ?>" class="muted">→ Tam yazar profili</a>
        </p>
    </footer>
</article>
<?php // Not (G6): Newsletter CTA artık sadece anasayfada gösteriliyor. ?>
