<?php
/**
 * Yapı Haritası — coğrafi etiketli projeleri Leaflet üzerinde göster.
 * Atelier estetiği: breadcrumbs + hero + filter chips + map + liste fallback.
 *
 * @var array<int,array<string,mixed>> $points
 * @var array<string,int>              $role_counts
 * @var array<string,int>              $type_counts
 * @var array{min:?int,max:?int}       $year_range
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
$typeCounts = $type_counts ?? [];

$mapPoints = array_map(static function ($p) use ($roleLabels, $typeLabels) {
    $bt = (string) ($p['building_type'] ?? 'diger');
    return [
        'id'       => (int) $p['id'],
        'name'     => $p['name'],
        'slug'     => $p['slug'],
        'location' => $p['location'],
        'lat'      => (float) $p['lat'],
        'lng'      => (float) $p['lng'],
        'year'     => $p['year_completed'] ? (int) $p['year_completed'] : null,
        'cover'    => $p['cover_image'] ?: null,
        'role'     => $p['role'],
        'roleLabel' => $roleLabels[$p['role']] ?? $p['role'],
        'buildingType' => $bt,
        'buildingTypeLabel' => $typeLabels[$bt] ?? 'Diğer',
        'url'      => url('/proje/' . $p['slug']),
    ];
}, $points);
?>

<?= breadcrumbs_html($breadcrumbs ?? []) ?>

<section class="hero map-hero">
    <p class="map-eyebrow">COĞRAFYA · PROJELER</p>
    <h1>Yapı Haritası</h1>
    <p class="lead">
        Türkiye genelinde mimari proje, restorasyon ve koruma çalışmalarının
        harita üzerinde dağılımı. <strong><?= count($points) ?></strong> yapı kayıtlı.
        <?php if (!empty($year_range['min']) && !empty($year_range['max'])): ?>
            <span class="muted">·</span>
            <span class="muted"><?= (int) $year_range['min'] ?>–<?= (int) $year_range['max'] ?> arası</span>
        <?php endif; ?>
    </p>
</section>

<?php if (!empty($points) && !empty($typeCounts)): ?>
<nav class="map-filters" aria-label="Yapı tipi filtresi" role="group">
    <button type="button" class="map-chip is-active" data-type="all" aria-pressed="true">
        Tümü <span class="map-chip-count"><?= count($points) ?></span>
    </button>
    <?php foreach ($typeCounts as $type => $count): ?>
        <button type="button" class="map-chip" data-type="<?= esc($type) ?>" aria-pressed="false">
            <?= esc($typeLabels[$type] ?? $type) ?>
            <span class="map-chip-count"><?= (int) $count ?></span>
        </button>
    <?php endforeach; ?>
</nav>
<?php endif; ?>

<section class="map-stage">
    <div id="building-map"
         class="building-map"
         data-points='<?= esc(json_encode($mapPoints, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>
        <div class="map-loading">
            <span class="map-loading-dot"></span>
            <span class="map-loading-dot"></span>
            <span class="map-loading-dot"></span>
        </div>
    </div>

    <?php if (empty($points)): ?>
        <div class="map-empty">
            <p class="map-empty-mark">§</p>
            <h2>Henüz haritalanmış yapı yok</h2>
            <p>
                Harita üzerinde gösterim için projenin <strong>yayında</strong>
                olması ve <code>lat / lng</code> koordinatlarının dolu olması gerekir.
            </p>
            <p class="muted">
                <?php if (!empty($is_admin)): ?>
                    <a href="<?= esc(url('/panel/projeler')) ?>">Projeleri yönet →</a>
                <?php else: ?>
                    <a href="<?= esc(url('/projeler')) ?>">Projeler sayfasına git →</a>
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <p class="map-hint">
            <span class="map-hint-icon">↕</span>
            Yakınlaştırmak için haritaya tıklayın · marker'a tıklayarak proje detaylarını görün.
        </p>
    <?php endif; ?>
</section>

<?php if (!empty($points)): ?>
<section class="map-list-section">
    <header class="block-title-wrap">
        <h2 class="block-title">
            <span class="bt-mark">§</span>
            Haritadaki Yapılar
            <small><?= count($points) ?> kayıt</small>
        </h2>
    </header>

    <div class="map-list">
        <?php foreach ($points as $p):
            $bt = (string) ($p['building_type'] ?? 'diger');
        ?>
            <a class="map-list-item"
               href="<?= esc(url('/proje/' . $p['slug'])) ?>"
               data-role="<?= esc($p['role']) ?>"
               data-type="<?= esc($bt) ?>">
                <?php if (!empty($p['cover_image'])): ?>
                    <div class="map-list-cover">
                        <?= picture_from_path((string) $p['cover_image'], esc($p['name']), ['width' => 160, 'height' => 120]) ?>
                    </div>
                <?php else: ?>
                    <div class="map-list-cover map-list-cover-empty">
                        <span>§</span>
                    </div>
                <?php endif; ?>
                <div class="map-list-body">
                    <span class="map-list-role"><?= esc($typeLabels[$bt] ?? 'Diğer') ?></span>
                    <h3 class="map-list-title"><?= esc($p['name']) ?></h3>
                    <p class="map-list-meta">
                        <?= esc($p['location'] ?? '—') ?>
                        <?php if (!empty($p['year_completed'])): ?>
                            <span class="muted">·</span> <?= (int) $p['year_completed'] ?>
                        <?php endif; ?>
                    </p>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
