<?php
/**
 * Projeler portfolyo listesi — Atelier estetiği (harita patterniyle uyumlu).
 *
 * @var array<int,array<string,mixed>> $items
 * @var array<int,array<string,mixed>> $featured
 * @var int $page
 * @var int $total_pages
 * @var int $total
 * @var array<string,int> $role_counts
 * @var array<string,int> $type_counts
 * @var array{min:?int,max:?int} $year_range
 * @var string $active_role
 * @var string $active_type
 */
\App\Core\View::layout('base');

$roleLabels = [
    'arsitekt'   => 'Müellif Mimar',
    'musavir'    => 'Mimari Müşavir',
    'kontrol'    => 'Kontrol',
    'danisman'   => 'Danışman',
    'arastirma'  => 'Araştırmacı',
    'diger'      => 'Diğer',
];
$typeLabels = \App\Models\Project::BUILDING_TYPES;
$activeType = (string) ($active_type ?? '');
$activeRole = (string) ($active_role ?? '');
$activeFilter = $activeType !== '' || $activeRole !== '';
$activeLabel = $activeType !== ''
    ? ($typeLabels[$activeType] ?? $activeType)
    : ($activeRole !== '' ? ($roleLabels[$activeRole] ?? $activeRole) : '');
?>

<?= breadcrumbs_html($breadcrumbs ?? []) ?>

<section class="hero projects-hero">
    <p class="projects-eyebrow">PORTFOLYO · MİMARİ</p>
    <h1><?= esc($h1_title ?? 'Projeler') ?></h1>
    <p class="lead">
        Restorasyon, koruma ve mimari müşavirlik çalışmaları.
        <strong><?= (int) $total ?></strong> proje
        <?php if (!empty($year_range['min']) && !empty($year_range['max'])): ?>
            <span class="muted">·</span>
            <span class="muted"><?= (int) $year_range['min'] ?>–<?= (int) $year_range['max'] ?> arası</span>
        <?php endif; ?>
    </p>
</section>

<?php if (!empty($featured) && !$activeFilter): ?>
<section class="block">
    <h2 class="block-title">Öne Çıkanlar <small>seçili portfolyo yapıları</small></h2>
    <div class="mag-grid mag-grid-fill">
        <?php foreach ($featured as $p):
            $bt = (string) ($p['building_type'] ?? 'diger');
            $btLabel = $typeLabels[$bt] ?? 'Diğer';
            $u = url('/proje/' . $p['slug']);
            $excerpt = (string) ($p['subtitle'] ?? '');
            if ($excerpt === '' && !empty($p['description'])) {
                $excerpt = trim(strip_tags((string) $p['description']));
            }
            $excerpt = mb_substr($excerpt, 0, 140);
        ?>
            <article class="mag-card mag-card-project mag-card-pick">
                <a class="mag-cover <?= empty($p['cover_image']) ? 'mag-cover-empty' : '' ?>" href="<?= esc($u) ?>" title="<?= esc($p['name']) ?>" aria-label="<?= esc($p['name']) ?>">
                    <?php if (!empty($p['cover_image'])): ?>
                        <?= picture_from_path((string) $p['cover_image'], esc($p['name']), ['width' => 800, 'height' => 600]) ?>
                    <?php endif; ?>
                </a>
                <a class="cat-pill" href="<?= esc(url('/projeler?tip=' . urlencode($bt))) ?>" title="<?= esc($btLabel) ?> kategorisi"><?= esc($btLabel) ?></a>
                <h3><a href="<?= esc($u) ?>" title="<?= esc($p['name']) ?>"><?= esc($p['name']) ?></a></h3>
                <?php if ($excerpt !== ''): ?>
                    <p><?= esc($excerpt) ?></p>
                <?php endif; ?>
                <p class="mag-meta mag-meta-project">
                    <span class="mag-meta-ico" aria-hidden="true">◇</span>
                    <span><?= esc($p['location'] ?? '—') ?></span>
                    <?php if (!empty($p['year_completed'])): ?>
                        <span class="sep">·</span>
                        <time><?= (int) $p['year_completed'] ?></time>
                    <?php endif; ?>
                </p>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($type_counts)): ?>
<nav class="projects-filters" aria-label="Yapı tipi filtresi">
    <a class="projects-chip<?= !$activeFilter ? ' is-active' : '' ?>"
       href="<?= esc(url('/projeler')) ?>"
       <?= !$activeFilter ? 'aria-current="true"' : '' ?>>
        Tümü <span class="projects-chip-count"><?= (int) $total ?></span>
    </a>
    <?php foreach ($type_counts as $type => $count): ?>
        <a class="projects-chip<?= $activeType === $type ? ' is-active' : '' ?>"
           href="<?= esc(url('/projeler?tip=' . urlencode($type))) ?>"
           <?= $activeType === $type ? 'aria-current="true"' : '' ?>>
            <?= esc($typeLabels[$type] ?? $type) ?>
            <span class="projects-chip-count"><?= (int) $count ?></span>
        </a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>

<section class="projects-list-section">
    <header class="block-title-wrap">
        <h2 class="block-title">
            <span class="bt-mark">§</span>
            <?php if ($activeFilter): ?>
                <?= esc($activeLabel) ?>
            <?php else: ?>
                Tüm Projeler
            <?php endif; ?>
            <small><?= count($items) ?> kayıt</small>
        </h2>
    </header>

    <?php if (empty($items)): ?>
        <div class="projects-empty">
            <p class="projects-empty-mark">§</p>
            <h3>
                <?php if ($activeFilter): ?>
                    Bu kategoride henüz proje yok
                <?php else: ?>
                    Henüz yayında proje yok
                <?php endif; ?>
            </h3>
            <p>
                <?php if ($activeFilter): ?>
                    Filtreyi temizleyip tüm projeleri görebilirsin.
                <?php else: ?>
                    Admin paneli üzerinden proje ekleyip "Yayında" durumuna alabilirsin.
                <?php endif; ?>
            </p>
            <?php if ($activeFilter): ?>
                <p class="muted">
                    <a href="<?= esc(url('/projeler')) ?>">← Tüm projelere dön</a>
                </p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="mag-grid mag-grid-fill">
            <?php foreach ($items as $_pidx => $p):
                $bt = (string) ($p['building_type'] ?? 'diger');
                $btLabel = $typeLabels[$bt] ?? 'Diğer';
                $u = url('/proje/' . $p['slug']);
                $excerpt = (string) ($p['subtitle'] ?? '');
                if ($excerpt === '' && !empty($p['description'])) {
                    $excerpt = trim(strip_tags((string) $p['description']));
                }
                $excerpt = mb_substr($excerpt, 0, 130);
            ?>
                <article class="mag-card mag-card-project">
                    <a class="mag-cover <?= empty($p['cover_image']) ? 'mag-cover-empty' : '' ?>" href="<?= esc($u) ?>" title="<?= esc($p['name']) ?>" aria-label="<?= esc($p['name']) ?>">
                        <?php if (!empty($p['cover_image'])): ?>
                            <?= picture_from_path((string) $p['cover_image'], esc($p['name']), [
                                'width' => 800, 'height' => 600,
                                'loading' => $_pidx < 3 ? 'eager' : 'lazy',
                            ]) ?>
                        <?php endif; ?>
                    </a>
                    <a class="cat-pill" href="<?= esc(url('/projeler?tip=' . urlencode($bt))) ?>" title="<?= esc($btLabel) ?> kategorisi"><?= esc($btLabel) ?></a>
                    <h3><a href="<?= esc($u) ?>" title="<?= esc($p['name']) ?>"><?= esc($p['name']) ?></a></h3>
                    <?php if ($excerpt !== ''): ?>
                        <p><?= esc($excerpt) ?></p>
                    <?php endif; ?>
                    <p class="mag-meta mag-meta-project">
                        <span class="mag-meta-ico" aria-hidden="true">◇</span>
                        <span><?= esc($p['location'] ?? '—') ?></span>
                        <?php if (!empty($p['year_completed'])): ?>
                            <span class="sep">·</span>
                            <time><?= (int) $p['year_completed'] ?></time>
                        <?php endif; ?>
                    </p>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($total_pages > 1 && !$activeFilter): ?>
            <nav class="projects-pagination" aria-label="Sayfalar">
                <?php if ($page > 1): ?>
                    <a class="projects-page-link" href="?sayfa=<?= $page - 1 ?>">← Önceki</a>
                <?php else: ?>
                    <span class="projects-page-link is-disabled">← Önceki</span>
                <?php endif; ?>
                <span class="projects-page-info">
                    Sayfa <strong><?= $page ?></strong> / <?= $total_pages ?>
                </span>
                <?php if ($page < $total_pages): ?>
                    <a class="projects-page-link" href="?sayfa=<?= $page + 1 ?>">Sonraki →</a>
                <?php else: ?>
                    <span class="projects-page-link is-disabled">Sonraki →</span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php if (\App\Models\Setting::get('building_map_enabled', false, 'features')): ?>
<aside class="projects-map-cta">
    <div class="projects-map-cta-inner">
        <p class="projects-map-cta-eyebrow">COĞRAFYA</p>
        <h2 class="projects-map-cta-title">Yapı Haritası</h2>
        <p class="projects-map-cta-text">
            Coğrafi etiketli projelerin tamamını Türkiye haritası üzerinde gez.
        </p>
        <a class="projects-map-cta-btn" href="<?= esc(url('/harita')) ?>">
            Haritayı Aç →
        </a>
    </div>
</aside>
<?php endif; ?>
