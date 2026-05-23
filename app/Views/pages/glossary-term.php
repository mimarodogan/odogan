<?php
/**
 * Sözlük terim sayfası — blog yazı (post.css) estetiğine uyumlu.
 * Kullanılan class'lar `pages/post.php` ile birebir aynı; tek fark
 * eyebrow/meta etiketleri sözlüğe özgü.
 *
 * @var array $item     glossary satırı
 * @var array $related  ilgili sözlük terimleri
 */
\App\Core\View::layout('base');

$term      = (string) ($item['term']  ?? '');
$slug      = (string) ($item['slug']  ?? '');
$category  = trim((string) ($item['category']  ?? ''));
$aliases   = trim((string) ($item['aliases']   ?? ''));
$definition= (string) ($item['definition']    ?? '');
$references= trim((string) ($item['references'] ?? ''));
$updatedAt = (string) ($item['updated_at'] ?? $item['created_at'] ?? '');
$viewCount = (int) ($item['view_count'] ?? 0);

// İlk paragrafı lead olarak çek; HTML body yeterince zenginse lead boş bırakılır.
$plain = trim(strip_tags($definition));
$leadText = mb_substr($plain, 0, 200);
if (mb_strlen($plain) > 200) {
    $leadText = mb_substr($leadText, 0, mb_strrpos($leadText, ' ') ?: 200) . '…';
}

// Referansları parse et — JSON [{text,url}] yeni biçim, fallback legacy `;`
$refList = [];
if ($references !== '') {
    $decoded = json_decode($references, true);
    if (is_array($decoded)) {
        foreach ($decoded as $r) {
            if (!is_array($r)) continue;
            $rText = trim((string) ($r['text'] ?? ''));
            $rUrl  = trim((string) ($r['url']  ?? ''));
            if ($rText === '' && $rUrl === '') continue;
            $refList[] = [
                'text' => $rText !== '' ? $rText : $rUrl,
                'url'  => preg_match('#^https?://#i', $rUrl) ? $rUrl : '',
            ];
        }
    } else {
        // Legacy: 'A; B; https://x'
        foreach (array_filter(array_map('trim', explode(';', $references))) as $part) {
            $isUrl = (bool) preg_match('#^https?://#i', $part);
            $refList[] = [
                'text' => $part,
                'url'  => $isUrl ? $part : '',
            ];
        }
    }
}

// Aliases comma ile ayır
$aliasList = [];
if ($aliases !== '') {
    foreach (array_filter(array_map('trim', explode(',', $aliases))) as $a) {
        $aliasList[] = $a;
    }
}
?>

<?= breadcrumbs_html($breadcrumbs ?? []) ?>

<article class="post post-glossary">
    <header>
        <h1><?= esc($term) ?></h1>

        <?php if (!empty($aliasList)): ?>
            <p class="lead">
                <em>Ayrıca bilinir:</em>
                <?php foreach ($aliasList as $i => $a): ?>
                    <strong><?= esc($a) ?></strong><?= $i < count($aliasList) - 1 ? ', ' : '' ?>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
    </header>

    <section class="post-body">
        <?= $definition /* Sanitized at save time */ ?>
    </section>

    <?php if (!empty($refList)): ?>
    <section class="author-section post-glossary-refs" aria-labelledby="kaynaklar-title">
        <h2 id="kaynaklar-title">Kaynaklar</h2>
        <ol>
            <?php foreach ($refList as $ref):
                $rText = (string) ($ref['text'] ?? '');
                $rUrl  = (string) ($ref['url']  ?? '');
            ?>
                <li>
                    <?php if ($rUrl !== ''): ?>
                        <a href="<?= esc($rUrl) ?>" target="_blank" rel="noopener nofollow"
                           title="<?= esc($rUrl) ?> — dış kaynak">
                            <?= esc($rText) ?> <span aria-hidden="true">↗</span>
                        </a>
                    <?php else: ?>
                        <?= esc($rText) ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>
    <?php endif; ?>

    <?php
    // Share buttons partial'ı bir $post array bekler — sözlük girdisinden
    // uyumlu bir wrapper üretiyoruz (terim paylaşmak için aynı UX).
    $url = $canonical ?? absolute_url('/sozluk/' . $slug);
    $post = [
        'id'            => (int) ($item['id'] ?? 0),
        'title'         => $term,
        'slug'          => $slug,
        'category_slug' => 'sozluk',
        'cover_image'   => '',
        'excerpt'       => $leadText,
    ];
    if (file_exists(dirname(__DIR__) . '/partials/share-buttons.php')) {
        require dirname(__DIR__) . '/partials/share-buttons.php';
    }
    ?>

    <footer class="muted" style="margin-top:2rem">
        <a href="<?= esc(url('/sozluk')) ?>" title="Tüm sözlük girdileri">← Tüm sözlüğe dön</a>
    </footer>
</article>

<?php if (!empty($related)): ?>
<section class="related-block">
    <h2>İlgili Terimler<?= $category !== '' ? ' <small>· ' . esc($category) . ' kategorisinden</small>' : '' ?></h2>
    <div class="mag-grid mag-grid-glossary">
        <?php foreach ($related as $r):
            $ru = url('/sozluk/' . $r['slug']);
            $rDef = trim(strip_tags((string) ($r['definition'] ?? '')));
            $rExcerpt = mb_substr($rDef, 0, 130);
            if (mb_strlen($rDef) > 130) {
                $rExcerpt = mb_substr($rExcerpt, 0, mb_strrpos($rExcerpt, ' ') ?: 130) . '…';
            }
        ?>
            <article class="mag-card mag-card-glossary">
                <a class="mag-card-glossary-mark" href="<?= esc($ru) ?>" title="<?= esc($r['term']) ?>" aria-hidden="true">§</a>
                <?php if (!empty($r['category'])): ?>
                    <span class="cat-pill"><?= esc($r['category']) ?></span>
                <?php endif; ?>
                <h3><a href="<?= esc($ru) ?>" title="<?= esc($r['term']) ?>"><?= esc($r['term']) ?></a></h3>
                <?php if ($rExcerpt !== ''): ?>
                    <p><?= esc($rExcerpt) ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
