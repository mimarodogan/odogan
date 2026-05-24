<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Cache\CacheManager;
use App\Core\Database;

/**
 * AutoGlossaryLink — Yazı body'sindeki mimari terimleri otomatik olarak
 * sözlük girdilerine link'ler. Her terim **yazı içinde 1 kez** linklenir
 * (spam önler), sonraki tekrarlar düz metin kalır.
 *
 * SEO etkisi: Internal linking ağı zenginleşir, yazıdan sözlüğe geçiş
 * artar, sayfa içi engagement & dwell-time yükselir.
 *
 * Hız: Tüm terimler 1 saatlik cache'le yüklenir; render başına 1 cache hit.
 *
 * Güvenlik:
 *   - Replacement yapılırken `<a>`, `<code>`, `<pre>`, `<h*>`, `<script>`,
 *     `<style>` tag'leri içine GİRMEZ — DOMDocument ile text node tabanlı.
 *   - Aliases da target olarak eşleştirilir (örn. "Biophilic Design" →
 *     biyofilik-tasarim).
 */
final class AutoGlossaryLink
{
    /**
     * Yazı HTML body'sini terim otomatik link'leriyle zenginleştir.
     */
    public static function apply(string $html): string
    {
        if ($html === '' || !self::featureOn()) {
            return $html;
        }
        $terms = self::loadTerms();
        if (empty($terms)) {
            return $html;
        }

        // DOMDocument ile text node bazlı substitution
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        // <body> wrapper'ı + UTF-8 meta — Türkçe karakterleri korumak için
        $wrapped = '<?xml encoding="utf-8" ?><div id="__autolink_root">' . $html . '</div>';
        if (!$doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            return $html;
        }
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $doc->getElementById('__autolink_root');
        if (!$root) {
            return $html;
        }

        // Her terim için yazı genelinde 1 kez linkleneceğini takip et
        $linkedSlugs = [];

        self::walkAndLink($doc, $root, $terms, $linkedSlugs);

        // <div id="__autolink_root">…</div> wrapper'ını sıyır
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return $out;
    }

    /**
     * Yazı içindeki text node'ları gezerek terim eşleşmelerinde
     * <a> elementi inject eder. Belirli tag'lerin içine girmez.
     */
    private static function walkAndLink(\DOMDocument $doc, \DOMNode $node, array $terms, array &$linkedSlugs): void
    {
        $skipTags = ['a', 'code', 'pre', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'script', 'style', 'textarea'];

        $children = iterator_to_array($node->childNodes);
        foreach ($children as $child) {
            if ($child instanceof \DOMElement) {
                $tag = strtolower($child->tagName);
                if (in_array($tag, $skipTags, true)) {
                    continue; // bu elementin içine girme
                }
                self::walkAndLink($doc, $child, $terms, $linkedSlugs);
            } elseif ($child instanceof \DOMText) {
                self::linkTextNode($doc, $child, $terms, $linkedSlugs);
            }
        }
    }

    private static function linkTextNode(\DOMDocument $doc, \DOMText $textNode, array $terms, array &$linkedSlugs): void
    {
        $text = $textNode->nodeValue ?? '';
        if (trim($text) === '') return;

        // Tüm terimler için tek tek dene — uzun terim önce eşleşsin (overlap önler)
        foreach ($terms as $entry) {
            $slug = $entry['slug'];
            if (in_array($slug, $linkedSlugs, true)) continue; // bu yazıda zaten linklendi

            $patterns = $entry['patterns']; // [terim, alias1, alias2, ...]
            foreach ($patterns as $pattern) {
                $regex = '/(?<![\p{L}\p{N}])(' . preg_quote($pattern, '/') . ')(?![\p{L}\p{N}])/iu';
                if (!preg_match($regex, $text, $m, PREG_OFFSET_CAPTURE)) {
                    continue;
                }
                // Eşleşme bulundu — bu text node'u 3'e böl:
                // [öncesi] + <a>eşleşme</a> + [sonrası]
                $offset = $m[1][1];
                $matchedText = $m[1][0];
                $matchedLen = strlen($matchedText);

                $before = substr($text, 0, $offset);
                $after  = substr($text, $offset + $matchedLen);

                $parent = $textNode->parentNode;
                if (!$parent) return;

                if ($before !== '') {
                    $parent->insertBefore($doc->createTextNode($before), $textNode);
                }
                $a = $doc->createElement('a');
                $a->setAttribute('href', '/sozluk/' . $slug);
                $a->setAttribute('class', 'glossary-autolink');
                $a->setAttribute('title', 'Sözlük: ' . $entry['term']);
                $a->appendChild($doc->createTextNode($matchedText));
                $parent->insertBefore($a, $textNode);

                if ($after !== '') {
                    $parent->insertBefore($doc->createTextNode($after), $textNode);
                }
                $parent->removeChild($textNode);

                $linkedSlugs[] = $slug;
                return; // bu text node tüketildi
            }
        }
    }

    /**
     * Tüm aktif terimleri (+ aliases) cache'ten yükle.
     * @return array<int,array{slug:string,term:string,patterns:array<int,string>}>
     */
    private static function loadTerms(): array
    {
        return CacheManager::driver()->remember('glossary:autolink-terms', 3600, function () {
            try {
                $rows = Database::instance()->fetchAll(
                    'SELECT slug, term, aliases FROM glossary
                     WHERE is_active = 1
                     ORDER BY CHAR_LENGTH(term) DESC'  // uzun terimler önce (overlap önler)
                );
            } catch (\Throwable) {
                return [];
            }

            $terms = [];
            foreach ($rows as $r) {
                $patterns = [(string) $r['term']];
                $aliases = trim((string) ($r['aliases'] ?? ''));
                if ($aliases !== '') {
                    foreach (explode(',', $aliases) as $a) {
                        $a = trim($a);
                        if ($a !== '' && mb_strlen($a) >= 3) {
                            $patterns[] = $a;
                        }
                    }
                }
                // Çok kısa terimleri atla — "iz", "yön" gibi false-positive riski
                $patterns = array_filter($patterns, static fn($p) => mb_strlen($p) >= 4);
                if (empty($patterns)) continue;

                $terms[] = [
                    'slug'     => (string) $r['slug'],
                    'term'     => (string) $r['term'],
                    'patterns' => array_values($patterns),
                ];
            }
            return $terms;
        }, ['glossary']);
    }

    private static function featureOn(): bool
    {
        // Feature flag: features.glossary_autolink_enabled
        // Glossary feature aktif değilse çalışmaz.
        if (!function_exists('feature') || !feature('glossary_enabled')) {
            return false;
        }
        return (bool) \App\Models\Setting::get('glossary_autolink_enabled', true, 'features');
    }
}
