<?php
/**
 * @var string $title
 * @var string $q
 * @var array  $results   FULLTEXT sonuçlar
 * @var array  $popular   0 sonuç fallback
 */
\App\Core\View::layout('base');
?>
<section class="hero">
    <h1>Arama</h1>
    <form method="get" action="<?= esc(url('/ara')) ?>" class="form" style="max-width:560px">
        <label class="visually-hidden" for="search-q">Arama</label>
        <input id="search-q" type="search" name="q" value="<?= esc($q) ?>"
               placeholder="Aramak istediğiniz kelimeyi yazın…"
               autofocus minlength="2" maxlength="120" required
               style="width:100%;font-size:1.1rem;padding:.75rem 1rem">
        <button class="btn btn-primary" type="submit" style="margin-top:.5rem">Ara</button>
    </form>
</section>

<?php if ($q === ''): ?>
    <p class="muted">Yukarıdaki kutuya en az 2 karakterlik bir kelime girin.</p>

<?php elseif (!$results): ?>
    <section>
        <p class="muted">
            "<strong><?= esc($q) ?></strong>" için sonuç bulunamadı.
        </p>
        <?php if ($popular): ?>
            <h2>Belki bunlar ilginizi çeker</h2>
            <div class="mag-grid">
                <?php foreach ($popular as $p): $u = url('/' . $p['category_slug'] . '/' . $p['slug']); ?>
                    <article class="mag-card">
                        <a class="mag-cover <?= empty($p['cover_image']) ? 'mag-cover-empty' : '' ?>"
                           href="<?= esc($u) ?>" title="<?= esc($p['title']) ?>">
                            <?php if (!empty($p['cover_image'])): ?>
                                <?= picture_from_path((string) $p['cover_image'], esc($p['title']), ['width' => 800, 'height' => 600]) ?>
                            <?php endif; ?>
                        </a>
                        <h3><a href="<?= esc($u) ?>" title="<?= esc($p['title']) ?>"><?= esc($p['title']) ?></a></h3>
                        <p class="mag-meta">
                            <time datetime="<?= esc(date('c', strtotime((string) $p['published_at']))) ?>">
                                <?= esc(tr_date($p['published_at'])) ?>
                            </time>
                            <?php if ($p['reading_minutes']): ?>
                                <span class="sep">·</span> <?= (int) $p['reading_minutes'] ?> dk
                            <?php endif; ?>
                        </p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

<?php else: ?>
    <p class="muted"><?= count($results) ?> sonuç · "<strong><?= esc($q) ?></strong>"</p>
    <section class="mag-grid">
        <?php foreach ($results as $p): $u = url('/' . $p['category_slug'] . '/' . $p['slug']); ?>
            <article class="mag-card">
                <a class="mag-cover <?= empty($p['cover_image']) ? 'mag-cover-empty' : '' ?>"
                   href="<?= esc($u) ?>" title="<?= esc($p['title']) ?>">
                    <?php if (!empty($p['cover_image'])): ?>
                        <?= picture_from_path((string) $p['cover_image'], esc($p['title']), ['width' => 800, 'height' => 600]) ?>
                    <?php endif; ?>
                </a>
                <h3><a href="<?= esc($u) ?>" title="<?= esc($p['title']) ?>"><?= esc($p['title']) ?></a></h3>
                <?php if (!empty($p['excerpt'])): ?>
                    <p><?= esc(mb_substr((string) $p['excerpt'], 0, 150)) ?></p>
                <?php endif; ?>
                <p class="mag-meta">
                    <a href="<?= esc(url('/' . $p['category_slug'])) ?>" title="<?= esc($p['category_name']) ?>">
                        <?= esc($p['category_name']) ?>
                    </a>
                    <span class="sep">·</span>
                    <a href="<?= esc(url('/yazar/' . $p['author_slug'])) ?>" title="<?= esc($p['author_name']) ?>">
                        <?= esc($p['author_name']) ?>
                    </a>
                    <span class="sep">·</span>
                    <time datetime="<?= esc(date('c', strtotime((string) $p['published_at']))) ?>">
                        <?= esc(tr_date($p['published_at'])) ?>
                    </time>
                    <?php if ($p['reading_minutes']): ?>
                        <span class="sep">·</span> <?= (int) $p['reading_minutes'] ?> dk
                    <?php endif; ?>
                </p>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
