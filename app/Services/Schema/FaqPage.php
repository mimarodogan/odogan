<?php
declare(strict_types=1);

namespace App\Services\Schema;

use App\Services\MarkdownService;

final class FaqPage
{
    /**
     * @param array<int,array{q:string,a:string}> $items
     */
    public static function build(array $items): ?array
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
        return [
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }
}
