<?php \App\Core\View::layout('base'); ?>

<?= breadcrumbs_html($breadcrumbs ?? []) ?>

<article class="post">
    <header>
        <p class="card-meta">
            <a href="<?= esc(url('/' . $post['category_slug'])) ?>" title="<?= esc($post['category_name']) ?> kategorisi"><?= esc($post['category_name']) ?></a>
        </p>

        <?php if (!empty($series_info) && !empty($series_nav)): ?>
        <nav class="post-series-header" aria-label="Bu yazı bir dizinin parçası">
            <a class="psh-title" href="<?= esc(url('/dizi/' . $series_info['slug'])) ?>"
               title="Dizi: <?= esc($series_info['name']) ?>">
                📚 <?= esc($series_info['name']) ?>
                <?php if ($series_nav['total'] > 0): ?>
                    <span class="psh-pos">· Bölüm <?= (int) $series_nav['position'] ?>/<?= (int) $series_nav['total'] ?></span>
                <?php endif; ?>
            </a>
            <span class="psh-arrows">
                <?php if (!empty($series_nav['prev'])): ?>
                    <a href="<?= esc(url('/' . $series_nav['prev']['category_slug'] . '/' . $series_nav['prev']['slug'])) ?>"
                       title="Önceki bölüm: <?= esc($series_nav['prev']['title']) ?>"
                       rel="prev">◀ Önceki Bölüm</a>
                <?php endif; ?>
                <?php if (!empty($series_nav['prev']) && !empty($series_nav['next'])): ?>
                    <span aria-hidden="true">|</span>
                <?php endif; ?>
                <?php if (!empty($series_nav['next'])): ?>
                    <a href="<?= esc(url('/' . $series_nav['next']['category_slug'] . '/' . $series_nav['next']['slug'])) ?>"
                       title="Sonraki bölüm: <?= esc($series_nav['next']['title']) ?>"
                       rel="next">Sonraki Bölüm ▶</a>
                <?php endif; ?>
            </span>
        </nav>
        <?php endif; ?>

        <h1><?= esc($post['title']) ?></h1>
        <?php if (!empty($post['excerpt'])): ?>
            <aside class="post-tldr" aria-label="Özet">
                <span class="post-tldr-label" aria-hidden="true">ÖZ</span>
                <p><?= esc($post['excerpt']) ?></p>
            </aside>
        <?php endif; ?>

        <p class="post-meta">
            <time datetime="<?= esc(date('c', strtotime((string) $post['published_at']))) ?>"><?= esc(tr_date($post['published_at'])) ?></time>
            <?php if ($post['reading_minutes']): ?>
                <span class="post-meta-sep" aria-hidden="true">·</span>
                <span><?= (int) $post['reading_minutes'] ?> DK OKUMA</span>
            <?php endif; ?>
            <?php
            // Last modified — sadece published_at'ten farklıysa göster (gereksiz "bugün" pollutionu önler)
            $pubTs = strtotime((string) $post['published_at']);
            $modTs = strtotime((string) ($post['updated_at'] ?? $post['published_at']));
            if ($modTs && $pubTs && abs($modTs - $pubTs) > 86400):
            ?>
                <span class="post-meta-sep" aria-hidden="true">·</span>
                <span class="post-meta-updated" title="Son güncelleme tarihi">
                    Güncelleme: <time datetime="<?= esc(date('c', $modTs)) ?>"><?= esc(tr_date(date('Y-m-d', $modTs))) ?></time>
                </span>
            <?php endif; ?>
        </p>

        <?php if ($cover): ?>
            <figure class="post-cover">
                <?= picture($cover, $post['title'], [
                    'loading'       => 'eager',
                    'fetchpriority' => 'high',
                    'sizes'         => '(max-width: 720px) 100vw, 720px',
                ]) ?>
            </figure>
        <?php endif; ?>
    </header>

    <aside class="toc" aria-label="İçindekiler">
        <h2 class="toc-title">İçindekiler</h2>
        <nav data-toc-target></nav>
    </aside>

    <?php
    // Paywall (Tier 9) — yazı paywall=1 ve kullanıcı giriş yapmamışsa özet göster.
    $_paywallOn = !empty($post['paywall']) && function_exists('feature') && feature('paywall_enabled');
    $_isLoggedIn = (\App\Services\AuthService::user() !== null);
    if ($_paywallOn && !$_isLoggedIn):
        $paywallExcerpt = trim((string) ($post['paywall_excerpt'] ?? ''));
        if ($paywallExcerpt === '') {
            $paywallExcerpt = mb_substr(strip_tags($body_html), 0, 380) . '…';
        }
        require dirname(__DIR__) . '/partials/paywall.php';
    else: ?>
    <section class="post-body" data-toc-source>
        <?= $body_html ?>
    </section>

    <?php
    // Before/After slider (Tier 9) — post.before_after_json varsa
    if (!empty($post['before_after_json']) && function_exists('feature') && feature('before_after_enabled')) {
        $beforeAfter = is_string($post['before_after_json'])
            ? json_decode($post['before_after_json'], true)
            : $post['before_after_json'];
        if (is_array($beforeAfter) && !empty($beforeAfter)) {
            require dirname(__DIR__) . '/partials/before-after.php';
        }
    }
    ?>
    <?php endif; ?>

    <?php if ($faq): ?>
    <section class="author-section">
        <h2>Sıkça Sorulan Sorular</h2>
        <dl class="faq">
            <?php foreach ($faq as $row): ?>
                <dt><?= esc($row['q']) ?></dt>
                <dd><?= esc($row['a']) ?></dd>
            <?php endforeach; ?>
        </dl>
    </section>
    <?php endif; ?>

    <?php
    // Engagement bar (Tier 7) — yazı sonu, share-buttons öncesi
    require dirname(__DIR__) . '/partials/engagement-bar.php';
    // Reactions bar (Tier 8) — emoji tepkileri
    require dirname(__DIR__) . '/partials/reactions-bar.php';
    ?>

    <?php if (!empty($tags)): ?>
    <section class="post-tags" aria-label="Etiketler">
        <span class="muted" style="font-size:.85rem">Etiketler:</span>
        <?php foreach ($tags as $_t): ?>
            <a href="<?= esc(url('/etiket/' . $_t['slug'])) ?>"
               class="tag-chip"
               title="&quot;<?= esc($_t['name']) ?>&quot; etiketli diğer yazılar"
               rel="tag"><?= esc($_t['name']) ?></a>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <?php
    $url = $canonical ?? absolute_url('/' . $post['category_slug'] . '/' . $post['slug']);
    require dirname(__DIR__) . '/partials/share-buttons.php';
    ?>

    <footer class="muted" style="margin-top:2rem">
        <a href="<?= esc(url('/' . $post['category_slug'])) ?>" title="<?= esc($post['category_name']) ?> kategorisine dön">← <?= esc($post['category_name']) ?> kategorisine dön</a>
    </footer>
</article>

<?php if (function_exists('feature') && feature('prev_next_nav_enabled') && !empty($prev_next)):
    require dirname(__DIR__) . '/partials/prev-next-nav.php';
endif; ?>

<?php if (function_exists('feature') && feature('author_bio_card_enabled') && !empty($author)):
    $excludeId = (int) $post['id'];
    require dirname(__DIR__) . '/partials/author-bio-card.php';
    // Co-author kartları (varsa)
    if (function_exists('feature') && feature('co_author_enabled')) {
        $_cos = \App\Models\PostAuthor::coAuthorsFor((int) $post['id']);
        foreach (array_slice($_cos, 0, 2) as $_co) {
            $coAuthor = \App\Models\User::findById((int) $_co['id']);
            if (!$coAuthor) continue;
            $author = $coAuthor;
            $profile = \App\Services\ProfileService::decode($coAuthor['profile_json'] ?? null);
            require dirname(__DIR__) . '/partials/author-bio-card.php';
        }
    }
endif; ?>

<?php if (!empty($related)): ?>
<section class="related-block">
    <h2>İlgili Yazılar</h2>
    <div class="mag-grid">
        <?php foreach ($related as $r): $ru = url('/' . $r['category_slug'] . '/' . $r['slug']); ?>
            <article class="mag-card">
                <a class="mag-cover <?= empty($r['cover_image']) ? 'mag-cover-empty' : '' ?>" href="<?= esc($ru) ?>" title="<?= esc($r['title']) ?>" aria-label="<?= esc($r['title']) ?>">
                    <?php if (!empty($r['cover_image'])): ?>
                        <?= picture_from_path((string) $r['cover_image'], esc($r['title']), ['width' => 800, 'height' => 600]) ?>
                    <?php endif; ?>
                </a>
                <h3><a href="<?= esc($ru) ?>" title="<?= esc($r['title']) ?>"><?= esc($r['title']) ?></a></h3>
                <?php if (!empty($r['excerpt'])): ?>
                    <p><?= esc(mb_substr((string) $r['excerpt'], 0, 130)) ?></p>
                <?php endif; ?>
                <p class="mag-meta">
                    <time datetime="<?= esc(date('c', strtotime((string) $r['published_at']))) ?>"><?= esc(tr_date($r['published_at'])) ?></time>
                    <?php if ($r['reading_minutes']): ?>
                        <span class="sep">·</span> <span><?= (int) $r['reading_minutes'] ?> dk</span>
                    <?php endif; ?>
                </p>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($trending)):
    require dirname(__DIR__) . '/partials/trending.php';
endif; ?>

<?php
$post_id = (int) $post['id'];
require dirname(__DIR__) . '/partials/comments.php';
?>
