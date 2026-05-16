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

        // 1) Title length
        $title = trim((string) ($post['title'] ?? ''));
        $tlen = mb_strlen($title);
        $tScore = ($tlen >= 50 && $tlen <= 60) ? 20
                : (($tlen >= 35 && $tlen <= 70) ? 12
                : (($tlen >= 20 && $tlen <= 90) ? 6 : 0));
        $parts[] = [
            'name' => 'Başlık uzunluğu',
            'score' => $tScore, 'max' => 20, 'ok' => $tScore >= 12,
            'tip' => $tlen === 0
                ? 'Başlık boş.'
                : ($tlen < 50
                    ? "Başlık {$tlen} karakter — 50-60 ideal aralık (SERP'te kesilmesin)."
                    : ($tlen > 60
                        ? "Başlık {$tlen} karakter — Google'da kesilebilir, 60 altı önerilir."
                        : 'Başlık ideal uzunlukta ✓')),
        ];

        // 2) Meta description
        $desc = trim((string) ($post['meta_description'] ?? '')) ?: trim((string) ($post['excerpt'] ?? ''));
        $dlen = mb_strlen($desc);
        $dScore = ($dlen >= 150 && $dlen <= 160) ? 20
                : (($dlen >= 120 && $dlen <= 180) ? 12
                : (($dlen >= 70 && $dlen <= 200) ? 6 : 0));
        $parts[] = [
            'name' => 'Meta açıklama',
            'score' => $dScore, 'max' => 20, 'ok' => $dScore >= 12,
            'tip' => $dlen === 0
                ? 'Meta açıklama boş — yazıdan otomatik üretilir ama elle yazmak daha iyi.'
                : ($dlen < 150 ? "Açıklama {$dlen} karakter — 150-160 ideal."
                : ($dlen > 160 ? "Açıklama {$dlen} karakter — Google'da kesilebilir."
                : 'Açıklama ideal ✓')),
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
