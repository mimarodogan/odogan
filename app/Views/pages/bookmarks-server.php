<?php \App\Core\View::layout('base'); ?>
<section class="page-saved" style="margin-bottom:5rem">
    <header class="page-saved-head">
        <h1>Kaydedilen Yazılar</h1>
        <p class="muted">Hesabınla senkron — tüm cihazlarda görünür.</p>
    </header>

    <?php if (empty($bookmarks)): ?>
        <p class="muted" style="text-align:center;padding:3rem 0">Henüz kayıtlı yazı yok. Yazı sayfasındaki ★ butonuna tıklayarak ekleyebilirsin.</p>
    <?php else: ?>
        <div class="mag-grid">
            <?php foreach ($bookmarks as $b): $u = url('/' . $b['category_slug'] . '/' . $b['slug']); ?>
                <article class="mag-card">
                    <?php if (!empty($b['cover_image'])): ?>
                        <a class="mag-cover" href="<?= esc($u) ?>">
                            <img src="<?= esc(url($b['cover_image'])) ?>" alt="<?= esc($b['title']) ?>" loading="lazy" decoding="async" width="800" height="600">
                        </a>
                    <?php else: ?>
                        <a class="mag-cover mag-cover-empty" href="<?= esc($u) ?>"></a>
                    <?php endif; ?>
                    <?php if (!empty($b['category_name'])): ?>
                        <a class="cat-pill" href="<?= esc(url('/' . $b['category_slug'])) ?>"><?= esc($b['category_name']) ?></a>
                    <?php endif; ?>
                    <h3><a href="<?= esc($u) ?>"><?= esc($b['title']) ?></a></h3>
                    <?php if (!empty($b['excerpt'])): ?>
                        <p><?= esc(mb_substr((string) $b['excerpt'], 0, 130)) ?></p>
                    <?php endif; ?>
                    <p class="mag-meta">
                        <a href="<?= esc(url('/yazar/' . $b['author_slug'])) ?>"><strong><?= esc($b['author_name']) ?></strong></a>
                        <span class="sep">·</span>
                        <time datetime="<?= esc(date('c', strtotime((string) $b['published_at']))) ?>"><?= esc(tr_date($b['published_at'])) ?></time>
                    </p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
