<?php
declare(strict_types=1);

namespace App\Services\Glossary;

/**
 * Sözlük kaynak URL'leri için doğrulama yardımcıları.
 *
 * AiGlossaryService'ten ayrıldı (single-responsibility). İki public
 * method:
 *
 *   - isHomepageUrl(): URL "anasayfa" tipinde mi (path boş veya sadece
 *     dil kodu)? AI uydurma URL'lerine düşmemek için filtre.
 *
 *   - isAlive(): URL gerçekten erişilebilir mi (HEAD/GET 200-399)?
 *     AI'nın verdiği kaynaklarda kullanılır → ölü olanlar UI'da rozet
 *     ile işaretlenir.
 *
 * İkisi de saf (DB/state yok), test edilebilir.
 */
final class UrlVerifier
{
    /**
     * URL bir "anasayfa" mı (yani spesifik içerik URL'i değil)?
     *
     * Tipik AI hallüsinasyon davranışı: emin olmadığında "güvenli" diye
     * kök domain verir (örn. "https://tdk.gov.tr"). Bu metot böyle
     * URL'leri yakalar; arayan tarafta url alanı boşaltılır → text
     * akademik atıf olarak kalır.
     *
     * Kural:
     *   - path boş veya "/" → anasayfa (içerik yok)
     *   - path var, query/fragment yok, path 2-3 harf (dil kodu) → anasayfa
     *   - aksi → deep URL
     */
    public static function isHomepageUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return true; // parse edemiyorsak şüpheli
        }
        $path     = (string) ($parts['path']     ?? '');
        $query    = (string) ($parts['query']    ?? '');
        $fragment = (string) ($parts['fragment'] ?? '');

        // Query veya fragment varsa muhtemelen spesifik içerik (?ara=, ?word=, vs)
        if ($query !== '' || $fragment !== '') {
            return false;
        }

        // Path boş veya sadece "/" → kesinlikle anasayfa
        $trimmedPath = trim($path, '/');
        if ($trimmedPath === '') {
            return true;
        }

        // Path çok kısa ve sadece dil kodu olabilir (tr, en, fr) → şüpheli
        // ama "tr" bir kod olabileceği gibi "tr-makale-adi" anlamlı olabilir.
        // 2-3 harf tek segment → anasayfa olarak işaretle
        if (mb_strlen($trimmedPath) <= 3 && !str_contains($trimmedPath, '/')) {
            return true;
        }

        return false;
    }

    /**
     * URL canlı mı? HEAD ile dener, 405/501 ise GET fallback. 4 sn timeout.
     * Sözlük girdisi başına 8 URL × 4 sn = max 32 sn bekleme.
     */
    public static function isAlive(string $url): bool
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 4,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 4,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_USERAGENT      => 'OdoganBot/1.0 (+https://odogan.com.tr) link-check',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 400) {
            return true;
        }

        // HEAD reddedildi (405 / 501 veya 0=timeout) — GET ile son şans
        if ($code === 405 || $code === 501 || $code === 0) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 4,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 4,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_USERAGENT      => 'OdoganBot/1.0 (+https://odogan.com.tr) link-check',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_RANGE          => '0-1024', // sadece ilk 1KB
            ]);
            curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $code >= 200 && $code < 400;
        }

        return false;
    }
}
