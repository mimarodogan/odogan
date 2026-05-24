<?php
declare(strict_types=1);

namespace App\Services\Schema;

use App\Services\MarkdownService;

final class FaqPage
{
    /**
     * @param array<int,array{q:string,a:string}> $items
     * @param string|null $id  Opsiyonel @id (örn. "{post_url}#faq") — graph
     *                         içinde başka node'lardan referans verilebilir.
     */
    public static function build(array $items, ?string $id = null): ?array
    {
        $entities = [];
        foreach ($items as $row) {
            $q = trim((string) ($row['q'] ?? ''));
            $a = trim((string) ($row['a'] ?? ''));
            if ($q === '' || $a === '') {
                continue;
            }
            $entities[] = [
                '@type' => 'Question',
                'name' => $q,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => MarkdownService::plain($a, 1500),
                ],
            ];
        }
        if (!$entities) {
            return null;
        }
        $node = ['@type' => 'FAQPage'];
        if ($id !== null && $id !== '') {
            $node['@id'] = $id;
        }
        $node['mainEntity'] = $entities;
        return $node;
    }
}
