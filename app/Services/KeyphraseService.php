<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Türkçe-farkındalıklı anahtar kelime eşleşme motoru.
 *
 * Türkçe sondan eklemeli olduğu için düz (exact) eşleşme yetersizdir:
 *   "mimarlık" ararken "mimarlığın, mimarlıkta, mimarlığa" da sayılmalı.
 * Bu yüzden kelimeler **kök/gövde toleranslı** eşleştirilir:
 *   - Türkçe-doğru küçük harf (İ→i, I→ı),
 *   - kök sonu sert ünsüz (k/p/ç/t/g) ek alınca yumuşadığı için son harf düşürülür,
 *   - gövde prefix'i (startsWith) ile ekli biçimler yakalanır.
 *
 * Tamamen saf (DB/IO yok) → birim test edilebilir.
 */
final class KeyphraseService
{
    /**
     * Türkçe-doğru küçük harf + ASCII-katlama + noktalama → boşluk.
     *
     * ASCII katlama (ç→c, ş→s, ı/İ→i, ö→o, ü→u, ğ→g) sayesinde slug'lar
     * (zaten diakritiksiz) ve kullanıcının Türkçe karaktersiz yazdığı kelimeler
     * de eşleşir. Eşleştirme her iki tarafta aynı şekilde katlandığı için tutarlı.
     */
    public static function normalize(string $s): string
    {
        $s = str_replace(['I', 'İ'], ['ı', 'i'], $s);
        $s = mb_strtolower($s, 'UTF-8');
        $s = strtr($s, [
            'ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u',
            'â' => 'a', 'î' => 'i', 'û' => 'u',
        ]);
        // Harf/rakam dışındaki her şey ayraç.
        $s = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s);
        return trim((string) preg_replace('/\s+/u', ' ', $s));
    }

    /** @return string[] normalize edilmiş kelime listesi */
    public static function tokenize(string $s): array
    {
        $n = self::normalize($s);
        if ($n === '') {
            return [];
        }
        return explode(' ', $n);
    }

    public static function wordCount(string $s): int
    {
        return count(self::tokenize($s));
    }

    /**
     * Bir kelimenin karşılaştırma kökü: uzun kelimelerde sert/yumuşayan son
     * ünsüz düşürülür ki ekli biçimler prefix ile yakalansın.
     *   mimarlık → mimarlı   (mimarlığın, mimarlıkta da eşleşir)
     *   tasarım  → tasarım   (tasarımı, tasarımın ek olarak eklenir)
     */
    public static function wordRoot(string $word): string
    {
        $w = self::normalize($word);
        $len = mb_strlen($w);
        if ($len < 5) {
            return $w;
        }
        $last = mb_substr($w, -1);
        if (in_array($last, ['k', 'p', 'ç', 't', 'g', 'ğ', 'b', 'c', 'd'], true)) {
            return mb_substr($w, 0, $len - 1);
        }
        return $w;
    }

    /** Tek bir (normalize edilmiş) token, anahtar kelime köküyle eşleşiyor mu? */
    public static function tokenMatches(string $normToken, string $keywordWord): bool
    {
        $root = self::wordRoot($keywordWord);
        if ($root === '') {
            return false;
        }
        if (mb_strlen($root) < 3) {
            // Çok kısa kelime: yalnız tam eşleşme (yanlış pozitif önleme).
            return $normToken === self::normalize($keywordWord);
        }
        return str_starts_with($normToken, $root);
    }

    /**
     * Bir ifadenin "anlamlı" kelimeleri (>=3 harf). Hepsi kısaysa hepsini döner.
     * @return string[]
     */
    public static function significantWords(string $phrase): array
    {
        $words = self::tokenize($phrase);
        $sig = array_values(array_filter($words, static fn($w) => mb_strlen($w) >= 3));
        return $sig ?: $words;
    }

    /** Metin, ifadenin TÜM anlamlı kelimelerini (kök-toleranslı) içeriyor mu? */
    public static function containsAll(string $text, string $phrase): bool
    {
        $sig = self::significantWords($phrase);
        if ($sig === []) {
            return false;
        }
        $tokens = self::tokenize($text);
        foreach ($sig as $w) {
            $found = false;
            foreach ($tokens as $t) {
                if (self::tokenMatches($t, $w)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
        }
        return true;
    }

    /**
     * İfadenin metinde kaç kez geçtiği (yaklaşık): ifadenin ilk anlamlı
     * kelimesinin kök-eşleşme sayısı. Tek-kelime ifadelerde tam sayım.
     */
    public static function occurrences(string $text, string $phrase): int
    {
        $sig = self::significantWords($phrase);
        if ($sig === []) {
            return 0;
        }
        $primary = $sig[0];
        $count = 0;
        foreach (self::tokenize($text) as $t) {
            if (self::tokenMatches($t, $primary)) {
                $count++;
            }
        }
        return $count;
    }

    /** Anahtar kelime yoğunluğu (% — gövdedeki toplam kelimeye oran). */
    public static function density(string $body, string $phrase): float
    {
        $total = self::wordCount($body);
        if ($total === 0) {
            return 0.0;
        }
        return round(self::occurrences($body, $phrase) / $total * 100, 2);
    }
}
