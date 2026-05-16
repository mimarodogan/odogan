<?php
/**
 * RSS 2.0 feed.
 * @var array $posts
 * @var string $siteName, $siteDesc, $siteUrl, $feedUrl
 */
declare(strict_types=1);

$buildDate = $posts ? date('r', strtotime((string) $posts[0]['published_at'])) : date('r');
?>
<?= '<?xml version="1.0" encoding="UTF-8"?>' . "\n" ?>
<rss version="2.0"
     xmlns:atom="http://www.w3.org/2005/Atom"
     xmlns:dc="http://purl.org/dc/elements/1.1/"
     xmlns:content="http://purl.org/rss/1.0/modules/content/">
<channel>
    <title><?= htmlspecialchars($siteName, ENT_XML1) ?></title>
    <link><?= htmlspecialchars($siteUrl, ENT_XML1) ?></link>
    <description><?= htmlspecialchars($siteDesc !== '' ? $siteDesc : $siteName, ENT_XML1) ?></description>
    <language>tr</language>
    <lastBuildDate><?= $buildDate ?></lastBuildDate>
    <atom:link href="<?= htmlspecialchars($feedUrl, ENT_XML1) ?>" rel="self" type="application/rss+xml" />
    <generator>Odogan CMS</generator>
    <?php
    // RSS feed kısaltma — kötü niyetli scraper'lara karşı tam içerik vermek yerine
    // ilk ~250 kelimelik özet + "devamı için" linki yayınlanır.
    $rssWordLimit = 250;
    foreach ($posts as $p):
        $postUrl = absolute_url('/' . $p['category_slug'] . '/' . $p['slug']);
        $pubDate = date('r', strtotime((string) $p['published_at']));
        $plainFull  = \App\Services\MarkdownService::plain((string) $p['body'], 9999, (string) $p['body_format']);
        $plainShort = \App\Services\MarkdownService::plain((string) $p['body'], $rssWordLimit, (string) $p['body_format']);
        // Kısaltıldı mı kontrolü — kelime sayısı karşılaştır
        $isTruncated = str_word_count($plainFull, 0, 'çğıöşüâîûÇĞİÖŞÜ') > $rssWordLimit;
        // Paragraflar halinde HTML'e çevir
        $rssHtmlBody = '';
        foreach (preg_split('/\n{2,}/', $plainShort) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') continue;
            $rssHtmlBody .= '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_XML1) . '</p>' . "\n";
        }
        if ($isTruncated) {
            $rssHtmlBody .= '<p><em>… Yazının devamı sitede.</em></p>';
        }
        $rssHtmlBody .= '<p><a href="' . htmlspecialchars($postUrl, ENT_QUOTES | ENT_XML1) . '">'
                     . ($isTruncated ? 'Yazının devamını oku →' : 'Yazıyı sitede aç →')
                     . '</a></p>';
    ?>
    <item>
        <title><?= htmlspecialchars((string) $p['title'], ENT_XML1) ?></title>
        <link><?= htmlspecialchars($postUrl, ENT_XML1) ?></link>
        <guid isPermaLink="true"><?= htmlspecialchars($postUrl, ENT_XML1) ?></guid>
        <pubDate><?= $pubDate ?></pubDate>
        <?php if (!empty($p['author_name'])): ?>
        <dc:creator><?= htmlspecialchars((string) $p['author_name'], ENT_XML1) ?></dc:creator>
        <?php endif; ?>
        <category><?= htmlspecialchars((string) $p['category_name'], ENT_XML1) ?></category>
        <description><?= htmlspecialchars($p['excerpt'] ?: mb_substr($plainShort, 0, 280) . '…', ENT_XML1) ?></description>
        <content:encoded><![CDATA[<?= $rssHtmlBody ?>]]></content:encoded>
    </item>
    <?php endforeach; ?>
</channel>
</rss>
