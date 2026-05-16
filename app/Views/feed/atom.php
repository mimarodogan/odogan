<?php
/**
 * Atom 1.0 feed.
 * @var array $posts
 * @var string $siteName, $siteDesc, $siteUrl, $feedUrl
 */
declare(strict_types=1);

$updated = $posts ? date('c', strtotime((string) ($posts[0]['updated_at'] ?? $posts[0]['published_at']))) : date('c');
?>
<?= '<?xml version="1.0" encoding="UTF-8"?>' . "\n" ?>
<feed xmlns="http://www.w3.org/2005/Atom">
    <title><?= htmlspecialchars($siteName, ENT_XML1) ?></title>
    <subtitle><?= htmlspecialchars($siteDesc !== '' ? $siteDesc : $siteName, ENT_XML1) ?></subtitle>
    <link href="<?= htmlspecialchars($siteUrl, ENT_XML1) ?>" rel="alternate" type="text/html" />
    <link href="<?= htmlspecialchars($feedUrl, ENT_XML1) ?>" rel="self" type="application/atom+xml" />
    <id><?= htmlspecialchars($siteUrl, ENT_XML1) ?></id>
    <updated><?= $updated ?></updated>
    <generator uri="https://odogan.com.tr">Odogan CMS</generator>
    <?php
    // Atom feed kısaltma (RSS ile aynı politika — ilk ~250 kelime + devam linki)
    $atomWordLimit = 250;
    foreach ($posts as $p):
        $postUrl = absolute_url('/' . $p['category_slug'] . '/' . $p['slug']);
        $pubDate = date('c', strtotime((string) $p['published_at']));
        $modDate = date('c', strtotime((string) ($p['updated_at'] ?? $p['published_at'])));
        $plainFull  = \App\Services\MarkdownService::plain((string) $p['body'], 9999, (string) $p['body_format']);
        $plainShort = \App\Services\MarkdownService::plain((string) $p['body'], $atomWordLimit, (string) $p['body_format']);
        $isTruncated = str_word_count($plainFull, 0, 'çğıöşüâîûÇĞİÖŞÜ') > $atomWordLimit;
        $atomHtmlBody = '';
        foreach (preg_split('/\n{2,}/', $plainShort) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') continue;
            $atomHtmlBody .= '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_XML1) . '</p>' . "\n";
        }
        if ($isTruncated) {
            $atomHtmlBody .= '<p><em>… Yazının devamı sitede.</em></p>';
        }
        $atomHtmlBody .= '<p><a href="' . htmlspecialchars($postUrl, ENT_QUOTES | ENT_XML1) . '">'
                      . ($isTruncated ? 'Yazının devamını oku →' : 'Yazıyı sitede aç →')
                      . '</a></p>';
    ?>
    <entry>
        <title><?= htmlspecialchars((string) $p['title'], ENT_XML1) ?></title>
        <link href="<?= htmlspecialchars($postUrl, ENT_XML1) ?>" rel="alternate" type="text/html" />
        <id><?= htmlspecialchars($postUrl, ENT_XML1) ?></id>
        <published><?= $pubDate ?></published>
        <updated><?= $modDate ?></updated>
        <?php if (!empty($p['author_name'])): ?>
        <author>
            <name><?= htmlspecialchars((string) $p['author_name'], ENT_XML1) ?></name>
            <?php if (!empty($p['author_slug'])): ?>
            <uri><?= htmlspecialchars(absolute_url('/yazar/' . $p['author_slug']), ENT_XML1) ?></uri>
            <?php endif; ?>
        </author>
        <?php endif; ?>
        <category term="<?= htmlspecialchars((string) $p['category_slug'], ENT_XML1) ?>"
                  label="<?= htmlspecialchars((string) $p['category_name'], ENT_XML1) ?>" />
        <summary type="text"><?= htmlspecialchars($p['excerpt'] ?: (mb_substr($plainShort, 0, 280) . '…'), ENT_XML1) ?></summary>
        <content type="html"><![CDATA[<?= $atomHtmlBody ?>]]></content>
    </entry>
    <?php endforeach; ?>
</feed>
