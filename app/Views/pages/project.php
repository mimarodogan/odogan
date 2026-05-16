<?php
/** @var array<string,mixed> $project */
/** @var array<int,array<string,mixed>> $posts */
\App\Core\View::layout('base');

$gallery = is_array($project['gallery_json'] ?? null) ? $project['gallery_json'] : [];
$partners = is_array($project['partners_json'] ?? null) ? $project['partners_json'] : [];
$tags = is_array($project['tags_json'] ?? null) ? $project['tags_json'] : [];
$links = is_array($project['links_json'] ?? null) ? $project['links_json'] : [];
$team = is_array($project['team_json'] ?? null) ? $project['team_json'] : [];

$roleLabels = [
    'arsitekt' => 'Müellif Mimar',
    'musavir' => 'Mimari Müşavir',
    'kontrol' => 'Kontrol',
    'danisman' => 'Danışman',
    'arastirma' => 'Araştırmacı',
    'diger' => 'Katkı',
];
$typeLabels = \App\Models\Project::BUILDING_TYPES;
$btKey = (string) ($project['building_type'] ?? 'diger');
$btLabel = $typeLabels[$btKey] ?? 'Diğer';

// Künye için sahibin ismini DB'den çek (eğer user_id varsa)
$ownerName = null;
if (!empty($project['user_id'])) {
    try {
        $ownerName = \App\Models\User::findById((int) $project['user_id'])['name'] ?? null;
    } catch (\Throwable) { /* tablo veya kolon yoksa boş */ }
}

$teamGroupMeta = [
    'architects'  => 'Mimari Ekip',
    'engineers'   => 'Mühendislik',
    'consultants' => 'Danışmanlar',
];
$hasAnyTeam = false;
foreach (array_keys($teamGroupMeta) as $g) {
    if (!empty($team[$g])) { $hasAnyTeam = true; break; }
}
?>

<?= breadcrumbs_html($breadcrumbs ?? []) ?>

<article class="project-show">
    <header class="project-hero">
        <span class="project-hero-eyebrow"><?= esc(mb_strtoupper($btLabel, 'UTF-8')) ?></span>
        <h1 class="project-hero-title"><?= esc($project['name']) ?></h1>
        <?php if (!empty($project['subtitle'])): ?>
            <p class="project-hero-sub"><?= esc($project['subtitle']) ?></p>
        <?php endif; ?>
        <ul class="project-meta-list">
            <?php if (!empty($project['location'])): ?>
                <li><span>Lokasyon</span><strong><?= e($project['location']) ?></strong></li>
            <?php endif; ?>
            <?php if (!empty($project['year_completed']) || !empty($project['year_started'])): ?>
                <li>
                    <span>Yıl</span>
                    <strong>
                        <?= $project['year_started'] ? (int) $project['year_started'] : '—' ?>
                        <?= $project['year_completed'] ? '– ' . (int) $project['year_completed'] : '' ?>
                    </strong>
                </li>
            <?php endif; ?>
            <?php if (!empty($project['surface_m2'])): ?>
                <li><span>Yüzölçümü</span><strong><?= number_format((int) $project['surface_m2'], 0, ',', '.') ?> m²</strong></li>
            <?php endif; ?>
            <?php if (!empty($project['client'])): ?>
                <li><span>Müşteri / Kurum</span><strong><?= e($project['client']) ?></strong></li>
            <?php endif; ?>
        </ul>
    </header>

    <?php if (!empty($project['cover_image'])): ?>
        <figure class="project-cover">
            <?= picture_from_path(
                (string) $project['cover_image'],
                e($project['name']),
                ['fetchpriority' => 'high', 'sizes' => '(max-width: 768px) 100vw, 960px']
            ) ?>
        </figure>
    <?php endif; ?>

    <?php if (!empty($project['description'])): ?>
        <div class="project-body prose">
            <?= $project['description'] /* Sanitized at save time / trusted */ ?>
        </div>
    <?php endif; ?>

    <?php
    // Gallery'yi normalize et (URL'leri çöz, geçersizleri at)
    $galleryItems = [];
    foreach ($gallery as $g) {
        $url = $g['url']
            ?? (!empty($g['media_id'])
                ? (\App\Models\MediaResolver::byId((int) $g['media_id'])['url'] ?? null)
                : null);
        if ($url) {
            $galleryItems[] = [
                'url' => $url,
                'caption' => $g['caption'] ?? $project['name'],
            ];
        }
    }
    ?>
    <?php if (!empty($galleryItems)): ?>
        <section class="project-gallery">
            <header class="project-gallery-head">
                <h2 class="block-title"><span class="bt-mark">§</span> Galeri <small><?= count($galleryItems) ?> görsel</small></h2>
                <button type="button"
                        class="project-gallery-open"
                        data-gallery-open="proje-galerisi"
                        title="Galeriyi tam ekran aç">
                    ⛶ Tam Ekran
                </button>
            </header>
            <div class="project-gallery-grid">
                <?php foreach ($galleryItems as $i => $g): ?>
                    <a href="<?= esc($g['url']) ?>"
                       class="project-gallery-item js-gallery"
                       data-gallery="proje-galerisi"
                       data-caption="<?= esc($g['caption']) ?>"
                       aria-label="Görsel <?= $i + 1 ?> / <?= count($galleryItems) ?>">
                        <img src="<?= esc($g['url']) ?>"
                             alt="<?= esc($g['caption']) ?>"
                             loading="lazy" decoding="async">
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($hasAnyTeam || $ownerName || !empty($project['client'])): ?>
        <section class="project-credits" aria-labelledby="proje-kunyesi">
            <header class="project-credits-head">
                <h2 class="block-title" id="proje-kunyesi"><span class="bt-mark">§</span> Künye</h2>
                <p class="project-credits-hint">Projeyi gerçekleştiren ekip</p>
            </header>

            <div class="project-credits-grid">
                <?php if ($ownerName): ?>
                <div class="credit-group">
                    <h3 class="credit-group-title">Müellif</h3>
                    <ul class="credit-list">
                        <li class="credit-item">
                            <span class="credit-name"><?= e($ownerName) ?></span>
                            <span class="credit-title"><?= esc($roleLabels[$project['role'] ?? 'arsitekt'] ?? 'Müellif Mimar') ?></span>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>

                <?php foreach ($teamGroupMeta as $gKey => $gLabel):
                    $members = $team[$gKey] ?? [];
                    if (empty($members)) continue;
                ?>
                <div class="credit-group">
                    <h3 class="credit-group-title"><?= esc($gLabel) ?></h3>
                    <ul class="credit-list">
                        <?php foreach ($members as $m):
                            $mName  = (string) ($m['name']  ?? '');
                            $mTitle = (string) ($m['title'] ?? '');
                            $mUrl   = (string) ($m['url']   ?? '');
                            if ($mName === '') continue;
                        ?>
                            <li class="credit-item">
                                <?php if ($mUrl !== ''): ?>
                                    <a href="<?= e($mUrl) ?>" target="_blank" rel="noopener nofollow" class="credit-name credit-link"
                                       title="<?= e($mName) ?> — dış site">
                                        <?= e($mName) ?> <span class="credit-link-ico" aria-hidden="true">↗</span>
                                    </a>
                                <?php else: ?>
                                    <span class="credit-name"><?= e($mName) ?></span>
                                <?php endif; ?>
                                <?php if ($mTitle !== ''): ?>
                                    <span class="credit-title"><?= e($mTitle) ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>

                <?php if (!empty($project['client'])): ?>
                <div class="credit-group">
                    <h3 class="credit-group-title">İşveren</h3>
                    <ul class="credit-list">
                        <li class="credit-item">
                            <span class="credit-name"><?= e($project['client']) ?></span>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($partners)): ?>
        <section class="project-side">
            <h2 class="block-title"><span class="bt-mark">§</span> Ortak / Katkı</h2>
            <ul class="project-partners">
                <?php foreach ($partners as $partner): ?>
                    <li><?= e((string) $partner) ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if (!empty($posts)): ?>
        <section class="block">
            <h2 class="block-title"><span class="bt-mark">§</span> İlgili Yazılar</h2>
            <div class="mag-grid">
                <?php foreach ($posts as $p): ?>
                    <a class="mag-card" href="<?= e(url('/' . $p['category_slug'] . '/' . $p['slug'])) ?>">
                        <?php if (!empty($p['cover_image'])): ?>
                            <div class="mag-card-cover"><?= picture_from_path((string) $p['cover_image'], e($p['title']), ['width' => 800, 'height' => 600]) ?></div>
                        <?php endif; ?>
                        <div class="mag-card-body">
                            <span class="mag-card-eyebrow"><?= e($p['category_name']) ?></span>
                            <h3 class="mag-card-title"><?= e($p['title']) ?></h3>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($links)): ?>
        <section class="project-links">
            <h2 class="block-title"><span class="bt-mark">§</span> Kaynaklar</h2>
            <ul>
                <?php foreach ($links as $l): ?>
                    <li>
                        <a href="<?= e($l['url']) ?>" rel="nofollow noopener" target="_blank">
                            <?= e($l['label'] ?? $l['url']) ?> ↗
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</article>
