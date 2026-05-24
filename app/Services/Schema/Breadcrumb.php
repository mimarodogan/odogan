<?php
declare(strict_types=1);

namespace App\Services\Schema;

final class Breadcrumb
{
    /**
     * @param array<int,array{name:string,url:string}> $items
     * @param string|null $id  Opsiyonel — WebPage.breadcrumb referansı için @id
     */
    public static function build(array $items, ?string $id = null): ?array
    {
        if (!$items) {
            return null;
        }
        $list = [];
        $i = 1;
        foreach ($items as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));
            if ($name === '' || $url === '') {
                continue;
            }
            $list[] = [
                '@type' => 'ListItem',
                'position' => $i++,
                'name' => $name,
                'item' => $url,
            ];
        }
        if (!$list) {
            return null;
        }
        $node = ['@type' => 'BreadcrumbList'];
        if ($id !== null && $id !== '') {
            $node['@id'] = $id;
        }
        $node['itemListElement'] = $list;
        return $node;
    }
}
