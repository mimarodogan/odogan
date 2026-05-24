<?php \App\Core\View::layout('base'); ?>
<?= breadcrumbs_html($breadcrumbs ?? []) ?>
<article class="author-profile">
    <header class="author-head">
        <h1><?= esc($author['name']) ?></h1>
        <?php if ($profile['headline'] ?? ''): ?>
            <p class="lead"><?= esc($profile['headline']) ?></p>
        <?php endif; ?>
        <p class="muted">
            <?= esc(ucfirst((string) $author['role'])) ?>
            <?php if ($profile['location'] ?? ''): ?>· <?= esc($profile['location']) ?><?php endif; ?>
        </p>
    </header>

    <?php if ($profile['bio'] ?? ''): ?>
    <section class="author-section">
        <h2>Hakkında</h2>
        <p><?= nl2br(esc($profile['bio'])) ?></p>
    </section>
    <?php endif; ?>

    <?php if (!empty($profile['expertise'])): ?>
    <section class="author-section">
        <h2>Uzmanlık Alanları</h2>
        <ul class="tags">
        <?php foreach ($profile['expertise'] as $tag): ?>
            <li><?= esc($tag) ?></li>
        <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php if (!empty($profile['education'])): ?>
    <section class="author-section">
        <h2>Eğitim</h2>
        <ul class="timeline">
        <?php foreach ($profile['education'] as $edu): ?>
            <li>
                <strong><?= esc($edu['institution']) ?></strong>
                <?php if ($edu['degree']): ?>— <?= esc($edu['degree']) ?><?php endif; ?>
                <?php if ($edu['field']): ?>, <?= esc($edu['field']) ?><?php endif; ?>
                <?php if ($edu['year_start'] || $edu['year_end']): ?>
                    <span class="muted">(<?= esc((string) ($edu['year_start'] ?? '')) ?>–<?= esc((string) ($edu['year_end'] ?? '')) ?>)</span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php if (!empty($profile['languages'])): ?>
    <section class="author-section">
        <h2>Diller</h2>
        <ul class="tags">
        <?php foreach ($profile['languages'] as $lang): ?>
            <li><?= esc($lang['name'] ?: $lang['code']) ?><?php if ($lang['level']): ?> · <?= esc($lang['level']) ?><?php endif; ?></li>
        <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php if (!empty($profile['certificates'])): ?>
    <section class="author-section">
        <h2>Sertifikalar</h2>
        <ul class="timeline">
            <?php foreach ($profile['certificates'] as $c): ?>
                <li>
                    <strong><?= esc((string) $c['name']) ?></strong>
                    <?php if (!empty($c['issuer'])): ?> — <?= esc((string) $c['issuer']) ?><?php endif; ?>
                    <?php if (!empty($c['year'])): ?>
                        <span class="muted">(<?= (int) $c['year'] ?>)</span>
                    <?php endif; ?>
                    <?php if (!empty($c['url'])): ?>
                        — <a href="<?= esc((string) $c['url']) ?>" target="_blank" rel="noopener" title="Sertifika/Belgeyi yeni sekmede aç">Belgeyi gör</a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php if (!empty($portfolio)): ?>
    <section class="author-section">
        <h2>Katkıda Bulunduğu İçerikler</h2>
        <ul class="timeline">
            <?php foreach ($portfolio as $p): ?>
                <li>
                    <a href="<?= esc(url('/' . $p['category_slug'] . '/' . $p['slug'])) ?>" title="<?= esc($p['title']) ?>">
                        <strong><?= esc($p['title']) ?></strong>
                    </a>
                    <span class="muted">
                        — <?= esc($p['category_name']) ?>
                        · <time datetime="<?= esc(date('c', strtotime((string) $p['published_at']))) ?>"><?= esc(tr_date($p['published_at'])) ?></time>
                        <?php if ($p['reading_minutes']): ?>· <?= (int) $p['reading_minutes'] ?> dk<?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php $sameAs = array_filter($profile['social'] ?? []); ?>
    <?php if ($sameAs): ?>
    <section class="author-section">
        <h2>Bağlantılar</h2>
        <ul class="links">
        <?php foreach ($sameAs as $key => $url_): ?>
            <li><a href="<?= esc($url_) ?>" rel="me noopener" target="_blank" title="<?= esc(ucfirst($key)) ?> profilini yeni sekmede aç"><?= esc(ucfirst($key)) ?></a></li>
        <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>
</article>
<!--
  Person JSON-LD üretimi Aşama 4'te SchemaService aracılığıyla buradan
  beslenecektir; profile_json bu sayfanın ana veri kaynağıdır.
-->
