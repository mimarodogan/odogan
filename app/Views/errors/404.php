<?php
/**
 * 404 — Sayfa Bulunamadı
 * Atelier design system: serif typography, cobalt accents, rank-list + showcase-list
 */
$title = 'Sayfa Bulunamadı — 404';
\App\Core\View::layout('base');

// Kullanıcıyı yönlendirmek için popüler + son yazılar
try {
    $popular = \App\Models\Post::trending(5, 30);
    $recent  = \App\Models\Post::recent(4);
} catch (\Throwable) {
    $popular = $recent = [];
}
?>

<section class="err-hero" aria-labelledby="err-heading">

    <div class="err-glyph-wrap" aria-hidden="true">
        <span class="err-glyph">◇</span>
        <span class="err-code">404</span>
    </div>

    <div class="err-body">
        <p class="err-eyebrow">Sayfa Bulunamadı</p>
        <h1 id="err-heading" class="err-title">Aradığınız bağlantı bu sunucuda mevcut değil.</h1>
        <p class="err-lead">Bağlantı taşınmış, silinmiş ya da hiç var olmamış olabilir. Aşağıdan okumaya devam edebilirsiniz.</p>

        <form class="err-search" method="get" action="<?= esc(url('/ara')) ?>" role="search">
            <label class="visually-hidden" for="err-search-q">Site içinde ara</label>
            <input id="err-search-q"
                   type="search"
                   name="q"
                   placeholder="Site içinde arayın…"
                   minlength="2"
                   maxlength="120"
                   autocomplete="off"
                   aria-label="Site içinde ara">
            <button class="btn btn-primary" type="submit">Ara</button>
        </form>

        <nav class="err-nav" aria-label="Sayfa yok — yardımcı bağlantılar">
            <a class="btn btn-primary" href="<?= esc(url('/')) ?>">← Ana Sayfa</a>
            <a class="btn" href="<?= esc(url('/yazarlar')) ?>">Yazarlar</a>
            <a class="btn" href="<?= esc(url('/projeler')) ?>">Projeler</a>
            <a class="btn" href="<?= esc(url('/ara')) ?>">Arama</a>
        </nav>
    </div>

</section>

<?php if ($popular || $recent): ?>
<section class="err-content two-col" aria-label="Okumaya devam edin">

    <?php if ($popular): ?>
    <div>
        <h2 class="block-title">Popüler Yazılar <small>en çok okunanlar</small></h2>
        <ol class="rank-list">
            <?php foreach ($popular as $p): ?>
            <li>
                <a href="<?= esc(url('/' . $p['category_slug'] . '/' . $p['slug'])) ?>"
                   title="<?= esc($p['title']) ?>">
                    <?= esc($p['title']) ?>
                    <?php if (!empty($p['category_name'])): ?>
                        <span class="err-cat"><?= esc($p['category_name']) ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ol>
    </div>
    <?php endif; ?>

    <?php if ($recent): ?>
    <div>
        <h2 class="block-title">Son Yayınlar <small>en yeni içerikler</small></h2>
        <ul class="showcase-list">
            <?php foreach ($recent as $p): ?>
            <li>
                <a href="<?= esc(url('/' . $p['category_slug'] . '/' . $p['slug'])) ?>"
                   title="<?= esc($p['title']) ?>">
                    <?= esc($p['title']) ?>
                </a>
                <?php if (!empty($p['published_at'])): ?>
                    <time class="err-date"
                          datetime="<?= esc(date('c', strtotime((string) $p['published_at']))) ?>">
                        <?= esc(tr_date($p['published_at'])) ?>
                    </time>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

</section>
<?php endif; ?>
