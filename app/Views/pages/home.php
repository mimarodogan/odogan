<?php \App\Core\View::layout('base'); ?>
<?php
$_homeName    = (string) \App\Models\Setting::get('site_name', \App\Core\Config::get('APP_NAME', 'Otorite Yayın'));
$_homeDesc    = (string) \App\Models\Setting::get('site_description', '');
$_siteTagline = (string) \App\Models\Setting::get('site_tagline', '', 'general');
?>

<?php if (!empty($featured)): $u = url('/' . $featured['category_slug'] . '/' . $featured['slug']); ?>
<article class="hero-featured">
    <a class="hf-image <?= empty($featured['cover_image']) ? 'hf-image-empty' : '' ?>" href="<?= esc($u) ?>" title="<?= esc($featured['title']) ?>" aria-label="<?= esc($featured['title']) ?>">
        <?php if (!empty($featured['cover_image'])): ?>
            <?= picture_from_path(
                (string) $featured['cover_image'],
                esc($featured['title']),
                ['fetchpriority' => 'high', 'sizes' => '(max-width: 768px) 100vw, 1200px', 'width' => 1200, 'height' => 900]
            ) ?>
        <?php endif; ?>
    </a>
    <div class="hf-content">
        <a class="cat-pill" href="<?= esc(url('/' . $featured['category_slug'])) ?>" title="<?= esc($featured['category_name']) ?> kategorisi"><?= esc($featured['category_name']) ?></a>
        <h1 class="hf-headline"><a href="<?= esc($u) ?>" title="<?= esc($featured['title']) ?>"><?= esc($featured['title']) ?></a></h1>
        <?php if (!empty($featured['excerpt'])): ?>
            <p class="excerpt"><?= esc(mb_substr((string) $featured['excerpt'], 0, 220)) ?></p>
        <?php endif; ?>
        <p class="hf-meta">
            <?php if (!empty($featured['author_avatar'])): ?>
                <img class="avatar" src="<?= esc(url($featured['author_avatar'])) ?>"
                     alt="<?= esc($featured['author_name']) ?> avatarı"
                     width="48" height="48"
                     loading="lazy" decoding="async">
            <?php endif; ?>
            <a href="<?= esc(url('/yazar/' . $featured['author_slug'])) ?>" title="<?= esc($featured['author_name']) ?> profili"><strong><?= esc($featured['author_name']) ?></strong></a>
            <span class="sep">·</span>
            <time datetime="<?= esc(date('c', strtotime((string) $featured['published_at']))) ?>"><?= esc(tr_date($featured['published_at'])) ?></time>
            <?php if ($featured['reading_minutes']): ?>
                <span class="sep">·</span> <span><?= (int) $featured['reading_minutes'] ?> dk okuma</span>
            <?php endif; ?>
        </p>
    </div>
</article>
<?php else: ?>
<header class="home-intro" aria-label="Site tanıtımı">
    <h1 class="home-intro-title"><?= esc($_homeName) ?></h1>
    <?php if ($_siteTagline !== '' || $_homeDesc !== ''): ?>
        <p class="home-intro-lead"><?= esc($_siteTagline !== '' ? $_siteTagline : $_homeDesc) ?></p>
    <?php endif; ?>
</header>
<?php endif; ?>

<?php if (!empty($editors_picks ?? [])): ?>
<section class="block block-editors-picks" aria-label="Editörün Seçimi">
    <h2 class="block-title">Editörün Seçimi <small>özenle seçilmiş yazılar</small></h2>
    <div class="mag-grid">
        <?php foreach ($editors_picks as $_idx => $p): $u = url('/' . $p['category_slug'] . '/' . $p['slug']); ?>
            <article class="mag-card mag-card-pick">
                <a class="mag-cover <?= empty($p['cover_image']) ? 'mag-cover-empty' : '' ?>" href="<?= esc($u) ?>" title="<?= esc($p['title']) ?>" aria-label="<?= esc($p['title']) ?>">
                    <?php if (!empty($p['cover_image'])): ?>
                        <?= picture_from_path((string) $p['cover_image'], esc($p['title']), ['width' => 800, 'height' => 600]) ?>
                    <?php endif; ?>
                    <span class="pick-badge" aria-hidden="true">Editör</span>
                </a>
                <a class="cat-pill" href="<?= esc(url('/' . $p['category_slug'])) ?>" title="<?= esc($p['category_name']) ?> kategorisi"><?= esc($p['category_name']) ?></a>
                <h3><a href="<?= esc($u) ?>" title="<?= esc($p['title']) ?>"><?= esc($p['title']) ?></a></h3>
                <?php if (!empty($p['excerpt'])): ?>
                    <p><?= esc(mb_substr((string) $p['excerpt'], 0, 130)) ?></p>
                <?php endif; ?>
                <p class="mag-meta">
                    <?php if (!empty($p['author_avatar'])): ?>
                        <img class="avatar" src="<?= esc(url($p['author_avatar'])) ?>"
                             alt="<?= esc($p['author_name']) ?> avatarı"
                             width="48" height="48"
                             loading="lazy" decoding="async">
                    <?php else: ?>
                        <span class="avatar" aria-hidden="true"></span>
                    <?php endif; ?>
                    <a href="<?= esc(url('/yazar/' . $p['author_slug'])) ?>" title="<?= esc($p['author_name']) ?> profili"><strong><?= esc($p['author_name']) ?></strong></a>
                    <span class="sep">·</span>
                    <time datetime="<?= esc(date('c', strtotime((string) $p['published_at']))) ?>"><?= esc(tr_date($p['published_at'])) ?></time>
                </p>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($recent): ?>
<section class="block">
    <h2 class="block-title">Yeni Yayınlar <small>en son içerikler</small></h2>
    <div class="mag-grid">
        <?php foreach ($recent as $_idx => $p): $u = url('/' . $p['category_slug'] . '/' . $p['slug']); ?>
            <article class="mag-card">
                <a class="mag-cover <?= empty($p['cover_image']) ? 'mag-cover-empty' : '' ?>" href="<?= esc($u) ?>" title="<?= esc($p['title']) ?>" aria-label="<?= esc($p['title']) ?>">
                    <?php if (!empty($p['cover_image'])): ?>
                        <?= picture_from_path((string) $p['cover_image'], esc($p['title']), ['width' => 800, 'height' => 600]) ?>
                    <?php endif; ?>
                </a>
                <a class="cat-pill" href="<?= esc(url('/' . $p['category_slug'])) ?>" title="<?= esc($p['category_name']) ?> kategorisi"><?= esc($p['category_name']) ?></a>
                <h3><a href="<?= esc($u) ?>" title="<?= esc($p['title']) ?>"><?= esc($p['title']) ?></a></h3>
                <?php if (!empty($p['excerpt'])): ?>
                    <p><?= esc(mb_substr((string) $p['excerpt'], 0, 130)) ?></p>
                <?php endif; ?>
                <p class="mag-meta">
                    <?php if (!empty($p['author_avatar'])): ?>
                        <img class="avatar" src="<?= esc(url($p['author_avatar'])) ?>"
                             alt="<?= esc($p['author_name']) ?> avatarı"
                             width="48" height="48"
                             loading="lazy" decoding="async">
                    <?php else: ?>
                        <span class="avatar" aria-hidden="true"></span>
                    <?php endif; ?>
                    <a href="<?= esc(url('/yazar/' . $p['author_slug'])) ?>" title="<?= esc($p['author_name']) ?> profili"><strong><?= esc($p['author_name']) ?></strong></a>
                    <span class="sep">·</span>
                    <time datetime="<?= esc(date('c', strtotime((string) $p['published_at']))) ?>"><?= esc(tr_date($p['published_at'])) ?></time>
                    <?php if ($p['reading_minutes']): ?>
                        <span class="sep">·</span> <span><?= (int) $p['reading_minutes'] ?> dk</span>
                    <?php endif; ?>
                </p>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($trending): ?>
<section class="block">
    <h2 class="block-title">Trend <small>son 30 günün popüleri</small></h2>
    <div class="mag-grid">
        <?php foreach ($trending as $p): $u = url('/' . $p['category_slug'] . '/' . $p['slug']); ?>
            <article class="mag-card">
                <a class="mag-cover <?= empty($p['cover_image']) ? 'mag-cover-empty' : '' ?>" href="<?= esc($u) ?>" title="<?= esc($p['title']) ?>" aria-label="<?= esc($p['title']) ?>">
                    <?php if (!empty($p['cover_image'])): ?>
                        <?= picture_from_path((string) $p['cover_image'], esc($p['title']), ['width' => 800, 'height' => 600]) ?>
                    <?php endif; ?>
                </a>
                <a class="cat-pill" href="<?= esc(url('/' . $p['category_slug'])) ?>" title="<?= esc($p['category_name']) ?> kategorisi"><?= esc($p['category_name']) ?></a>
                <h3><a href="<?= esc($u) ?>" title="<?= esc($p['title']) ?>"><?= esc($p['title']) ?></a></h3>
                <p class="mag-meta">
                    <strong><?= (int) ($p['live_views'] ?? $p['view_count']) ?></strong> okuma
                </p>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($recent_projects)):
    $typeLabels = \App\Models\Project::BUILDING_TYPES;
    // Max 4 proje — blog kartlarıyla aynı sistem (1280px ekranda 4 sütun).
    $homeProjects = array_slice($recent_projects, 0, 4);
?>
<section class="block block-home-projects" aria-label="Son projeler">
    <h2 class="block-title">Son Projeler <small>portfolyoya eklenen yapılar</small></h2>
    <div class="mag-grid mag-grid-fill">
        <?php foreach ($homeProjects as $_pidx => $p):
            $bt = (string) ($p['building_type'] ?? 'diger');
            $btLabel = $typeLabels[$bt] ?? 'Diğer';
            $u = url('/proje/' . $p['slug']);
            // Excerpt: subtitle veya description'dan kısa
            $excerpt = '';
            if (!empty($p['subtitle'])) {
                $excerpt = (string) $p['subtitle'];
            } elseif (!empty($p['description'])) {
                $excerpt = trim(strip_tags((string) $p['description']));
            }
            $excerpt = mb_substr($excerpt, 0, 130);
        ?>
            <article class="mag-card mag-card-project">
                <a class="mag-cover <?= empty($p['cover_image']) ? 'mag-cover-empty' : '' ?>" href="<?= esc($u) ?>" title="<?= esc($p['name']) ?>" aria-label="<?= esc($p['name']) ?>">
                    <?php if (!empty($p['cover_image'])): ?>
                        <?= picture_from_path((string) $p['cover_image'], esc($p['name']), ['width' => 800, 'height' => 600]) ?>
                    <?php endif; ?>
                </a>
                <a class="cat-pill" href="<?= esc(url('/projeler?tip=' . urlencode($bt))) ?>" title="<?= esc($btLabel) ?> kategorisindeki diğer projeler"><?= esc($btLabel) ?></a>
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

<div class="two-col">
    <?php if ($most_read): ?>
    <details class="block-acc" open>
        <summary class="block-title">En Çok Okunanlar</summary>
        <ol class="rank-list">
            <?php foreach ($most_read as $p): ?>
                <li><a href="<?= esc(url('/' . $p['category_slug'] . '/' . $p['slug'])) ?>" title="<?= esc($p['title']) ?>"><?= esc($p['title']) ?></a></li>
            <?php endforeach; ?>
        </ol>
    </details>
    <?php endif; ?>
    <?php if ($most_commented): ?>
    <details class="block-acc" open>
        <summary class="block-title">En Çok Yorumlananlar</summary>
        <ol class="rank-list">
            <?php foreach ($most_commented as $p): ?>
                <li>
                    <a href="<?= esc(url('/' . $p['category_slug'] . '/' . $p['slug'])) ?>" title="<?= esc($p['title']) ?>"><?= esc($p['title']) ?><span class="rank-meta">· <?= (int) $p['comment_count'] ?> yorum</span></a>
                </li>
            <?php endforeach; ?>
        </ol>
    </details>
    <?php endif; ?>
</div>

<?php if ($showcase): ?>
<details class="block-acc block" open>
    <summary class="block-title">Kategoriler</summary>
    <div class="two-col">
    <?php foreach ($showcase as $sc): ?>
        <div class="showcase">
            <h3>
                <a href="<?= esc(url('/' . $sc['category']['slug'])) ?>" title="<?= esc($sc['category']['name']) ?> kategorisi"><?= esc($sc['category']['name']) ?></a>
                <small class="muted" style="font-family:var(--font);font-weight:500;font-size:.85rem"> → <a href="<?= esc(url('/' . $sc['category']['slug'])) ?>" title="<?= esc($sc['category']['name']) ?> · tüm yazılar">tümü</a></small>
            </h3>
            <ul class="showcase-list">
                <?php foreach ($sc['posts'] as $p): ?>
                    <li><a href="<?= esc(url('/' . $sc['category']['slug'] . '/' . $p['slug'])) ?>" title="<?= esc($p['title']) ?>"><?= esc($p['title']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>
    </div>
</details>
<?php endif; ?>

<?php if (!empty($glossary_picks ?? [])): ?>
<section class="home-glossary" aria-labelledby="home-glossary-title">
    <header class="home-glossary-head">
        <h2 id="home-glossary-title" class="home-glossary-title">Bugün Öğren <span class="home-glossary-sub">· Sözlükten</span></h2>
        <a class="home-glossary-all" href="<?= esc(url('/sozluk')) ?>" title="Tüm Mimari Sözlük">Tüm sözlüğe git →</a>
    </header>
    <div class="home-glossary-grid">
        <?php foreach ($glossary_picks as $g):
            $gExcerpt = trim(strip_tags((string) ($g['definition'] ?? '')));
            $gFull    = $gExcerpt;
            if (mb_strlen($gFull) > 140) {
                $cut = mb_substr($gFull, 0, 140);
                $pos = mb_strrpos($cut, ' ');
                $gExcerpt = ($pos !== false ? mb_substr($cut, 0, $pos) : $cut) . '…';
            }
        ?>
            <article class="home-glossary-item">
                <?php if (!empty($g['category'])): ?>
                    <p class="home-glossary-eyebrow"><span aria-hidden="true">§</span> <?= esc(mb_strtoupper((string) $g['category'], 'UTF-8')) ?></p>
                <?php else: ?>
                    <p class="home-glossary-eyebrow"><span aria-hidden="true">§</span> SÖZLÜK</p>
                <?php endif; ?>
                <h3 class="home-glossary-term">
                    <a href="<?= esc(url('/sozluk/' . $g['slug'])) ?>" title="<?= esc($g['term']) ?>"><?= esc($g['term']) ?></a>
                </h3>
                <?php if ($gExcerpt !== ''): ?>
                    <p class="home-glossary-def"><?= esc($gExcerpt) ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php
// Newsletter CTA — anasayfa altı
$cta_title = 'Yeni yazıları kaçırma';
$cta_subtitle = 'Ayda bir bülten — mimarlık, yapı kültürü ve sözlüğe eklenen yeni terimler. İstediğin zaman çık.';
require dirname(__DIR__) . '/partials/newsletter-cta.php';
unset($cta_title, $cta_subtitle);
?>
