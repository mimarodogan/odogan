<?php
declare(strict_types=1);

namespace App\Services\Schema;

/**
 * /sozluk index sayfası için Schema.org DefinedTermSet.
 * Her aktif sözlük girdisi DefinedTerm olarak hasDefinedTerm dizisine girer.
 *
 * SALAMA BİLGİ YOK — sadece DB'deki gerçek girdiler kullanılır.
 */
final class DefinedTermSet
{
    /**
     * @param array<int,array> $items Glossary::all(activeOnly=true) çıktısı
     * @return array|null             DefinedTermSet node veya null
     */
    public static function build(array $items): ?array
    {
        if (empty($items)) return null;

        $url = url('/sozluk');
        $terms = [];

        foreach ($items as $g) {
            $term = (string) ($g['term'] ?? '');
            $slug = (string) ($g['slug'] ?? '');
            if ($term === '' || $slug === '') continue;

            $node = [
                '@type' => 'DefinedTerm',
                'name'  => $term,
                'url'   => url('/sozluk/' . $slug),
            ];
            // Kısa açıklama — sadece tanım doluysa
            $def = trim(strip_tags((string) ($g['definition'] ?? '')));
            if ($def !== '') {
                $node['description'] = mb_substr($def, 0, 200);
            }
            // Kategori — varsa termCode olarak değil, sadece inDefinedTermSet'in
            // bir parçası olarak yapıdan kaldırıyoruz (set zaten ortak).
            $aliases = trim((string) ($g['aliases'] ?? ''));
            if ($aliases !== '') {
                $altNames = array_values(array_filter(array_map('trim', explode(',', $aliases))));
                if (!empty($altNames)) {
                    $node['alternateName'] = count($altNames) === 1 ? $altNames[0] : $altNames;
                }
            }

            $terms[] = $node;
        }

        if (empty($terms)) return null;

        return [
            '@type'          => 'DefinedTermSet',
            '@id'            => $url . '#sozluk',
            'name'           => 'Mimari Sözlük',
            'description'    => 'Mimarlık, mühendislik ve şehir planlama terimleri.',
            'url'            => $url,
            'inLanguage'     => 'tr',
            'hasDefinedTerm' => $terms,
        ];
    }
}
