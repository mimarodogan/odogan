<?php \App\Core\View::layout('base'); ?>
<?php
$statusLabels = [
    'published' => ['Yayında', 'badge-published'],
    'pending'   => ['Onay Bekliyor', 'badge-pending'],
    'draft'     => ['Taslak', 'badge-draft'],
    'scheduled' => ['Zamanlandı', 'badge-scheduled'],
    'rejected'  => ['Reddedildi', 'badge-rejected'],
    'archived'  => ['Arşiv', 'badge-archived'],
];
$commentStatusLabels = [
    'pending'  => ['Onay Bekliyor', 'badge-pending'],
    'approved' => ['Onaylı', 'badge-published'],
    'spam'     => ['Spam', 'badge-rejected'],
    'rejected' => ['Reddedildi', 'badge-rejected'],
];
$roleLabels = [
    'admin'  => 'Yönetici',
    'editor' => 'Editör',
    'author' => 'Yazar',
    'member' => 'Üye',
];
?>

<section class="hero">
    <h1>Yönetim Paneli</h1>
    <p class="lead">Hoş geldin <strong><?= esc($user['name'] ?? '') ?></strong>.</p>
    <?php require dirname(__DIR__) . '/partials/flash.php'; ?>

    <?php if (!empty($widgets)): ?>
    <form method="get" action="<?= esc(url('/admin')) ?>" class="dash-period-form">
        <label for="dash-period" class="muted" style="font-size:.85rem">Dönem:</label>
        <select id="dash-period" name="period" onchange="this.form.submit()">
            <option value="7"  <?= (int) ($period ?? 7) === 7  ? 'selected' : '' ?>>Son 7 gün</option>
            <option value="30" <?= (int) ($period ?? 7) === 30 ? 'selected' : '' ?>>Son 30 gün</option>
            <option value="90" <?= (int) ($period ?? 7) === 90 ? 'selected' : '' ?>>Son 90 gün</option>
            <option value="0"  <?= (int) ($period ?? 7) === 0  ? 'selected' : '' ?>>Tüm zamanlar</option>
        </select>
        <noscript><button class="btn" type="submit">Güncelle</button></noscript>
    </form>
    <?php endif; ?>
</section>

<section class="dash-stats">
    <article class="dash-stat">
        <span class="ds-label">Toplam Yazı</span>
        <span class="ds-value"><?= (int) $stats['posts_total'] ?></span>
        <span class="ds-meta">
            <?= (int) $stats['posts_published'] ?> yayında ·
            <?php if ($stats['posts_pending'] > 0): ?>
                <a href="<?= esc(url('/editor/onay')) ?>"><strong><?= (int) $stats['posts_pending'] ?></strong> onay bekliyor</a> ·
            <?php endif; ?>
            <?= (int) $stats['posts_draft'] ?> taslak
            <?php if ($stats['posts_scheduled'] > 0): ?>
                · <?= (int) $stats['posts_scheduled'] ?> zamanlanmış
            <?php endif; ?>
        </span>
        <a class="ds-link" href="<?= esc(url('/panel/yazilar')) ?>">Yazıları aç →</a>
    </article>

    <article class="dash-stat">
        <span class="ds-label">Toplam Kullanıcı</span>
        <span class="ds-value"><?= (int) $stats['users_total'] ?></span>
        <span class="ds-meta">
            <?= (int) $stats['users_admin'] ?> yönetici ·
            <?= (int) $stats['users_editor'] ?> editör ·
            <?= (int) $stats['users_author'] ?> yazar
            <?php if ($stats['users_member'] > 0): ?>
                · <?= (int) $stats['users_member'] ?> üye
            <?php endif; ?>
        </span>
        <a class="ds-link" href="<?= esc(url('/admin/kullanicilar')) ?>">Kullanıcıları aç →</a>
    </article>

    <article class="dash-stat">
        <span class="ds-label">Yorumlar</span>
        <span class="ds-value"><?= (int) $stats['comments_total'] ?></span>
        <span class="ds-meta">
            <?php if ($stats['comments_pending'] > 0): ?>
                <a href="<?= esc(url('/editor/yorumlar')) ?>"><strong><?= (int) $stats['comments_pending'] ?></strong> onay bekliyor</a>
            <?php else: ?>
                Tümü işlendi.
            <?php endif; ?>
        </span>
        <a class="ds-link" href="<?= esc(url('/editor/yorumlar')) ?>">Moderasyona git →</a>
    </article>

    <article class="dash-stat">
        <span class="ds-label">Kategoriler</span>
        <span class="ds-value"><?= (int) $stats['categories_total'] ?></span>
        <span class="ds-meta">Silo URL temeli.</span>
        <a class="ds-link" href="<?= esc(url('/admin/kategoriler')) ?>">Kategorileri aç →</a>
    </article>
</section>

<?php if (!empty($widgets)): ?>
<section class="dash-widgets" aria-label="<?= esc($widgets['period_label']) ?>">
    <h2 class="dw-section-title"><?= esc($widgets['period_label']) ?> · Aktivite</h2>

    <div class="dw-delta-grid">
        <article class="dw-delta">
            <span class="dw-label">Yeni Yazı</span>
            <span class="dw-value"><?= (int) $widgets['posts_recent'] ?></span>
            <small class="muted"><?= (int) $widgets['posts_published_recent'] ?> yayında</small>
        </article>
        <article class="dw-delta">
            <span class="dw-label">Yeni Yorum</span>
            <span class="dw-value"><?= (int) $widgets['comments_recent'] ?></span>
            <?php if ($widgets['comments_pending'] > 0): ?>
                <small class="muted"><a href="<?= esc(url('/editor/yorumlar')) ?>"><?= (int) $widgets['comments_pending'] ?> onay bekliyor</a></small>
            <?php endif; ?>
        </article>
        <article class="dw-delta">
            <span class="dw-label">Yeni Üye</span>
            <span class="dw-value"><?= (int) $widgets['users_recent'] ?></span>
            <?php if (!empty($widgets['pending_authors'])): ?>
                <small class="muted"><?= (int) $widgets['pending_authors'] ?> yazar başvurusu bekliyor</small>
            <?php endif; ?>
        </article>
        <?php if (isset($widgets['subs_total'])): ?>
        <article class="dw-delta">
            <span class="dw-label">Abone</span>
            <span class="dw-value"><?= (int) $widgets['subs_total'] ?></span>
            <small class="muted">+<?= (int) $widgets['subs_recent'] ?> bu dönem</small>
        </article>
        <?php endif; ?>
    </div>

    <?php if (!empty($widgets['spark_posts'])): ?>
    <div class="dw-spark-row">
        <article class="dw-spark">
            <h3 class="dw-spark-title">Günlük yazı akışı</h3>
            <?php
            $sp = $widgets['spark_posts'];
            $maxP = max(1, max(array_column($sp, 'count')));
            ?>
            <div class="dw-bars">
                <?php foreach ($sp as $d): $h = (int) round(($d['count'] / $maxP) * 100); ?>
                    <span class="dw-bar" style="height:<?= max(2, $h) ?>%"
                          title="<?= esc($d['date']) ?>: <?= (int) $d['count'] ?> yazı"></span>
                <?php endforeach; ?>
            </div>
            <p class="muted dw-spark-note"><?= count($sp) ?> gün · max <?= $maxP ?>/gün</p>
        </article>

        <article class="dw-spark">
            <h3 class="dw-spark-title">Günlük yorum akışı</h3>
            <?php
            $sc = $widgets['spark_comments'];
            $maxC = max(1, max(array_column($sc, 'count')));
            ?>
            <div class="dw-bars dw-bars-alt">
                <?php foreach ($sc as $d): $h = (int) round(($d['count'] / $maxC) * 100); ?>
                    <span class="dw-bar" style="height:<?= max(2, $h) ?>%"
                          title="<?= esc($d['date']) ?>: <?= (int) $d['count'] ?> yorum"></span>
                <?php endforeach; ?>
            </div>
            <p class="muted dw-spark-note"><?= count($sc) ?> gün · max <?= $maxC ?>/gün</p>
        </article>
    </div>
    <?php endif; ?>

    <div class="dw-leaders">
        <?php if (!empty($widgets['top_authors'])): ?>
        <article class="dw-leader">
            <h3 class="dw-spark-title">En aktif yazarlar</h3>
            <ol class="dw-leader-list">
                <?php foreach ($widgets['top_authors'] as $a): ?>
                    <li>
                        <a href="<?= esc(url('/yazar/' . $a['slug'])) ?>"><?= esc($a['name']) ?></a>
                        <span class="muted">— <?= (int) $a['post_count'] ?> yazı, <?= number_format((float) $a['view_total'], 0, ',', '.') ?> okuma</span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </article>
        <?php endif; ?>

        <?php if (!empty($widgets['top_commented'])): ?>
        <article class="dw-leader">
            <h3 class="dw-spark-title">En yorum alan yazılar</h3>
            <ol class="dw-leader-list">
                <?php foreach ($widgets['top_commented'] as $p): ?>
                    <li>
                        <a href="<?= esc(url('/' . $p['category_slug'] . '/' . $p['slug'])) ?>"><?= esc($p['title']) ?></a>
                        <span class="muted">— <?= (int) $p['comment_count'] ?> yorum</span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </article>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<section class="dash-recent">
    <article class="dr-col">
        <h2 class="dr-title">Son Yazılar</h2>
        <?php if ($recentPosts): ?>
            <ul class="dr-list">
                <?php foreach ($recentPosts as $p): ?>
                    <?php
                    $st = $statusLabels[$p['status']] ?? [ucfirst((string) $p['status']), 'badge-draft'];
                    $url = (!empty($p['category_slug']) && !empty($p['slug']))
                        ? url('/' . $p['category_slug'] . '/' . $p['slug'])
                        : url('/panel/yazilar');
                    ?>
                    <li class="dr-item">
                        <a class="dr-main" href="<?= esc($url) ?>"><?= esc($p['title']) ?></a>
                        <span class="dr-sub">
                            <?php if (!empty($p['author_name'])): ?>
                                <a href="<?= esc(url('/yazar/' . $p['author_slug'])) ?>"><?= esc($p['author_name']) ?></a> ·
                            <?php endif; ?>
                            <?= esc(tr_date($p['published_at'] ?? $p['created_at'])) ?>
                            · <span class="badge <?= esc($st[1]) ?>"><?= esc($st[0]) ?></span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="muted">Henüz yazı yok.</p>
        <?php endif; ?>
    </article>

    <article class="dr-col">
        <h2 class="dr-title">Son Yorumlar</h2>
        <?php if ($recentComments): ?>
            <ul class="dr-list">
                <?php foreach ($recentComments as $c): ?>
                    <?php
                    $st = $commentStatusLabels[$c['status']] ?? [ucfirst((string) $c['status']), 'badge-draft'];
                    $authorName = $c['user_name'] ?? $c['author_name'] ?? 'Anonim';
                    $postUrl = (!empty($c['category_slug']) && !empty($c['post_slug']))
                        ? url('/' . $c['category_slug'] . '/' . $c['post_slug'])
                        : null;
                    ?>
                    <li class="dr-item">
                        <span class="dr-main">
                            <strong><?= esc($authorName) ?></strong>:
                            <?= esc(mb_substr((string) $c['body'], 0, 90)) ?><?= mb_strlen((string) $c['body']) > 90 ? '…' : '' ?>
                        </span>
                        <span class="dr-sub">
                            <?php if ($postUrl): ?>
                                <a href="<?= esc($postUrl) ?>"><?= esc(mb_substr((string) $c['post_title'], 0, 50)) ?></a> ·
                            <?php endif; ?>
                            <?= esc(tr_date($c['created_at'])) ?>
                            · <span class="badge <?= esc($st[1]) ?>"><?= esc($st[0]) ?></span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="muted">Henüz yorum yok.</p>
        <?php endif; ?>
    </article>

    <article class="dr-col">
        <h2 class="dr-title">Son Üyeler</h2>
        <?php if ($recentUsers): ?>
            <ul class="dr-list">
                <?php foreach ($recentUsers as $u): ?>
                    <li class="dr-item">
                        <a class="dr-main" href="<?= esc(url('/yazar/' . $u['slug'])) ?>"><?= esc($u['name']) ?></a>
                        <span class="dr-sub">
                            <?= esc($roleLabels[$u['role']] ?? ucfirst((string) $u['role'])) ?>
                            · <?= esc(tr_date($u['created_at'])) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="muted">Henüz üye yok.</p>
        <?php endif; ?>
    </article>
</section>
