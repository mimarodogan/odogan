<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Post;

/**
 * Internal Link Önerisi (Tier 5 feature 4.5).
 *
 * Body'den anahtar kelimeleri çıkar, FULLTEXT search ile aday yazılar bul,
 * MATCH AGAINST skoruna göre sırala, en alakalı yazıları döndür.
 *
 * Yazı yazarken sidebar'da debounced çağrılır → tıkla → editor'e link insert.
 *
 * Not: T1 sonrası etiket sistemi kaldırıldı, etiket-bonus skorlama artık yok;
 * sadece FULLTEXT relevance score kullanılır.
 */
final class PostSuggestionService
{
    /**
     * Türkçe stop-word listesi — kelime extraction'da skip.
     */
    private const STOP_WORDS = [
        'bir', 'bu', 'şu', 've', 'veya', 'ile', 'için', 'gibi', 'kadar', 'daha',
        'ama', 'fakat', 'çünkü', 'ki', 'da', 'de', 'ta', 'te', 'mi', 'mı', 'mu', 'mü',
        'çok', 'az', 'değil', 'olarak', 'olur', 'oldu', 'olan', 'olmak', 'edilir',
        'her', 'bazı', 'tüm', 'bütün', 'birkaç', 'hiç', 'hep', 'sadece',
        'şey', 'kişi', 'yani', 'ise', 'eğer', 'önce', 'sonra', 'sırasında',
        'the', 'and', 'or', 'of', 'in', 'to', 'a', 'an', 'is', 'for',
    ];

    private const MIN_WORD_LEN = 4;
    private const MAX_KEYWORDS = 6;
    private const MIN_BODY_LEN = 80; // body en az 80 karakter olmalı (anlamlı suggestion için)

    /**
     * Body'ye göre alakalı yazıları öner.
     *
     * @param string   $body          Yazının current body'si (HTML/markdown fark etmez)
     * @param int|null $excludePostId Edit modunda mevcut yazıyı dışla
     * @param int      $limit         Kaç öneri (default 5)
     * @return array<int,array{id:int,title:string,slug:string,category_slug:string,score:float}>
     */
    public static function findSimilar(string $body, ?int $excludePostId = null, int $limit = 5): array
    {
        $plain = self::plainText($body);
        if (mb_strlen($plain) < self::MIN_BODY_LEN) {
            return [];
        }
        $keywords = self::extractKeywords($plain, self::MAX_KEYWORDS);
        if (!$keywords) {
            return [];
        }
        // FULLTEXT boolean query: zorunlu olmayan, prefix matching
        $query = implode(' ', array_map(static fn($w) => '+' . $w . '*', $keywords));
        $candidates = Post::search($query, $limit * 3);
        if (!$candidates) {
            return [];
        }
        $suggestions = [];
        foreach ($candidates as $c) {
            $pid = (int) $c['id'];
            if ($excludePostId !== null && $pid === $excludePostId) {
                continue;
            }
            $score = (float) ($c['score'] ?? 0);
            $suggestions[] = [
                'id' => $pid,
                'title' => (string) $c['title'],
                'slug' => (string) $c['slug'],
                'category_slug' => (string) ($c['category_slug'] ?? ''),
                'category_name' => (string) ($c['category_name'] ?? ''),
                'score' => round($score, 3),
            ];
        }
        usort($suggestions, static fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($suggestions, 0, max(1, $limit));
    }

    /**
     * Stop-word'leri filtrele, kelime frekansını TF-bazlı skorla,
     * en sık geçen top-N kelimeyi döndür.
     *
     * @return string[]
     */
    public static function extractKeywords(string $plain, int $top = 6): array
    {
        $plain = mb_strtolower($plain, 'UTF-8');
        // Sadece harfler ve rakamlar (Türkçe karakterler dahil)
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $plain) ?: [];
        $freq = [];
        $stop = array_flip(self::STOP_WORDS);
        foreach ($tokens as $t) {
            if (mb_strlen($t) < self::MIN_WORD_LEN) continue;
            if (isset($stop[$t])) continue;
            $freq[$t] = ($freq[$t] ?? 0) + 1;
        }
        arsort($freq);
        $keywords = [];
        foreach ($freq as $word => $count) {
            $keywords[] = $word;
            if (count($keywords) >= $top) break;
        }
        return $keywords;
    }

    /**
     * Body'den HTML strip + boşluk normalize.
     */
    private static function plainText(string $body): string
    {
        $stripped = strip_tags($body);
        $stripped = html_entity_decode($stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $stripped = (string) preg_replace('/\s+/u', ' ', $stripped);
        return trim($stripped);
    }
}
