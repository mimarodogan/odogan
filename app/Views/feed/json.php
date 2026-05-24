<?php
/**
 * JSON Feed 1.1 — https://www.jsonfeed.org/version/1.1/
 * @var array $posts
 * @var string $siteName, $siteDesc, $siteUrl, $feedUrl
 */
declare(strict_types=1);

$items = [];
$jsonWordLimit = 250;
foreach ($posts as $p) {
    $postUrl = absolute_url('/' . $p['category_slug'] . '/' . $p['slug']);
    $plainFull  = \App\Services\MarkdownService::plain((string) $p['body'], 9999, (string) $p['body_format']);
    $plainShort = \App\Services\MarkdownService::plain((string) $p['body'], $jsonWordLimit, (string) $p['body_format']);
    $isTruncated = str_word_count($plainFull, 0, 'çğıöşüâîûÇĞİÖŞÜ') > $jsonWordLimit;
    // HTML paragraflarına çevir + devam linki
    $jsonHtmlBody = '';
    foreach (preg_split('/\n{2,}/', $plainShort) ?: [] as $paragraph) {
        $paragraph = trim($paragraph);
        if ($paragraph === '') continue;
        $jsonHtmlBody .= '<p>' . htmlspecialchars($paragraph, ENT_QUOTES) . '</p>';
    }
    if ($isTruncated) {
        $jsonHtmlBody .= '<p><em>… Yazının devamı sitede.</em></p>';
    }
    $jsonHtmlBody .= '<p><a href="' . htmlspecialchars($postUrl, ENT_QUOTES) . '">'
                  . ($isTruncated ? 'Yazının devamını oku →' : 'Yazıyı sitede aç →')
                  . '</a></p>';

    $item = [
        'id' => $postUrl,
        'url' => $postUrl,
        'title' => (string) $p['title'],
        'content_html' => $jsonHtmlBody,
        'summary' => $p['excerpt'] ?: (mb_substr($plainShort, 0, 280) . '…'),
        'date_published' => date('c', strtotime((string) $p['published_at'])),
        'date_modified'  => date('c', strtotime((string) ($p['updated_at'] ?? $p['published_at']))),
        'tags' => [(string) $p['category_name']],
    ];
    if (!empty($p['cover_image'])) {
        $item['image'] = absolute_url((string) $p['cover_image']);
    }
    if (!empty($p['author_name'])) {
        $item['authors'] = [[
            'name' => (string) $p['author_name'],
            'url' => !empty($p['author_slug']) ? absolute_url('/yazar/' . $p['author_slug']) : null,
        ]];
    }
    $items[] = $item;
}

$feed = [
    'version' => 'https://jsonfeed.org/version/1.1',
    'title' => $siteName,
    'home_page_url' => $siteUrl,
    'feed_url' => $feedUrl,
    'description' => $siteDesc !== '' ? $siteDesc : $siteName,
    'language' => 'tr',
    'items' => $items,
];

echo json_encode($feed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
