<?php
declare(strict_types=1);

namespace App\Services;

/**
 * SEO skoru (0-100) — yazı yazarken canlı geri bildirim.
 *
 * Skor dağılımı:
 *  - Title 50-60 char        → 20p
 *  - Meta description 150-160 → 20p
 *  - Slug temiz (no TR, < 75) → 15p
 *  - Body img alt doluluğu    → 15p
 *  - Word count ≥ 300         → 20p
 *  - Meta title + desc dolu   → 10p
 *
 * Tavsiye objeleri: her eksik puan için kullanıcıya gösterilen Türkçe ipucu.
 */
final class SeoScoreService
{
    /**
     * @param array{
     *   title?:string,
     *   slug?:string,
     *   excerpt?:string,
     *   body?:string,
     *   body_format?:string,
     *   meta_title?:string,
     *   meta_description?:string
     * } $post
     *
     * @return array{score:int, max:int, parts:array<int,array{name:string,score:int,max:int,ok:bool,tip:string}>}
     */
    public static function score(array $post): array
    {
        $parts = [];

        // 1) Title length — Modern aralık (Türkçe + Google 2024+ SERP standardı)
        //   30-65 char ideal (mobile-first, kesilmez)
        //   25-30 + 65-75 iyi
        //   18-25 + 75-90 orta
        //   <18 + >90 zayıf
        $title = trim((string) ($post['title'] ?? ''));
        $tlen = mb_strlen($title);
        if ($tlen === 0) {
            $tScore = 0;
        } elseif ($tlen >= 30 && $tlen <= 65) {
            $tScore = 20;
        } elseif (($tlen >= 25 && $tlen < 30) || ($tlen > 65 && $tlen <= 75)) {
            $tScore = 15;
        } elseif (($tlen >= 18 && $tlen < 25) || ($tlen > 75 && $tlen <= 90)) {
            $tScore = 10;
        } elseif ($tlen >= 10) {
            // 10-17 veya 91+ — zayıf ama puan var (önceki elseif'ler 18-24 ve 76-90'ı yakaladı)
            $tScore = 5;
        } else {
            $tScore = 0;
        }
        $parts[] = [
            'name' => 'Başlık uzunluğu',
            'score' => $tScore, 'max' => 20, 'ok' => $tScore >= 15,
            'tip' => $tlen === 0
                ? 'Başlık boş.'
                : ($tlen >= 30 && $tlen <= 65
                    ? "Başlık {$tlen} karakter — ideal aralıkta ✓"
                    : ($tlen < 30
                        ? "Başlık {$tlen} karakter — biraz daha uzatabilirsin (30-65 ideal, anahtar kelime için yer var)."
                        : "Başlık {$tlen} karakter — Google SERP'te kesilebilir (65 üzeri risk)."
                    )),
        ];

        // 2) Meta description — Modern aralık
        //   120-160 ideal (Google snippet width 160 civarı)
        //   90-120 + 160-200 iyi
        //   60-90 + 200-250 orta
        //   <60 + >250 zayıf
        $desc = trim((string) ($post['meta_description'] ?? '')) ?: trim((string) ($post['excerpt'] ?? ''));
        $dlen = mb_strlen($desc);
        if ($dlen === 0) {
            $dScore = 0;
        } elseif ($dlen >= 120 && $dlen <= 160) {
            $dScore = 20;
        } elseif (($dlen >= 90 && $dlen < 120) || ($dlen > 160 && $dlen <= 200)) {
            $dScore = 15;
        } elseif (($dlen >= 60 && $dlen < 90) || ($dlen > 200 && $dlen <= 250)) {
            $dScore = 10;
        } else {
            $dScore = 5;
        }
        $parts[] = [
            'name' => 'Meta açıklama',
            'score' => $dScore, 'max' => 20, 'ok' => $dScore >= 15,
            'tip' => $dlen === 0
                ? 'Meta açıklama boş — yazıdan otomatik üretilir ama elle yazmak SERP CTR\'ını artırır.'
                : ($dlen >= 120 && $dlen <= 160
                    ? "Açıklama {$dlen} karakter — ideal ✓"
                    : ($dlen < 120
                        ? "Açıklama {$dlen} karakter — biraz uzatabilirsin (120-160 ideal)."
                        : "Açıklama {$dlen} karakter — Google'da kesilebilir (160 üzeri risk).")),
        ];

        // 3) Slug
        $slug = trim((string) ($post['slug'] ?? ''));
        $slen = mb_strlen($slug);
        $hasTr = (bool) preg_match('/[çğıöşüÇĞİÖŞÜ_]/u', $slug);
        $sScore = ($slug !== '' && !$hasTr && $slen <= 75 && preg_match('/^[a-z0-9\-]+$/', $slug)) ? 15
                : (($slug !== '' && $slen <= 100) ? 8 : 0);
        $parts[] = [
            'name' => 'URL Slug',
            'score' => $sScore, 'max' => 15, 'ok' => $sScore >= 8,
            'tip' => $slug === ''
                ? 'Slug boş (otomatik üretilecek).'
                : ($hasTr
                    ? 'Slug\'da Türkçe karakter / alt çizgi var. Sadece a-z, 0-9, tire önerilir.'
                    : ($slen > 75 ? "Slug {$slen} karakter — 75 altı önerilir." : 'Slug temiz ✓')),
        ];

        // 4) Alt text doluluğu (body içinde img count vs alt-text count)
        $body = (string) ($post['body'] ?? '');
        preg_match_all('/<img[^>]*>/i', $body, $imgs);
        preg_match_all('/<img[^>]*\salt\s*=\s*"([^"]+)"/i', $body, $alts);
        $imgCount = count($imgs[0] ?? []);
        $altCount = 0;
        foreach (($alts[1] ?? []) as $alt) {
            if (trim($alt) !== '') $altCount++;
        }
        $aScore = $imgCount === 0 ? 15 // image yoksa tam puan (mağdur etme)
                : ($altCount === $imgCount ? 15
                : ($altCount >= floor($imgCount * 0.6) ? 8 : 0));
        $parts[] = [
            'name' => 'Görsel alt-text',
            'score' => $aScore, 'max' => 15, 'ok' => $aScore >= 8,
            'tip' => $imgCount === 0
                ? 'Yazıda görsel yok.'
                : ($altCount === $imgCount
                    ? "Tüm {$imgCount} görsel için alt-text var ✓"
                    : "{$imgCount} görselden {$altCount} tanesinde alt-text var — hepsine ekle (a11y + SEO)."),
        ];

        // 5) Word count
        $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags($body)));
        $wc = count(array_filter(preg_split('/\s+/u', $plain) ?: []));
        $wScore = $wc >= 1500 ? 20
                : ($wc >= 800 ? 16
                : ($wc >= 500 ? 12
                : ($wc >= 300 ? 8
                : ($wc >= 150 ? 4 : 0))));
        $parts[] = [
            'name' => 'Kelime sayısı',
            'score' => $wScore, 'max' => 20, 'ok' => $wScore >= 8,
            'tip' => $wc < 300
                ? "Yazı {$wc} kelime — derinleştirilebilir (300+ önerilen minimum)."
                : ($wc >= 1500 ? "Yazı {$wc} kelime — kapsamlı ✓"
                : "Yazı {$wc} kelime ✓"),
        ];

        // 6) Meta title + meta description dolu mu
        $mt = trim((string) ($post['meta_title'] ?? ''));
        $md = trim((string) ($post['meta_description'] ?? ''));
        $mScore = ($mt !== '' && $md !== '') ? 10
                : (($mt !== '' || $md !== '') ? 5 : 0);
        $parts[] = [
            'name' => 'Meta alanları dolu',
            'score' => $mScore, 'max' => 10, 'ok' => $mScore >= 5,
            'tip' => $mScore === 10
                ? 'Meta başlık + açıklama elle yazılmış ✓'
                : ($mScore === 5
                    ? 'Meta alanlarından sadece biri dolu — diğerini de yaz.'
                    : 'Meta başlık ve açıklama elle yazılmamış (otomatik üretilir).'),
        ];

        $total = 0;
        $max = 0;
        foreach ($parts as $p) {
            $total += $p['score'];
            $max += $p['max'];
        }

        return [
            'score' => $total,
            'max' => $max,
            'parts' => $parts,
        ];
    }
}
