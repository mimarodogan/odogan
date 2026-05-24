<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Wikipedia-stili Otomatik İç Linkleme.
 *
 * Bir yazı/sözlük girdisinin gövdesindeki ilk geçen sözlük terimlerini
 * veya yazı başlıklarını ANCHOR'a sarar. Sayfa başına maksimum 2 link
 * (kullanıcı kararı: her tip sayfa için 2). DOM-güvenli — mevcut <a>,
 * <code>, <pre>, başlıklar (<h1-6>), <script>, <style> içine girilmez.
 *
 * Çağrı:
 *   AutoLinkService::enrich($html, 'post', $postId, [
 *       'category_id' => 5,
 *   ]);
 *   AutoLinkService::enrich($html, 'glossary', $glossId, [
 *       'category' => 'Strüktür',
 *   ]);
 *
 * Not: Etiket sistemi T1'de kaldırıldı; eski `tag_slugs` parametresi
 * artık desteklenmiyor.
 *
 * GATING:
 *   - feature('auto_internal_link') === true olmalı; aksi halde HTML
 *     hiç dokunulmadan döner.
 *
 * Performans:
 *   - Aday liste (terim + aktif yazılar) ilk çağrıda bellekte cache'lenir
 *     (request bazlı, statik dizi).
 *   - Body için DOMDocument tek geçişlik tarama; tipik post (~5 KB)
 *     için 2-5 ms.
 *   - Render başına çağrıldığında bile gerçek-zamanlı; ekstra invalidate
 *     mekanizmasına ihtiyaç yok (her render taze çözer).
 */
final class AutoLinkService
{
    /** Sayfa başına maksimum otomatik link */
    private const MAX_LINKS_PER_PAGE = 2;

    /** Aday cache (request başı) */
    private static ?array $candidates = null;

    public static function isEnabled(): bool
    {
        return function_exists('feature') && feature('auto_internal_link');
    }

    /**
     * @param string $html             Yazı/sözlük gövdesi (sanitized HTML)
     * @param string $sourceType       'post' | 'glossary'
     * @param int    $sourceId         self-link engellemek için
     * @param array  $ctx              ['category_id'?, 'category'?]
     */
    public static function enrich(string $html, string $sourceType, int $sourceId, array $ctx = []): string
    {
        if (!self::isEnabled() || trim($html) === '') {
            return $html;
        }
        if (!in_array($sourceType, ['post', 'glossary'], true)) {
            return $html;
        }

        $candidates = self::candidates();
        if ($candidates === []) {
            return $html;
        }

        // Self-link engelleyici: kendi kimliğini aday listeden düşür
        $selfKey = $sourceType . ':' . $sourceId;
        $candidates = array_filter($candidates, static fn($c) => $c['key'] !== $selfKey);
        if ($candidates === []) {
            return $html;
        }

        // Skor: freq × kategori uyumu × tazelik (basit ama etkili).
        // Eşleşmesi olanları topla, en yüksek 2'sini seç.
        $bodyNorm = KeyphraseService::normalize(strip_tags($html));
        if ($bodyNorm === '') {
            return $html;
        }

        $scored = [];
        foreach ($candidates as $c) {
            $hits = self::countMatches($bodyNorm, $c['needles_norm']);
            if ($hits === 0) {
                continue;
            }
            $score = $hits * self::contextMultiplier($c, $ctx) * $c['recency'];
            $scored[] = $c + ['hits' => $hits, 'score' => $score];
        }
        if ($scored === []) {
            return $html;
        }
        usort($scored, static fn($a, $b) => $b['score'] <=> $a['score']);
        $top = array_slice($scored, 0, self::MAX_LINKS_PER_PAGE);

        return self::injectLinks($html, $top);
    }

    /**
     * Adayları topla: glossary aktif terimler + son 1 yıl içinde yayınlanmış
     * yazıların başlıkları. Her aday: needles (term + alias varyantları).
     *
     * @return array<int,array{
     *   key:string, type:string, id:int, href:string, label:string,
     *   class:string, needles_norm:array<int,array{phrase:string,raw:string}>,
     *   category_id:int, category_slug:string, recency:float
     * }>
     */
    private static function candidates(): array
    {
        if (self::$candidates !== null) {
            return self::$candidates;
        }
        $out = [];

        // ─── Glossary aday'ları ─────────────────────────────────────────
        try {
            $rows = Database::instance()->fetchAll(
                'SELECT id, term, slug, aliases, category FROM glossary WHERE is_active = 1'
            );
        } catch (\Throwable) {
            $rows = [];
        }
        foreach ($rows as $r) {
            $term  = (string) ($r['term']  ?? '');
            $slug  = (string) ($r['slug']  ?? '');
            if ($term === '' || $slug === '') continue;

            $needles = [self::makeNeedle($term)];
            $aliRaw  = (string) ($r['aliases'] ?? '');
            if ($aliRaw !== '') {
                foreach (array_filter(array_map('trim', explode(',', $aliRaw))) as $a) {
                    if (mb_strlen($a) >= 3) {
                        $needles[] = self::makeNeedle($a);
                    }
                }
            }
            $out[] = [
                'key'           => 'glossary:' . (int) $r['id'],
                'type'          => 'glossary',
                'id'            => (int) $r['id'],
                'href'          => url('/sozluk/' . $slug),
                'label'         => $term,
                'class'         => 'auto-link auto-link-glossary',
                'needles_norm'  => $needles,
                'category_id'   => 0,
                'category_slug' => mb_strtolower((string) ($r['category'] ?? '')),
                'recency'       => 1.0,
            ];
        }

        // ─── Yazı aday'ları (son 1 yıl, published) ──────────────────────
        try {
            $rows = Database::instance()->fetchAll(
                "SELECT p.id, p.title, p.slug, p.category_id, c.slug AS category_slug, p.published_at
                 FROM posts p
                 LEFT JOIN categories c ON c.id = p.category_id
                 WHERE p.status = 'published'
                   AND p.published_at IS NOT NULL
                   AND p.published_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)
                 ORDER BY p.published_at DESC"
            );
        } catch (\Throwable) {
            $rows = [];
        }
        foreach ($rows as $r) {
            $title = (string) ($r['title'] ?? '');
            $slug  = (string) ($r['slug']  ?? '');
            $cslug = (string) ($r['category_slug'] ?? '');
            if ($title === '' || $slug === '' || $cslug === '') continue;
            // Çok kısa başlıkları atla — yanlış-pozitif riski (örn. "Ev")
            if (mb_strlen($title) < 8) continue;

            // Tazelik: 0..1 (bugün=1, 365 gün önce ~0.1)
            $ts = strtotime((string) $r['published_at']) ?: time();
            $ageDays = max(0, (time() - $ts) / 86400);
            $recency = max(0.1, 1.0 - ($ageDays / 365));

            $out[] = [
                'key'           => 'post:' . (int) $r['id'],
                'type'          => 'post',
                'id'            => (int) $r['id'],
                'href'          => url('/' . $cslug . '/' . $slug),
                'label'         => $title,
                'class'         => 'auto-link auto-link-post',
                'needles_norm'  => [self::makeNeedle($title)],
                'category_id'   => (int) ($r['category_id'] ?? 0),
                'category_slug' => $cslug,
                'recency'       => $recency,
            ];
        }

        self::$candidates = $out;
        return $out;
    }

    /**
     * Bir kelime/kelime öbeği için arama "needle"'i:
     *   - phrase: normalize edilmiş gövde ("konsol kiriş")
     *   - raw:    HTML'de aranacak orijinal (case-insensitive eşleşme)
     */
    private static function makeNeedle(string $phrase): array
    {
        return [
            'phrase' => KeyphraseService::normalize($phrase),
            'raw'    => trim($phrase),
        ];
    }

    /**
     * Normalize edilmiş body içinde herhangi bir needle kaç kez geçiyor?
     * @param array<int,array{phrase:string,raw:string}> $needles
     */
    private static function countMatches(string $bodyNorm, array $needles): int
    {
        $total = 0;
        foreach ($needles as $n) {
            $p = (string) $n['phrase'];
            if ($p === '') continue;
            // Türkçe ek toleransı: kelimeden sonra 0-10 harf olabilir.
            $rx = '/(?<![\p{L}\p{N}])' . preg_quote($p, '/') . '(?:\p{L}{0,10})?(?![\p{L}\p{N}])/u';
            $c = preg_match_all($rx, $bodyNorm, $m);
            if ($c !== false) $total += $c;
        }
        return $total;
    }

    /**
     * Kategori/etiket uyumu varsa skor 1.4×; tamamen alakasız ise 0.8×.
     */
    private static function contextMultiplier(array $candidate, array $ctx): float
    {
        // Kategori uyumu (post→post için, hem id hem slug)
        if ($candidate['type'] === 'post' && !empty($ctx['category_id'])) {
            if ((int) $candidate['category_id'] === (int) $ctx['category_id']) {
                return 1.4;
            }
        }
        // Glossary→post için: glossary kategori string'i post kategori slug ile karşılaştır
        if ($candidate['type'] === 'glossary' && !empty($ctx['category_id'])) {
            // Sözlüğün kategorisi yazı içinde ipucu varsa pozitif
            return 1.1;
        }
        return 1.0;
    }

    /**
     * Top adayların ilk-geçişini HTML'e enjekte et.
     * DOM-güvenli: <a>, <code>, <pre>, <h1-6>, <script>, <style> es geçilir.
     *
     * @param array<int,array{key:string,type:string,href:string,label:string,class:string,needles_norm:array}> $picks
     */
    private static function injectLinks(string $html, array $picks): string
    {
        if ($picks === [] || trim($html) === '') return $html;

        // DOMDocument ile yükle. Türkçe karakter için UTF-8 BOM enjekte et,
        // libxml uyarılarını sustur.
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $wrap = '<?xml encoding="UTF-8"?><div id="autolink-root">' . $html . '</div>';
        libxml_use_internal_errors(true);
        $doc->loadHTML($wrap, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $root = $doc->getElementById('autolink-root');
        if (!$root) return $html;

        // Her aday için sıra ile dene — birden fazlasının aynı satıra düşmesini önle
        $usedKeys = [];
        foreach ($picks as $p) {
            if (count($usedKeys) >= self::MAX_LINKS_PER_PAGE) break;
            $applied = self::applyOne($doc, $root, $p);
            if ($applied) {
                $usedKeys[$p['key']] = true;
            }
        }

        // Sadece root'un iç HTML'ini geri ver (XML declaration & wrap'ı çıkar)
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return $out;
    }

    /**
     * Tek bir adayı text node'larında ilk-geçişte uygula.
     */
    private static function applyOne(\DOMDocument $doc, \DOMElement $root, array $pick): bool
    {
        $needles = $pick['needles_norm'];
        if ($needles === []) return false;

        $xpath = new \DOMXPath($doc);
        // Yasaklı atalar dışındaki tüm metin düğümleri
        $textNodes = $xpath->query(
            './/text()[not(ancestor::a) and not(ancestor::code) and not(ancestor::pre)'
            . ' and not(ancestor::h1) and not(ancestor::h2) and not(ancestor::h3)'
            . ' and not(ancestor::h4) and not(ancestor::h5) and not(ancestor::h6)'
            . ' and not(ancestor::script) and not(ancestor::style)'
            . ' and not(ancestor::nav) and not(ancestor::aside)]',
            $root
        );
        if ($textNodes === false) return false;

        foreach ($textNodes as $node) {
            $original = $node->nodeValue;
            if ($original === null || trim($original) === '') continue;

            foreach ($needles as $n) {
                $raw    = (string) $n['raw'];
                $phrase = (string) $n['phrase'];
                if ($raw === '' || $phrase === '') continue;

                // Türkçe doğru eşleşme için normalize karşılaştır, ama orijinal
                // gövdedeki konumu bulmak gerek. Strateji:
                //   - Orijinal metni kelime kelime tara (regex)
                //   - Her aday kelime grubunu normalize et, hedefle karşılaştır
                $rx = '/(?<![\p{L}\p{N}])(' . preg_quote($raw, '/') . '\p{L}{0,10})(?![\p{L}\p{N}])/iu';
                if (preg_match($rx, $original, $m, PREG_OFFSET_CAPTURE)) {
                    $matchText  = $m[1][0];
                    $matchStart = $m[1][1];
                    $matchEnd   = $matchStart + strlen($matchText);

                    $before = substr($original, 0, $matchStart);
                    $after  = substr($original, $matchEnd);

                    $a = $doc->createElement('a', htmlspecialchars($matchText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                    $a->setAttribute('href', $pick['href']);
                    $a->setAttribute('class', $pick['class']);
                    $a->setAttribute('data-auto-link', $pick['type']);
                    $a->setAttribute('title', $pick['label']);

                    $parent = $node->parentNode;
                    if (!$parent) return false;

                    if ($before !== '') {
                        $parent->insertBefore($doc->createTextNode($before), $node);
                    }
                    $parent->insertBefore($a, $node);
                    if ($after !== '') {
                        $parent->insertBefore($doc->createTextNode($after), $node);
                    }
                    $parent->removeChild($node);
                    return true; // İlk-geçiş yeterli
                }
            }
        }
        return false;
    }

    /**
     * Test/dev için aday cache'ini sıfırla.
     */
    public static function resetCache(): void
    {
        self::$candidates = null;
    }

    /**
     * DEBUG — bir kaynak için tüm adayları skorla, hangileri seçilmiş döndür.
     * Sadece admin debug panel için kullanılır. enrich() davranışını
     * etkilemez; sadece "neden bu link konuldu/konulmadı" sorusuna cevap verir.
     *
     * @return array{
     *   total_candidates:int,
     *   matched:array<int,array{key:string,type:string,label:string,href:string,hits:int,score:float,multiplier:float,recency:float}>,
     *   picked_keys:array<int,string>
     * }
     */
    public static function debug(string $html, string $sourceType, int $sourceId, array $ctx = []): array
    {
        if (trim($html) === '') {
            return ['total_candidates' => 0, 'matched' => [], 'picked_keys' => []];
        }
        $candidates = self::candidates();
        $selfKey = $sourceType . ':' . $sourceId;
        $candidates = array_filter($candidates, static fn($c) => $c['key'] !== $selfKey);

        $bodyNorm = KeyphraseService::normalize(strip_tags($html));
        $matched = [];
        foreach ($candidates as $c) {
            $hits = self::countMatches($bodyNorm, $c['needles_norm']);
            if ($hits === 0) continue;
            $mult = self::contextMultiplier($c, $ctx);
            $score = $hits * $mult * $c['recency'];
            $matched[] = [
                'key'        => $c['key'],
                'type'       => $c['type'],
                'label'      => $c['label'],
                'href'       => $c['href'],
                'hits'       => $hits,
                'multiplier' => $mult,
                'recency'    => $c['recency'],
                'score'      => $score,
            ];
        }
        usort($matched, static fn($a, $b) => $b['score'] <=> $a['score']);
        $top2 = array_slice($matched, 0, self::MAX_LINKS_PER_PAGE);
        return [
            'total_candidates' => count(self::candidates()),
            'matched'          => $matched,
            'picked_keys'      => array_map(static fn($m) => $m['key'], $top2),
        ];
    }
}
