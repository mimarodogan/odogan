<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Yazı Analizi orkestratörü — tüm eksenleri tek "İçerik Notu"nda birleştirir.
 *
 * Eksenler (ağırlık): Odak anahtar kelime (25), Teknik SEO (20),
 * E-E-A-T & Otorite (20), AEO/AI (15), Yapı & Okunabilirlik (20).
 *
 * Tamamen kural tabanlı + saf (DB/IO yok) → birim test edilebilir.
 * AI katmanı ayrıdır (AiAnalysisService, talep-üzerine).
 *
 * @phpstan-type Part array{ok:bool,name:string,score:int,max:int,tip:string}
 */
final class ContentAnalysisService
{
    private const WEIGHTS = [
        'keyword'     => 25,
        'seo'         => 20,
        'eeat'        => 20,
        'aeo'         => 15,
        'readability' => 20,
    ];

    private const AUTHORITY_HINTS = [
        'gov.tr', 'edu.tr', 'mevzuat.gov.tr', 'resmigazete.gov.tr', 'tse.org.tr',
        'csb.gov.tr', 'mimarlarodasi.org.tr', 'imo.org.tr', 'tdk.gov.tr',
        'wikipedia.org', '.gov/', '.edu/',
    ];

    private const TRANSITIONS = [
        'ancak', 'ayrıca', 'dolayısıyla', 'örneğin', 'bu nedenle', 'bu yüzden',
        'sonuç olarak', 'çünkü', 'böylece', 'öte yandan', 'özellikle', 'kısacası',
        'bununla birlikte', 'ilk olarak', 'son olarak', 'nitekim', 'aksine', 'yani',
    ];

    private const QUESTION_HINTS = ['nedir', 'nasıl', 'neden', 'niçin', 'ne kadar', 'kaç ', 'hangi', 'ne zaman'];

    private const EXPERIENCE_HINTS = ['kendi ', 'deneyim', 'tecrübe', 'gözlemled', 'projemiz', 'projemde', 'tasarladık', 'denedik', 'şahsen', 'bizzat', 'uyguladık'];

    /**
     * @param array<string,mixed> $post     title, slug, excerpt, body, body_format, meta_title, meta_description, focus_keyword, secondary_keywords
     * @param array<string,mixed> $context  category_name, expertise (string[]), published_at, updated_at
     * @return array{grade:string,score:int,sections:array<int,array<string,mixed>>,actions:array<int,string>}
     */
    public static function analyze(array $post, array $context = []): array
    {
        $html  = (string) ($post['body'] ?? '');
        $plain = self::plain($html);

        $sections = [
            self::section('keyword',     'Odak Anahtar Kelime',    self::keyphrasePart($post, $html, $plain)),
            self::section('seo',         'Teknik SEO',             SeoScoreService::score($post)['parts']),
            self::section('eeat',        'E-E-A-T & Otorite',      self::eeatPart($post, $context, $html, $plain)),
            self::section('aeo',         'AEO / AI Görünürlüğü',   self::aeoPart($post, $html, $plain)),
            self::section('readability', 'Yapı & Okunabilirlik',   self::readabilityPart($html, $plain)),
        ];

        // Ağırlıklı bileşik skor (0-100)
        $composite = 0.0;
        foreach ($sections as $s) {
            $ratio = $s['max'] > 0 ? $s['score'] / $s['max'] : 0;
            $composite += $ratio * self::WEIGHTS[$s['key']];
        }
        $score = (int) round($composite);

        return [
            'grade'    => self::grade($score),
            'score'    => $score,
            'sections' => $sections,
            'actions'  => self::priorityActions($sections),
        ];
    }

    // ─── Eksen 1: Odak anahtar kelime ────────────────────────────────
    /** @return array<int,array<string,mixed>> */
    private static function keyphrasePart(array $post, string $html, string $plain): array
    {
        $kw = trim((string) ($post['focus_keyword'] ?? ''));
        if ($kw === '') {
            return [[
                'ok' => false, 'name' => 'Odak kelime tanımlı', 'score' => 0, 'max' => 100,
                'tip' => 'Odak anahtar kelime boş — sağdaki "🎯 Odak Anahtar Kelime" alanına yazının hedef terimini gir; analiz buna göre puanlar.',
            ]];
        }

        $title   = (string) ($post['title'] ?? '');
        $slug    = (string) ($post['slug'] ?? '');
        $metaDsc = (string) ($post['meta_description'] ?? '') ?: (string) ($post['excerpt'] ?? '');
        $first   = self::firstParagraph($html, $plain);
        $heads   = implode(' ', array_map(static fn($h) => $h['text'], self::headings($html)));
        [$imgCount, , $imgAltText] = self::images($html);

        $density = KeyphraseService::density($plain, $kw);

        $p = [];
        $p[] = self::flag(KeyphraseService::containsAll($title, $kw), 'Başlıkta geçiyor', 20,
            'Odak kelimeyi başlığa (tercihen başa yakın) ekle.');
        $p[] = self::flag(KeyphraseService::containsAll($first, $kw), 'İlk paragrafta', 15,
            'Odak kelimeyi giriş paragrafına yerleştir (ilk 120 kelime).');
        $p[] = self::flag(KeyphraseService::containsAll($slug, $kw), 'URL slug\'ında', 10,
            'Slug odak kelimeyi içermiyor.');
        $p[] = self::flag(KeyphraseService::containsAll($metaDsc, $kw), 'Meta açıklamada', 15,
            'Meta açıklamaya odak kelimeyi ekle (SERP eşleşmesi + CTR).');
        $p[] = self::flag($heads !== '' && KeyphraseService::containsAll($heads, $kw), 'En az bir alt-başlıkta', 15,
            'Bir H2/H3 alt-başlığında odak kelimeyi (veya kökünü) kullan.');
        $p[] = self::flag($imgCount === 0 || KeyphraseService::containsAll($imgAltText, $kw), 'Görsel alt-text\'inde', 10,
            'En az bir görselin alt metnine odak kelimeyi ekle.');

        // Yoğunluk
        if ($density <= 0.0) {
            $dok = false; $dtip = 'Odak kelime gövdede hiç geçmiyor (veya çok az).';
        } elseif ($density > 3.0) {
            $dok = false; $dtip = "Yoğunluk %{$density} — fazla (keyword stuffing riski). %0.5–2.5 ideal.";
        } elseif ($density < 0.3) {
            $dok = false; $dtip = "Yoğunluk %{$density} — düşük. Doğal şekilde birkaç kez daha geçir.";
        } else {
            $dok = true; $dtip = "Yoğunluk %{$density} — sağlıklı ✓";
        }
        $p[] = ['ok' => $dok, 'name' => 'Anahtar kelime yoğunluğu', 'score' => $dok ? 15 : 0, 'max' => 15, 'tip' => $dtip];

        return $p;
    }

    // ─── Eksen 3: E-E-A-T & Otorite ──────────────────────────────────
    /** @return array<int,array<string,mixed>> */
    private static function eeatPart(array $post, array $context, string $html, string $plain): array
    {
        $links = self::links($html);
        $authority = 0;
        $external = 0;
        foreach ($links as $href) {
            if (!preg_match('#^https?://#i', $href)) {
                continue;
            }
            $external++;
            foreach (self::AUTHORITY_HINTS as $hint) {
                if (stripos($href, $hint) !== false) {
                    $authority++;
                    break;
                }
            }
        }

        $p = [];
        $p[] = self::flag($authority > 0, 'Otoriteye dış kaynak', 25,
            'Resmî/akademik bir kaynağa (.gov.tr, .edu.tr, mevzuat, TSE…) link ver — güven sinyali.');
        $p[] = self::flag($external > 0, 'Dış kaynak/atıf var', 10,
            'Yazıda hiç dış link yok; iddiaları kaynaklarla destekle.');

        // Konu–uzmanlık eşleşmesi
        $expertise = (array) ($context['expertise'] ?? []);
        $topic = trim((string) ($post['focus_keyword'] ?? '') . ' ' . (string) ($context['category_name'] ?? '') . ' ' . (string) ($post['secondary_keywords'] ?? '') . ' ' . (string) ($post['tags'] ?? ''));
        $match = false;
        if ($expertise && $topic !== '') {
            $expText = implode(' ', array_map('strval', $expertise));
            foreach (KeyphraseService::significantWords($topic) as $w) {
                if (KeyphraseService::containsAll($expText, $w)) {
                    $match = true;
                    break;
                }
            }
        }
        $p[] = [
            'ok' => $match || $expertise === [], 'name' => 'Konu–uzmanlık uyumu',
            'score' => ($match || $expertise === []) ? 20 : 0, 'max' => 20,
            'tip' => $expertise === []
                ? 'Yazar profilinde uzmanlık (knowsAbout) tanımlı değil — profili doldur.'
                : ($match ? 'Konu, yazarın uzmanlık alanlarıyla örtüşüyor ✓'
                    : 'Konu, yazarın profilindeki uzmanlık alanlarıyla örtüşmüyor görünüyor.'),
        ];

        // Tazelik
        $updated = (string) ($context['updated_at'] ?? $context['published_at'] ?? '');
        $freshOk = true; $freshTip = 'Tarih bilgisi yok.';
        if ($updated !== '' && ($ts = strtotime($updated))) {
            $months = (time() - $ts) / (30 * 86400);
            $freshOk = $months <= 18;
            $freshTip = $freshOk ? 'İçerik güncel ✓' : 'İçerik 18 aydan eski — gözden geçirip güncelle (tazelik sinyali).';
        }
        $p[] = ['ok' => $freshOk, 'name' => 'Tazelik', 'score' => $freshOk ? 10 : 0, 'max' => 10, 'tip' => $freshTip];

        // Kaynakça + ilk-elden deneyim
        $hasRefs = (bool) preg_match('/(kaynak(lar|ça)?|referans|bibliyograf)/iu', $plain) || $authority >= 2;
        $p[] = self::flag($hasRefs, 'Kaynakça/atıf bölümü', 5, 'Sonda "Kaynaklar" bölümü eklemek E-E-A-T\'yi güçlendirir.');

        $exp = false;
        $low = mb_strtolower($plain, 'UTF-8');
        foreach (self::EXPERIENCE_HINTS as $h) {
            if (mb_strpos($low, $h) !== false) { $exp = true; break; }
        }
        $p[] = self::flag($exp, 'İlk-elden deneyim izi', 5, 'İlk-elden deneyim/örnek ekle ("kendi projemde…") — Experience sinyali.');

        return $p;
    }

    // ─── Eksen 4: AEO / AI görünürlüğü ───────────────────────────────
    /** @return array<int,array<string,mixed>> */
    private static function aeoPart(array $post, string $html, string $plain): array
    {
        $excerpt = trim((string) ($post['excerpt'] ?? ''));
        $heads = self::headings($html);
        $headTexts = array_map(static fn($h) => mb_strtolower($h['text'], 'UTF-8'), $heads);

        $hasQuestionHead = false;
        foreach ($headTexts as $h) {
            if (str_contains($h, '?')) { $hasQuestionHead = true; break; }
            foreach (self::QUESTION_HINTS as $q) {
                if (str_contains($h, $q)) { $hasQuestionHead = true; break 2; }
            }
        }

        $hasList  = (bool) preg_match('/<(ul|ol)\b/i', $html);
        $hasTable = (bool) preg_match('/<table\b/i', $html);
        $hasDigits = (bool) preg_match('/\d/', $plain);
        // Tanım cümlesi: "... -dır/-dir/-dur/-dür/-tır..." ile biten cümle
        $hasDefinition = (bool) preg_match('/\b\w+(d[ıiuü]r|t[ıiuü]r)\.?/u', $plain);
        $faqLike = count(array_filter($headTexts, static fn($h) => str_contains($h, '?'))) >= 2
            || (bool) preg_match('/sıkça sorulan|s\.s\.s|sss/iu', $plain);

        $p = [];
        $p[] = self::flag(mb_strlen($excerpt) >= 80, 'TL;DR / üst-özet', 25,
            'Kısa bir özet (excerpt) yaz — AI Overview/sosyal paylaşım bunu çeker, şemandaki abstract\'ı besler.');
        $p[] = self::flag($hasQuestionHead, 'Soru-biçimli başlık', 20,
            'En az bir alt-başlığı soru olarak kur ("… nedir?", "nasıl yapılır?") — AI motorları sorguyla eşler.');
        $p[] = self::flag($hasList || $hasTable, 'Liste veya tablo', 20,
            'Madde listesi/tablo ekle — AI motorları yapısal içeriği daha kolay çıkarır.');
        $p[] = self::flag($hasDefinition, 'Tanım cümlesi', 15,
            'Net bir tanım cümlesi kur ("X, …-dır.") — "X nedir" sorgularında çıkarılır.');
        $p[] = self::flag($hasDigits, 'Somut veri/sayı', 10,
            'Somut sayı/ölçü/tarih ekle — AI motorları somut veriyi tercih eder.');
        $p[] = self::flag($faqLike, 'SSS / soru bloğu', 10,
            'Birkaç soru-cevap (SSS) ekle — rich result kalktı ama AEO için hâlâ değerli.');

        return $p;
    }

    // ─── Eksen 5: Yapı & Okunabilirlik ───────────────────────────────
    /** @return array<int,array<string,mixed>> */
    private static function readabilityPart(string $html, string $plain): array
    {
        $read = ReadabilityService::atesman($plain);
        $heads = self::headings($html);
        $wordCount = $read['words'];

        $p = [];

        // Ateşman okunabilirlik → 25p
        $rs = (int) $read['score'];
        $rScore = $rs >= 70 ? 25 : ($rs >= 50 ? 18 : ($rs >= 30 ? 10 : 4));
        $p[] = ['ok' => $rs >= 50, 'name' => 'Okunabilirlik (Ateşman)', 'score' => $rScore, 'max' => 25,
            'tip' => "Ateşman {$rs}/100 — {$read['category']}. {$read['tip']}"];

        // Gövdede H1 olmamalı (başlık zaten H1)
        $bodyH1 = (bool) preg_match('/<h1\b/i', $html);
        $p[] = ['ok' => !$bodyH1, 'name' => 'Gövdede H1 yok', 'score' => $bodyH1 ? 0 : 15, 'max' => 15,
            'tip' => $bodyH1 ? 'Gövdede H1 var — başlık zaten H1\'dir, gövdeyi H2\'den başlat.' : 'Başlık hiyerarşisi temiz ✓'];

        // Alt-başlık dağılımı + seviye atlama
        $h2 = count(array_filter($heads, static fn($h) => $h['level'] === 2));
        $skip = self::hasHeadingSkip($heads);
        $structOk = ($wordCount < 300 || $h2 >= 1) && !$skip;
        $p[] = ['ok' => $structOk, 'name' => 'Alt-başlık yapısı', 'score' => $structOk ? 15 : 6, 'max' => 15,
            'tip' => $skip ? 'Başlık seviyesi atlanmış (örn. H2\'den H4\'e). Sırayla in.'
                : (($wordCount >= 300 && $h2 < 1) ? 'Uzun yazıda alt-başlık yok — H2\'lerle böl.' : 'Alt-başlık yapısı uygun ✓')];

        // Uzun cümleler
        $longSent = self::longSentenceCount($plain, 28);
        $p[] = ['ok' => $longSent === 0, 'name' => 'Uzun cümle', 'score' => $longSent === 0 ? 15 : ($longSent <= 3 ? 8 : 3), 'max' => 15,
            'tip' => $longSent === 0 ? 'Cümle uzunlukları iyi ✓' : "{$longSent} cümle 28+ kelime — bunları böl/kısalt."];

        // İç link
        $internal = self::internalLinkCount($html);
        $p[] = self::flag($internal > 0, 'İç link', 15,
            'Kendi sitenden ilgili yazılara en az bir iç link ver (sağdaki "İlgili Yazılar" önerilerini kullan).');

        // Geçiş kelimeleri
        $trans = self::transitionRatio($plain);
        $tOk = $trans >= 0.20;
        $p[] = ['ok' => $tOk, 'name' => 'Geçiş kelimeleri', 'score' => $tOk ? 15 : ($trans >= 0.10 ? 8 : 3), 'max' => 15,
            'tip' => $tOk ? 'Akış bağlaçları yeterli ✓' : 'Geçiş kelimelerini artır ("ancak, dolayısıyla, örneğin…") — akış için.'];

        return $p;
    }

    // ─── Yardımcılar ─────────────────────────────────────────────────

    /** @param array<int,array<string,mixed>> $parts */
    private static function section(string $key, string $label, array $parts): array
    {
        $score = 0; $max = 0;
        foreach ($parts as $p) {
            $score += (int) $p['score'];
            $max   += (int) $p['max'];
        }
        return [
            'key' => $key, 'label' => $label, 'weight' => self::WEIGHTS[$key] ?? 0,
            'score' => $score, 'max' => $max,
            'pct' => $max > 0 ? (int) round($score / $max * 100) : 0,
            'parts' => $parts,
        ];
    }

    private static function flag(bool $ok, string $name, int $max, string $failTip, string $okTip = ''): array
    {
        return [
            'ok' => $ok, 'name' => $name, 'score' => $ok ? $max : 0, 'max' => $max,
            'tip' => $ok ? ($okTip ?: $name . ' ✓') : $failTip,
        ];
    }

    /** @param array<int,array<string,mixed>> $sections @return array<int,string> */
    private static function priorityActions(array $sections): array
    {
        $cand = [];
        foreach ($sections as $s) {
            $w = (int) $s['weight'];
            foreach ($s['parts'] as $p) {
                if (!empty($p['ok']) || (int) $p['max'] === 0) {
                    continue;
                }
                $impact = ($p['max'] - $p['score']) / max(1, $p['max']) * $w;
                $cand[] = ['impact' => $impact, 'tip' => '[' . $s['label'] . '] ' . $p['tip']];
            }
        }
        usort($cand, static fn($a, $b) => $b['impact'] <=> $a['impact']);
        return array_map(static fn($c) => $c['tip'], array_slice($cand, 0, 3));
    }

    private static function grade(int $score): string
    {
        return $score >= 90 ? 'A' : ($score >= 75 ? 'B' : ($score >= 60 ? 'C' : ($score >= 45 ? 'D' : ($score >= 30 ? 'E' : 'F'))));
    }

    private static function plain(string $html): string
    {
        $t = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) preg_replace('/\s+/u', ' ', $t));
    }

    /** @return array<int,array{level:int,text:string}> */
    private static function headings(string $html): array
    {
        $out = [];
        if (preg_match_all('/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $h) {
                $txt = trim((string) preg_replace('/\s+/u', ' ', strip_tags($h[2])));
                $out[] = ['level' => (int) $h[1], 'text' => $txt];
            }
        }
        return $out;
    }

    private static function firstParagraph(string $html, string $plain): string
    {
        if (preg_match('/<p\b[^>]*>(.*?)<\/p>/is', $html, $m)) {
            $t = trim((string) preg_replace('/\s+/u', ' ', strip_tags($m[1])));
            if ($t !== '') {
                return $t;
            }
        }
        // Fallback: ilk ~120 kelime
        $words = explode(' ', $plain);
        return implode(' ', array_slice($words, 0, 120));
    }

    /** @return array{0:int,1:int,2:string} [imgCount, altCount, altMetni] */
    private static function images(string $html): array
    {
        preg_match_all('/<img\b[^>]*>/i', $html, $imgs);
        $imgCount = count($imgs[0]);
        $altCount = 0; $altText = '';
        foreach ($imgs[0] as $tag) {
            if (preg_match('/\salt\s*=\s*"([^"]*)"/i', $tag, $am) && trim($am[1]) !== '') {
                $altCount++;
                $altText .= ' ' . $am[1];
            }
        }
        return [$imgCount, $altCount, trim($altText)];
    }

    /** @return string[] */
    private static function links(string $html): array
    {
        preg_match_all('/<a\b[^>]*\shref\s*=\s*"([^"]+)"/i', $html, $m);
        return $m[1];
    }

    private static function internalLinkCount(string $html): int
    {
        $n = 0;
        foreach (self::links($html) as $href) {
            // Göreli link veya kendi domaini → iç link
            if (!preg_match('#^https?://#i', $href) && !str_starts_with($href, '#') && !str_starts_with($href, 'mailto:')) {
                $n++;
            } elseif (stripos($href, 'odogan.com.tr') !== false) {
                $n++;
            }
        }
        return $n;
    }

    /** @return string[] cümleler */
    private static function sentences(string $plain): array
    {
        $parts = preg_split('/[.!?]+/u', $plain) ?: [];
        return array_values(array_filter(array_map('trim', $parts), static fn($s) => $s !== ''));
    }

    private static function longSentenceCount(string $plain, int $threshold): int
    {
        $n = 0;
        foreach (self::sentences($plain) as $s) {
            if (count(explode(' ', $s)) > $threshold) {
                $n++;
            }
        }
        return $n;
    }

    private static function transitionRatio(string $plain): float
    {
        $sents = self::sentences($plain);
        if ($sents === []) {
            return 0.0;
        }
        $hit = 0;
        foreach ($sents as $s) {
            $low = mb_strtolower($s, 'UTF-8');
            foreach (self::TRANSITIONS as $t) {
                if (str_contains($low, $t)) { $hit++; break; }
            }
        }
        return round($hit / count($sents), 2);
    }

    /** @param array<int,array{level:int,text:string}> $heads */
    private static function hasHeadingSkip(array $heads): bool
    {
        $prev = 1; // başlık (H1) varsayımı
        foreach ($heads as $h) {
            if ($h['level'] > $prev + 1) {
                return true;
            }
            $prev = $h['level'];
        }
        return false;
    }
}
