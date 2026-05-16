<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Türkçe okunabilirlik puanı — Mehmet Ateşman (1997) formülü.
 *
 *   Skor = 198.825
 *        - (40.175 × ort_hece_kelime)
 *        - (2.610  × ort_kelime_cümle)
 *
 * Aralıklar:
 *   90-100 → Çok kolay
 *   70-89  → Kolay
 *   50-69  → Orta
 *   30-49  → Zor
 *   0-29   → Çok zor
 */
final class ReadabilityService
{
    /**
     * @return array{
     *   score:int,
     *   category:string,
     *   words:int,
     *   sentences:int,
     *   syllables:int,
     *   avg_word_syllables:float,
     *   avg_sentence_words:float,
     *   tip:string
     * }
     */
    public static function atesman(string $plainText): array
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($plainText)));

        // Kelime
        $words = array_values(array_filter(preg_split('/\s+/u', $text) ?: []));
        $wordCount = count($words);

        // Cümle (.!? ile böl)
        $sentences = array_filter(preg_split('/[\.!\?]+/u', $text) ?: [], fn($s) => trim($s) !== '');
        $sentenceCount = max(1, count($sentences));

        // Hece (Türkçe sesli harfler yaklaşım)
        $syllables = 0;
        foreach ($words as $w) {
            $syllables += self::countSyllables($w);
        }

        if ($wordCount === 0) {
            return [
                'score' => 0, 'category' => 'Boş', 'words' => 0, 'sentences' => 0, 'syllables' => 0,
                'avg_word_syllables' => 0.0, 'avg_sentence_words' => 0.0,
                'tip' => 'Yazıyı genişletince hesaplanır.',
            ];
        }

        $avgSyl = $syllables / $wordCount;
        $avgSentWords = $wordCount / $sentenceCount;
        $score = 198.825 - (40.175 * $avgSyl) - (2.610 * $avgSentWords);
        $score = (int) round(max(0, min(100, $score)));

        [$cat, $tip] = self::categorize($score, $avgSentWords);

        return [
            'score' => $score,
            'category' => $cat,
            'words' => $wordCount,
            'sentences' => $sentenceCount,
            'syllables' => $syllables,
            'avg_word_syllables' => round($avgSyl, 2),
            'avg_sentence_words' => round($avgSentWords, 2),
            'tip' => $tip,
        ];
    }

    /**
     * Türkçe sesli harf sayısı = hece sayısı (kaba ama doğruluğa yakın).
     */
    private static function countSyllables(string $word): int
    {
        $w = mb_strtolower($word, 'UTF-8');
        $count = preg_match_all('/[aeıioöuüâîû]/u', $w);
        return max(1, (int) $count);
    }

    /**
     * @return array{0:string,1:string} [kategori, ipucu]
     */
    private static function categorize(int $score, float $avgSentWords): array
    {
        if ($score >= 90) {
            return ['Çok kolay', 'İlkokul seviyesi — daha çok dergi/gazete tarzı kısa cümleler.'];
        }
        if ($score >= 70) {
            return ['Kolay', 'Geniş kitle için okunaklı. Mimarlık yazıları için ideal.'];
        }
        if ($score >= 50) {
            $hint = $avgSentWords > 25 ? ' Cümleleri kısaltmak okunabilirliği artırır.' : '';
            return ['Orta', 'Lise seviyesi.' . $hint];
        }
        if ($score >= 30) {
            return ['Zor', 'Üniversite seviyesi — uzun cümleler ve karmaşık kelimeler var. Bazı paragrafları sadeleştir.'];
        }
        return ['Çok zor', 'Akademik metin. Geniş okur kitlesi için cümleleri kısalt ve basit kelimeler kullan.'];
    }
}
